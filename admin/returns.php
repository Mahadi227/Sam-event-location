<?php
// admin/returns.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Active branch handling for Super Admin
if (hasRole('super_admin') && isset($_GET['branch'])) {
    if ($_GET['branch'] === 'all') {
        unset($_SESSION['active_branch']);
    } else {
        $_SESSION['active_branch'] = (int)$_GET['branch'];
    }
}
$active_branch = hasRole('super_admin') ? ($_SESSION['active_branch'] ?? null) : $_SESSION['branch_id'];
$branchSql = getBranchSqlFilter('r');

// Fetch branches for filter
$branches = [];
if (hasRole('super_admin')) {
    $branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_penalty') {
    $r_id = $_POST['return_id'];
    $res_id = $_POST['reservation_id'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'];
    $ref = $_POST['transaction_ref'];
    $user_id = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        $pdo->prepare("INSERT INTO payments (reservation_id, amount, payment_method, transaction_ref, processed_by, payment_type) VALUES (?, ?, ?, ?, ?, 'penalty')")->execute([$res_id, $amount, $method, $ref, $user_id]);
        
        $pdo->prepare("UPDATE returns SET penalty_paid = (SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id = ? AND payment_type = 'penalty') WHERE id = ?")->execute([$res_id, $r_id]);
        
        logActivity($user_id, $_SESSION['branch_id'] ?? null, 'PAY_PENALTY', "Encaissement pénalité de $amount pour le retour #$r_id (Réservation #$res_id).");
        
        $pdo->commit();
        header("Location: returns.php?msg=paid");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur: " . $e->getMessage());
    }
}

$msg = $_GET['msg'] ?? '';

if (isset($_GET['delete_return']) && hasRole('super_admin')) {
    $del_id = (int)$_GET['delete_return'];
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT reservation_id FROM returns WHERE id = ?");
        $stmt->execute([$del_id]);
        $ret = $stmt->fetch();
        
        if ($ret) {
            $res_id = $ret['reservation_id'];
            
            $stmt_items = $pdo->prepare("SELECT * FROM return_items WHERE return_id = ?");
            $stmt_items->execute([$del_id]);
            $r_items = $stmt_items->fetchAll();
            
            foreach ($r_items as $item) {
                if ($item['qty_damaged'] > 0) {
                    $pdo->prepare("UPDATE items SET quantity_maintenance = GREATEST(0, quantity_maintenance - ?) WHERE id = ?")->execute([$item['qty_damaged'], $item['item_id']]);
                    $pdo->prepare("INSERT INTO stock_log (item_id, reservation_id, change_qty, reason) VALUES (?, ?, ?, ?)")->execute([$item['item_id'], $res_id, 0, "Annulation retour: retrait de " . $item['qty_damaged'] . " article(s) de la maintenance."]);
                }
                if ($item['qty_missing'] > 0) {
                    $pdo->prepare("UPDATE items SET quantity_total = quantity_total + ? WHERE id = ?")->execute([$item['qty_missing'], $item['item_id']]);
                    $pdo->prepare("INSERT INTO stock_log (item_id, reservation_id, change_qty, reason) VALUES (?, ?, ?, ?)")->execute([$item['item_id'], $res_id, $item['qty_missing'], "Annulation retour: restitution de " . $item['qty_missing'] . " article(s) manquant(s)."]);
                }
            }
            
            $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?")->execute([$res_id]);
            $pdo->prepare("DELETE FROM returns WHERE id = ?")->execute([$del_id]);
            
            logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'DELETE_RETURN', "Retour #$del_id supprimé. Stocks et statut restaurés.");
        }
        
        $pdo->commit();
        header("Location: returns.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de la suppression : " . $e->getMessage());
    }
}

// Pending Returns: Reservations that are active and event_date + duration is <= today
$today = date('Y-m-d');
$pending_query = "
    SELECT r.*, DATEDIFF('$today', DATE_ADD(r.event_date, INTERVAL r.duration_days DAY)) as days_late 
    FROM reservations r 
    WHERE r.status IN ('approved', 'in_preparation') 
    AND DATE_ADD(r.event_date, INTERVAL r.duration_days DAY) <= ? 
    $branchSql 
    ORDER BY r.event_date ASC
";
$stmt = $pdo->prepare($pending_query);
$stmt->execute([$today]);
$pending_returns = $stmt->fetchAll();

// Completed Returns
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->query("SELECT COUNT(*) FROM returns ret JOIN reservations r ON ret.reservation_id = r.id WHERE 1=1 $branchSql");
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$completed_query = "
    SELECT ret.*, r.customer_name, r.customer_phone, u.name as checked_by_name 
    FROM returns ret 
    JOIN reservations r ON ret.reservation_id = r.id 
    LEFT JOIN users u ON ret.checked_by = u.id 
    WHERE 1=1 $branchSql 
    ORDER BY ret.returned_date DESC 
    LIMIT $limit OFFSET $offset
";
$completed_returns = $pdo->query($completed_query)->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retours & Dommages - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .late-badge {
            background: #fef2f2;
            color: #ef4444;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="returns.php" class="active"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="transfers.php"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="branches.php"><i class="fas fa-building"></i> &nbsp; Branches</a>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0;">Gestion des Retours</h2>
                <p style="color: #666; margin-top: 5px;">Vérifiez le matériel retourné et appliquez les pénalités si nécessaire.</p>
            </div>
        </div>

        <?php if (hasRole('super_admin')): ?>
        <div class="card" style="margin-bottom: 20px; padding: 15px;">
            <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                <label style="font-weight: 600;">Filtrer par Succursale:</label>
                <select name="branch" class="form-control" style="width: auto;" onchange="this.form.submit()">
                    <option value="all">Toutes les succursales</option>
                    <?php foreach($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo (isset($_GET['branch']) && $_GET['branch'] == $b['id']) || (isset($_SESSION['active_branch']) && $_SESSION['active_branch'] == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($msg === 'success'): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> L'action a été enregistrée avec succès et le stock a été mis à jour.
            </div>
        <?php endif; ?>
        <?php if ($msg === 'deleted'): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> Le retour a été supprimé et les stocks correspondants ont été restaurés.
            </div>
        <?php endif; ?>
        <?php if ($msg === 'paid'): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> Le paiement de la pénalité a été encaissé avec succès.
            </div>
        <?php endif; ?>

        <h3 style="color: #1e293b; margin-bottom: 15px;">Retours Attendus</h3>
        <div class="card" style="margin-bottom: 30px;">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID Rés.</th>
                            <th>Client</th>
                            <th>Date de Fin Prévue</th>
                            <th>Retard</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_returns)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #666;">Aucun retour en attente.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_returns as $r): ?>
                                <?php 
                                    $end_date = date('Y-m-d', strtotime($r['event_date'] . " + " . ($r['duration_days'] ?? 1) . " days")); 
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $r['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['customer_name']); ?><br><small><?php echo htmlspecialchars($r['customer_phone']); ?></small></td>
                                    <td><?php echo date('d/m/Y', strtotime($end_date)); ?></td>
                                    <td>
                                        <?php if ($r['days_late'] > 0): ?>
                                            <span class="late-badge"><?php echo $r['days_late']; ?> jour(s) de retard</span>
                                        <?php else: ?>
                                            <span style="color: #10b981;">Aujourd'hui</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="process_return.php?id=<?php echo $r['id']; ?>" class="contact-btn" style="padding: 5px 15px; text-decoration: none; display: inline-block;">Traiter le Retour</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h3 style="color: #1e293b; margin-bottom: 15px;">Historique des Retours</h3>
        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID Retour</th>
                            <th>Client (Rés.)</th>
                            <th>Date Retour</th>
                            <th>Statut</th>
                            <th>Vérifié par</th>
                            <th>Pénalité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($completed_returns)): ?>
                            <tr><td colspan="7" style="text-align: center; color: #666;">Aucun historique de retour.</td></tr>
                        <?php else: ?>
                            <?php foreach ($completed_returns as $ret): ?>
                                <tr>
                                    <td>#<?php echo $ret['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ret['customer_name']); ?> <br><small><a href="manage.php?id=<?php echo $ret['reservation_id']; ?>">Rés. #<?php echo $ret['reservation_id']; ?></a></small></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ret['returned_date'])); ?></td>
                                    <td>
                                        <?php if ($ret['status'] === 'complete'): ?>
                                            <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">Complet</span>
                                        <?php else: ?>
                                            <span style="background: #fef3c7; color: #d97706; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">Incomplet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ret['checked_by_name'] ?? 'Inconnu'); ?></td>
                                    <td>
                                        <?php if ($ret['penalty_total'] > 0): ?>
                                            <div style="font-size: 0.8rem;">
                                                <strong style="color: #ef4444;">Total: <?php echo number_format($ret['penalty_total'], 0); ?> <?php echo getCurrency(); ?></strong><br>
                                                <span style="color: #166534;">Payé: <?php echo number_format($ret['penalty_paid'], 0); ?> <?php echo getCurrency(); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #666;">Aucune</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <?php if ($ret['penalty_total'] > $ret['penalty_paid']): ?>
                                            <button onclick='openPenaltyModal(<?php echo $ret['id']; ?>, <?php echo $ret['reservation_id']; ?>, <?php echo $ret['penalty_total'] - $ret['penalty_paid']; ?>)' title="Encaisser la pénalité" class="contact-btn" style="padding: 5px 10px; font-size: 0.8rem; border: none; cursor: pointer; background: #10b981;"><i class="fas fa-hand-holding-usd"></i> Encaisser</button>
                                        <?php endif; ?>
                                        <a href="edit_return.php?id=<?php echo $ret['id']; ?>" title="Modifier ce retour" style="color: #6366f1; font-size: 1.1rem;"><i class="fas fa-edit"></i></a>
                                        <?php if (hasRole('super_admin')): ?>
                                        <a href="?delete_return=<?php echo $ret['id']; ?>" onclick="return confirm('Attention: Confirmez-vous la suppression de ce retour ? Les articles manquants ou endommagés seront restaurés dans les stocks originaux et le statut de la réservation sera annulé.')" title="Supprimer" style="color: #ef4444; font-size: 1.1rem;"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                        <a href="print_penalty.php?id=<?php echo $ret['id']; ?>" target="_blank" title="Imprimer Facture / Pénalité" style="color: #4338ca; font-size: 1.1rem;"><i class="fas fa-file-invoice"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination" style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" style="padding: 5px 10px; background: <?php echo $i === $page ? 'var(--primary-blue)' : '#eee'; ?>; color: <?php echo $i === $page ? 'white' : '#333'; ?>; text-decoration: none; border-radius: 5px;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Penalty Payment Modal -->
<div id="penaltyModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 400px; max-width: 90%; position: relative;">
        <h3>Encaisser Pénalité</h3>
        <button type="button" onclick="document.getElementById('penaltyModal').style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="action" value="pay_penalty">
            <input type="hidden" name="return_id" id="modal_return_id">
            <input type="hidden" name="reservation_id" id="modal_reservation_id">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Montant à Encaisser (FCFA)</label>
                <input type="number" name="amount" id="modal_amount" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Méthode de paiement</label>
                <select name="payment_method" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="cash">CASH</option>
                    <option value="orange_money">Orange Money</option>
                    <option value="moov_money">Moov Money</option>
                    <option value="card">Card</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>Référence Transaction (Optionnel)</label>
                <input type="text" name="transaction_ref" placeholder="Ex: OM123456" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            
            <button type="submit" class="contact-btn" style="width:100%; border:none; padding:15px; cursor: pointer; background: #10b981;">Valider l'Encaissement</button>
        </form>
    </div>
</div>

<script>
    document.querySelector('.admin-hamburger').addEventListener('click', () => {
        document.querySelector('.admin-sidebar').classList.toggle('active');
    });

    function openPenaltyModal(return_id, reservation_id, amount_due) {
        document.getElementById('modal_return_id').value = return_id;
        document.getElementById('modal_reservation_id').value = reservation_id;
        document.getElementById('modal_amount').value = amount_due;
        document.getElementById('penaltyModal').style.display = 'flex';
    }
</script>
</body>
</html>
