<?php
// admin/transfers.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$branchSql = getBranchSqlFilter();
$current_branch_id = $_SESSION['active_branch'] ?? $_SESSION['branch_id'];
$isGlobalView = (empty($current_branch_id) || $current_branch_id == 'all');

$msg = '';
$error = '';

// Handle AJAX CRUD Operations
$is_ajax = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax) {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['ajax_action'])) {
        header('Content-Type: application/json');
        $act = $input['ajax_action'];
        $tid = $input['id'] ?? null;
        
        $t_stmt = $pdo->prepare("SELECT t.*, i.name as item_name, i.price_per_day, i.category_id, i.image_url FROM stock_transfers t JOIN items i ON t.item_id_from = i.id WHERE t.id = ?");
        $t_stmt->execute([$tid]);
        $transfer = $t_stmt->fetch();
        
        if ($transfer) {
            $can_manage = hasRole('super_admin') || $transfer['from_branch_id'] == $current_branch_id;
            $can_edit = hasRole('super_admin') || $transfer['requested_by'] == $_SESSION['user_id'] || $transfer['to_branch_id'] == $current_branch_id;
            
            if ($transfer['status'] !== 'pending') {
                echo json_encode(['success' => false, 'error' => 'Ce transfert a déjà été traité.']);
                exit;
            }

            if ($act === 'delete') {
                if ($can_edit) {
                    $pdo->prepare("DELETE FROM stock_transfers WHERE id = ?")->execute([$tid]);
                    echo json_encode(['success' => true, 'msg' => 'Transfert supprimé avec succès.']);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Non autorisé à supprimer.']);
                    exit;
                }
            }
            
            if ($act === 'edit') {
                if ($can_edit) {
                    $new_qty = (int)($input['quantity'] ?? 0);
                    if ($new_qty > 0) {
                        $pdo->prepare("UPDATE stock_transfers SET quantity = ? WHERE id = ?")->execute([$new_qty, $tid]);
                        echo json_encode(['success' => true, 'msg' => 'Quantité mise à jour.', 'new_qty' => $new_qty]);
                        exit;
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Quantité invalide.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Non autorisé à modifier.']);
                    exit;
                }
            }

            if ($can_manage) {
                if ($act === 'approve') {
                    $pdo->beginTransaction();
                    try {
                        // Check stock again
                        $check_stock = $pdo->prepare("SELECT quantity_total FROM items WHERE id = ?");
                        $check_stock->execute([$transfer['item_id_from']]);
                        $current_stock = $check_stock->fetchColumn();
                        if ($current_stock < $transfer['quantity']) {
                            throw new Exception("Stock insuffisant dans la succursale d'origine.");
                        }

                        $pdo->prepare("UPDATE items SET quantity_total = quantity_total - ? WHERE id = ?")->execute([$transfer['quantity'], $transfer['item_id_from']]);
                        
                        $check_dest = $pdo->prepare("SELECT id FROM items WHERE name = ? AND branch_id = ?");
                        $check_dest->execute([$transfer['item_name'], $transfer['to_branch_id']]);
                        $dest_item_id = $check_dest->fetchColumn();
                        
                        if ($dest_item_id) {
                            $pdo->prepare("UPDATE items SET quantity_total = quantity_total + ? WHERE id = ?")->execute([$transfer['quantity'], $dest_item_id]);
                        } else {
                            $pdo->prepare("INSERT INTO items (name, price_per_day, quantity_total, category_id, branch_id, image_url) VALUES (?, ?, ?, ?, ?, ?)")
                                ->execute([$transfer['item_name'], $transfer['price_per_day'], $transfer['quantity'], $transfer['category_id'], $transfer['to_branch_id'], $transfer['image_url']]);
                        }
                        
                        $pdo->prepare("UPDATE stock_transfers SET status = 'approved', processed_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $tid]);
                        $pdo->commit();
                        
                        require_once '../includes/notifications.php';
                        notifyBranch($transfer['to_branch_id'], "Nouveau Stock Reçu", "Transfert de {$transfer['quantity']}x {$transfer['item_name']} approuvé et ajouté à votre inventaire.", "system");
                        notifyBranch($transfer['from_branch_id'], "Transfert Expédié", "Vos {$transfer['quantity']}x {$transfer['item_name']} ont été expédiés.", "system");
                        
                        logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'STOCK_TRANSFER', "Transfert #$tid approuvé.");
                        
                        echo json_encode(['success' => true, 'msg' => 'Transfert approuvé avec succès.', 'new_status' => 'approved']);
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                        exit;
                    }
                    $pdo->prepare("UPDATE stock_transfers SET status = 'rejected', processed_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $tid]);
                    require_once '../includes/notifications.php';
                    notifyBranch($transfer['from_branch_id'], "Transfert Rejeté", "Votre demande de transfert pour {$transfer['quantity']}x {$transfer['item_name']} a été refusée.", "system");
                    
                    logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'STOCK_TRANSFER', "Transfert #$tid rejeté.");
                    
                    echo json_encode(['success' => true, 'msg' => 'Transfert rejeté.', 'new_status' => 'rejected']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Accès refusé pour valider ce transfert.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Transfert introuvable.']);
            exit;
        }
    }
}

// Handle Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_transfer'])) {
    if ($isGlobalView) {
        $error = "Veuillez sélectionner une succursale spécifique dans le sélecteur d'en-tête pour initier un transfert depuis celle-ci.";
    } else {
        $item_id = $_POST['item_id'];
        $to_branch = $_POST['to_branch'];
        $qty = (int)$_POST['quantity'];
        
        // Verify qty
        $check_q = $pdo->prepare("SELECT quantity_total, name FROM items WHERE id = ? AND branch_id = ?");
        $check_q->execute([$item_id, $current_branch_id]);
        $item_info = $check_q->fetch();
        
        if ($item_info && $item_info['quantity_total'] >= $qty && $qty > 0) {
            $stmt = $pdo->prepare("INSERT INTO stock_transfers (item_id_from, from_branch_id, to_branch_id, quantity, requested_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$item_id, $current_branch_id, $to_branch, $qty, $_SESSION['user_id']]);
            $msg = "Demande de transfert créée ! En attente d'approbation.";
            
            require_once '../includes/notifications.php';
            notifyBranch(null, "Demande de Transfert", "Une demande de transfert de $qty " . $item_info['name'] . " est en attente d'approbation.", "system"); // notifies super admins
            
            logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'STOCK_TRANSFER', "Demande d'expédition de $qty {$item_info['name']} créée.");
            
        } else {
            $error = "Quantité invalide ou stock insuffisant.";
        }
    }
}

// Handle Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_transfer'])) {
    if ($isGlobalView) {
        $error = "Veuillez sélectionner une succursale spécifique pour effectuer une demande.";
    } else {
        $item_data = explode('|', $_POST['item_data']);
        if (count($item_data) === 2) {
            $item_id = $item_data[0];
            $from_branch = $item_data[1];
            $qty = (int)$_POST['quantity'];
            
            $check_q = $pdo->prepare("SELECT quantity_total, name FROM items WHERE id = ? AND branch_id = ?");
            $check_q->execute([$item_id, $from_branch]);
            $item_info = $check_q->fetch();
            
            if ($item_info && $item_info['quantity_total'] >= $qty && $qty > 0) {
                $stmt = $pdo->prepare("INSERT INTO stock_transfers (item_id_from, from_branch_id, to_branch_id, quantity, requested_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $from_branch, $current_branch_id, $qty, $_SESSION['user_id']]);
                $msg = "Demande de stock envoyée ! En attente d'approbation par la direction.";
                
                require_once '../includes/notifications.php';
                notifyBranch(null, "Demande de Stock", "Une agence a demandé $qty " . $item_info['name'] . " depuis une autre succursale.", "system");
                
                logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'STOCK_TRANSFER', "Demande de réception de $qty {$item_info['name']} envoyée.");
            } else {
                $error = "Quantité invalide ou stock insuffisant dans l'agence source.";
            }
        } else {
            $error = "Données d'article invalides.";
        }
    }
}

// Fetch lists for forms and UI
$where_t = getBranchSqlFilter() ? "WHERE t.from_branch_id = '" . $current_branch_id . "' OR t.to_branch_id = '" . $current_branch_id . "'" : "";

$transfers = $pdo->query("SELECT t.*, i.name as item_name, bf.name as from_name, bt.name as to_name, u.name as requestor 
                          FROM stock_transfers t 
                          JOIN items i ON t.item_id_from = i.id 
                          JOIN branches bf ON t.from_branch_id = bf.id 
                          JOIN branches bt ON t.to_branch_id = bt.id 
                          LEFT JOIN users u ON t.requested_by = u.id 
                          $where_t ORDER BY t.created_at DESC")->fetchAll();

$branches = [];
$items = [];
$other_items = [];
if (!$isGlobalView) {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE id != '$current_branch_id'")->fetchAll();
    $items = $pdo->query("SELECT id, name, quantity_total FROM items WHERE branch_id = '$current_branch_id' AND quantity_total > 0")->fetchAll();
    $other_items = $pdo->query("SELECT i.id, i.name, i.quantity_total, b.name as branch_name, i.branch_id FROM items i JOIN branches b ON i.branch_id = b.id WHERE i.branch_id != '$current_branch_id' AND i.quantity_total > 0 ORDER BY b.name, i.name")->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transferts de Stock - Sam Event</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=9">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <?php if (hasRole('super_admin')): ?><a href="analytics.php"><i class="fas fa-chart-pie"></i> &nbsp; Analytiques</a><?php endif; ?>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="returns.php"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="transfers.php" class="active"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="branches.php"><i class="fas fa-building"></i> &nbsp; Succursales</a>
        <?php endif; ?>
        <?php if (hasRole('super_admin') || hasRole('mini_admin')): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>
            <a href="logs.php"><i class="fas fa-history"></i> &nbsp; Journal d'Activité</a>
        <?php endif; ?>
        <?php if (hasRole('super_admin')): ?>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0; display:flex; align-items:center; gap:10px;"><i class="fas fa-truck-loading" style="color:var(--primary-blue)"></i> Logistique Inter-Succursales</h2>
                <p style="color: #666; margin-top: 5px;">Demandez et aérez le transfert de matériel entre différentes boutiques.</p>
            </div>
            <?php if (!$isGlobalView): ?>
                <div style="display: flex; gap: 10px;">
                    <button class="contact-btn" style="background: #10b981;" onclick="document.getElementById('requestModal').style.display='flex'">+ Demander Stock</button>
                    <button class="contact-btn" onclick="document.getElementById('transferModal').style.display='flex'">+ Expédier Stock</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($msg): ?>
            <div style="background: #10b981; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #ef4444; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($isGlobalView): ?>
            <div style="background: #eef2ff; color: #312e81; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;">
                <i class="fas fa-info-circle"></i> Vous êtes en vision Super Admin globale. Pour initier un nouveau transfert de stock, veuillez sélectionner une succursale Expéditrice via le panneau de votre "Dashboard".
            </div>
        <?php endif; ?>

        <div class="card">
            <h3 style="margin-top:0; margin-bottom: 20px; color:#1e293b;">Historique des Mouvements</h3>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 15px;">Date</th>
                            <th style="padding: 15px;">Article</th>
                            <th style="padding: 15px;">Trajet</th>
                            <th style="padding: 15px;">Intervenant</th>
                            <th style="padding: 15px;">Statut</th>
                            <th style="padding: 15px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $t): ?>
                        <tr id="row_<?php echo $t['id']; ?>" style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 15px; font-size: 0.9rem; color:#64748b;"><?php echo date('d/m/y H:i', strtotime($t['created_at'])); ?></td>
                            <td style="padding: 15px;">
                                <strong><?php echo htmlspecialchars($t['item_name']); ?></strong><br>
                                <span id="qty_<?php echo $t['id']; ?>" style="background: #f1f5f9; padding: 2px 6px; border-radius:4px; font-size:0.8rem;">Qté: <?php echo $t['quantity']; ?></span>
                            </td>
                            <td style="padding: 15px; font-size: 0.9rem;">
                                De: <span style="font-weight:600; color:#ef4444;"><?php echo htmlspecialchars($t['from_name']); ?></span> <br>
                                Vers: <span style="font-weight:600; color:#10b981;"><?php echo htmlspecialchars($t['to_name']); ?></span>
                            </td>
                            <td style="padding: 15px; font-size:0.9rem;"><?php echo htmlspecialchars($t['requestor'] ?? 'Système'); ?></td>
                            <td id="status_<?php echo $t['id']; ?>" style="padding: 15px;">
                                <?php if ($t['status'] == 'approved'): ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">Approuvé</span>
                                <?php elseif ($t['status'] == 'rejected'): ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">Rejeté</span>
                                <?php else: ?>
                                    <span style="background: #fef9c3; color: #854d0e; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">En attente</span>
                                <?php endif; ?>
                            </td>
                            <td id="actions_<?php echo $t['id']; ?>" style="padding: 15px;">
                                <?php if ($t['status'] == 'pending'): ?>
                                    <div style="display: flex; gap: 5px;">
                                    <?php if (hasRole('super_admin') || $t['from_branch_id'] == $current_branch_id): ?>
                                        <button onclick="performAction(<?php echo $t['id']; ?>, 'approve')" class="action-btn edit-btn" style="border:none; cursor:pointer;" title="Approuver"><i class="fas fa-check"></i></button>
                                        <button onclick="performAction(<?php echo $t['id']; ?>, 'reject')" class="action-btn delete-btn" style="border:none; cursor:pointer;" title="Rejeter"><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                    <?php if (hasRole('super_admin') || $t['to_branch_id'] == $current_branch_id || $t['requested_by'] == $_SESSION['user_id']): ?>
                                        <button onclick="editQuantity(<?php echo $t['id']; ?>, <?php echo $t['quantity']; ?>)" class="action-btn view-btn" style="background:#e0e7ff; color:#4338ca; border:none; cursor:pointer;" title="Modifier Quantité"><i class="fas fa-edit"></i></button>
                                        <button onclick="performAction(<?php echo $t['id']; ?>, 'delete')" class="action-btn delete-btn" style="background:#fee2e2; color:#ef4444; border:none; cursor:pointer;" title="Supprimer Demande"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#cbd5e1;"><i class="fas fa-lock"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transfers)): ?>
                        <tr><td colspan="6" style="padding: 20px; text-align:center; color:#94a3b8;">Aucun transfert logistique trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<?php if (!$isGlobalView): ?>
<div id="transferModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0; color:#1e293b;">Exiger un Transfert</h2>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">Expédier du matériel depuis votre succursale vers une autre.</p>
        <form method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Article à expédier</label>
                <select name="item_id" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family:inherit;">
                    <option value="">Sélectionnez un article de votre stock...</option>
                    <?php foreach ($items as $i): ?>
                        <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['name']); ?> (Max: <?php echo $i['quantity_total']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Quantité d'expédition</label>
                <input type="number" name="quantity" min="1" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Agence Destinataire</label>
                <select name="to_branch" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family:inherit;">
                    <option value="">Où expédier le matériel ?</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" name="create_transfer" class="contact-btn" style="flex: 1; border:none; padding:12px; border-radius:8px;">Confirmer l'Expédition</button>
                <button type="button" onclick="document.getElementById('transferModal').style.display='none'" style="flex: 1; padding: 12px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Annuler</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$isGlobalView): ?>
<!-- Modal for Requesting Stock -->
<div id="requestModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0; color:#1e293b;">Demander du Stock</h2>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">Demandez du matériel depuis une autre succursale vers la vôtre.</p>
        <form method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Article souhaité</label>
                <select name="item_data" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family:inherit;">
                    <option value="">Sélectionnez un article...</option>
                    <?php foreach ($other_items as $i): ?>
                        <option value="<?php echo $i['id'] . '|' . $i['branch_id']; ?>"><?php echo htmlspecialchars($i['name']); ?> - <?php echo htmlspecialchars($i['branch_name']); ?> (Dispo: <?php echo $i['quantity_total']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Quantité demandée</label>
                <input type="number" name="quantity" min="1" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px;">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" name="request_transfer" class="contact-btn" style="flex: 1; border:none; padding:12px; border-radius:8px; background: #10b981;">Envoyer la Demande</button>
                <button type="button" onclick="document.getElementById('requestModal').style.display='none'" style="flex: 1; padding: 12px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Annuler</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="../assets/js/admin.js?v=9"></script>
<script>
async function performAction(id, action) {
    let confirmMsg = '';
    if (action === 'approve') confirmMsg = 'Confirmer le mouvement de stock ?';
    else if (action === 'reject') confirmMsg = 'Rejeter ce transfert ?';
    else if (action === 'delete') confirmMsg = 'Supprimer définitivement cette demande ?';

    if (!confirm(confirmMsg)) return;

    try {
        const res = await fetch('transfers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ ajax_action: action, id: id })
        });
        const data = await res.json();
        
        if (data.success) {
            // Update UI dynamically
            if (action === 'delete') {
                const row = document.getElementById('row_' + id);
                if (row) row.remove();
            } else if (action === 'approve' || action === 'reject') {
                const statusCell = document.getElementById('status_' + id);
                if (action === 'approve') {
                    statusCell.innerHTML = '<span style="background: #dcfce7; color: #166534; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">Approuvé</span>';
                } else {
                    statusCell.innerHTML = '<span style="background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">Rejeté</span>';
                }
                const actionsCell = document.getElementById('actions_' + id);
                actionsCell.innerHTML = '<span style="color:#cbd5e1;"><i class="fas fa-lock"></i></span>';
            }
            alert(data.msg);
        } else {
            alert('Erreur: ' + data.error);
        }
    } catch (err) {
        alert('Erreur de réseau.');
        console.error(err);
    }
}

async function editQuantity(id, currentQty) {
    const newQtyStr = prompt("Entrez la nouvelle quantité :", currentQty);
    if (newQtyStr === null) return;
    
    const newQty = parseInt(newQtyStr);
    if (isNaN(newQty) || newQty <= 0) {
        alert("Quantité invalide.");
        return;
    }
    
    if (newQty === currentQty) return;

    try {
        const res = await fetch('transfers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ ajax_action: 'edit', id: id, quantity: newQty })
        });
        const data = await res.json();
        
        if (data.success) {
            document.getElementById('qty_' + id).innerText = 'Qté: ' + data.new_qty;
            // Update the button's onclick to reflect new current quantity
            const actionsCell = document.getElementById('actions_' + id);
            const viewBtn = actionsCell.querySelector('.view-btn');
            if (viewBtn) {
                viewBtn.setAttribute('onclick', `editQuantity(${id}, ${data.new_qty})`);
            }
        } else {
            alert('Erreur: ' + data.error);
        }
    } catch (err) {
        alert('Erreur de réseau.');
        console.error(err);
    }
}
</script>
</body>
</html>
