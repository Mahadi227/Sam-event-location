<?php
// admin/manage.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/engine.php';
require_once '../includes/notifications.php';
requireStaff();

$id = $_GET['id'] ?? null;
if (!$id) die("ID Manquant");

// Process status update
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $id]);
    
    // Fetch user info for notification
    $stmt = $pdo->prepare("SELECT user_id, customer_name FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    $res_info = $stmt->fetch();
    
    if ($res_info && $res_info['user_id']) {
        $msg_map = [
            'approved' => "Votre réservation a été approuvée ! 🎉",
            'rejected' => "Votre réservation a été rejetée. Veuillez nous contacter.",
            'in_preparation' => "Votre matériel est en cours de préparation.",
            'completed' => "L'événement est terminé. Merci de votre confiance !",
            'cancelled' => "Votre réservation a été annulée."
        ];
        if (isset($msg_map[$new_status])) {
            createNotification($res_info['user_id'], "Mise à jour du statut", $msg_map[$new_status], "alert", $id);
        }
    }
}

// Process items update
if (isset($_POST['update_items'])) {
    $new_items = $_POST['items'] ?? [];
    
    $processed_items = [];
    foreach ($new_items as $item_id => $qty) {
        $qty = (int)$qty;
        if ($qty > 0) {
            $processed_items[$item_id] = $qty;
        }
    }
    
    $stmt = $pdo->prepare("SELECT duration_days, distance_km, event_date, promo_code_id FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    $res_data = $stmt->fetch();
    
    $promo_str = null;
    if ($res_data['promo_code_id']) {
        $stmt = $pdo->prepare("SELECT code FROM promo_codes WHERE id = ?");
        $stmt->execute([$res_data['promo_code_id']]);
        $promo_str = $stmt->fetchColumn();
    }
    
    $is_weekend = (date('N', strtotime($res_data['event_date'])) >= 6);
    
    $params = [
        'duration_days' => $res_data['duration_days'],
        'distance_km' => $res_data['distance_km'],
        'is_weekend' => $is_weekend,
        'promo_code' => $promo_str
    ];
    
    $pricing = calculateTotalPrice($processed_items, $params);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM reservation_items WHERE reservation_id = ?");
        $stmt->execute([$id]);
        
        foreach ($processed_items as $item_id => $qty) {
            $stmt = $pdo->prepare("SELECT price_per_day FROM items WHERE id = ?");
            $stmt->execute([$item_id]);
            $price_at_time = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO reservation_items (reservation_id, item_id, quantity, price_at_time) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $item_id, $qty, $price_at_time]);
        }
        
        $stmt = $pdo->prepare("UPDATE reservations SET total_price = ?, discount_amount = ?, promo_code_id = ? WHERE id = ?");
        $stmt->execute([$pricing['total'], $pricing['discount'], $pricing['promo_code_id'], $id]);
        
        $pdo->commit();
        header("Location: manage.php?id=" . $id . "&success_items=1");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur mise à jour articles : " . $e->getMessage());
    }
}

// Process info update
if (isset($_POST['update_info'])) {
    $name = $_POST['customer_name'];
    $phone = $_POST['customer_phone'];
    $date = $_POST['event_date'];
    $location = $_POST['event_location'];
    $duration_days = (int)($_POST['duration_days'] ?? 1);
    $distance_km = (int)($_POST['distance_km'] ?? 0);
    
    // We need to recalculate prices because duration/distance/date(weekend) changed
    $stmt = $pdo->prepare("SELECT promo_code_id FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    $res_promo = $stmt->fetchColumn();
    
    $promo_str = null;
    if ($res_promo) {
        $stmt = $pdo->prepare("SELECT code FROM promo_codes WHERE id = ?");
        $stmt->execute([$res_promo]);
        $promo_str = $stmt->fetchColumn();
    }
    
    $stmt = $pdo->prepare("SELECT item_id, quantity FROM reservation_items WHERE reservation_id = ?");
    $stmt->execute([$id]);
    $curr_items_db = $stmt->fetchAll();
    
    $recalc_items = [];
    foreach ($curr_items_db as $item) {
        $recalc_items[$item['item_id']] = $item['quantity'];
    }
    
    $is_weekend = (date('N', strtotime($date)) >= 6);
    
    $params = [
        'duration_days' => $duration_days,
        'distance_km' => $distance_km,
        'is_weekend' => $is_weekend,
        'promo_code' => $promo_str
    ];
    
    $pricing = calculateTotalPrice($recalc_items, $params);
    
    $stmt = $pdo->prepare("UPDATE reservations SET customer_name = ?, customer_phone = ?, event_date = ?, event_location = ?, duration_days = ?, distance_km = ?, total_price = ?, discount_amount = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $date, $location, $duration_days, $distance_km, $pricing['total'], $pricing['discount'], $id]);
    
    header("Location: manage.php?id=" . $id . "&success=1");
    exit;
}

// Process payment
if (isset($_POST['record_payment'])) {
    $amount = $_POST['amount'];
    $method = $_POST['method'];
    $ref = $_POST['ref'] ?? '';
    
    $pdo->beginTransaction();
    try {
        $staff_id = $_SESSION['user_id'] ?? null;
        
        // Record payment transaction
        $stmt = $pdo->prepare("INSERT INTO payments (reservation_id, amount, payment_method, transaction_ref, processed_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id, $amount, $method, $ref, $staff_id]);
        
        // Update reservation total paid
        $stmt = $pdo->prepare("UPDATE reservations SET amount_paid = amount_paid + ? WHERE id = ?");
        $stmt->execute([$amount, $id]);
        
        $pdo->commit();
        
        // Notification pour le Client
        $stmt = $pdo->prepare("SELECT user_id, customer_name FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $resInfo = $stmt->fetch();
        if ($resInfo && $resInfo['user_id']) {
            createNotification($resInfo['user_id'], "Paiement Reçu", "Nous avons bien reçu votre paiement de " . number_format($amount, 0) . " F.", "payment", $id);
        }
        
        // Notification pour le Staff
        $staff_name = $_SESSION['name'] ?? 'Reception';
        $processor_id = $_SESSION['user_id'] ?? null;
        notifyPaymentProcessed("Paiement Encaissé", "Paiement de " . number_format($amount, 0) . " F par $staff_name (Ref Réservation: #$id - " . ($resInfo['customer_name'] ?? 'Client') . ").", $id, $processor_id);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur paiement : " . $e->getMessage());
    }
}

// Get reservation info
$branchFilter = getBranchSqlFilter('r');
$stmt = $pdo->prepare("
    SELECT r.*, pc.code as promo_code_name 
    FROM reservations r 
    LEFT JOIN promo_codes pc ON r.promo_code_id = pc.id 
    WHERE r.id = ? $branchFilter
");
$stmt->execute([$id]);
$res = $stmt->fetch();

if (!$res) {
    die("Réservation introuvable ou accès refusé.");
}

// Get items
$stmt = $pdo->prepare("SELECT ri.*, i.name as item_name, i.image_url FROM reservation_items ri JOIN items i ON ri.item_id = i.id WHERE ri.reservation_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("SELECT * FROM payments WHERE reservation_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

// Get all active items grouped
$branchFilterItems = getBranchSqlFilter('i');
$stmt = $pdo->query("SELECT c.name as cat_name, i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.status = 'available' $branchFilterItems");
$all_items_catalog = [];
while ($row = $stmt->fetch()) {
    $all_items_catalog[$row['cat_name']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer la Réservation #<?php echo $id; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=7">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Reception</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <h2 style="color: white; margin-bottom: 30px;">Reception Sam</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> &nbsp; Accueil</a>
        <a href="walk_in.php"><i class="fas fa-plus"></i> &nbsp; Nouveau Walk-in</a>
        <a href="reservations.php" class="active"><i class="fas fa-list"></i> &nbsp; Reservations</a>
        <a href="calendar.php"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse (Shift)</a>
        <a href="profile.php"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2>Détails Réservation #<?php echo $id; ?></h2>
        <a href="../client/invoice.php?id=<?php echo $id; ?>" target="_blank" class="contact-btn" style="background: #444;"><i class="fas fa-print"></i> Imprimer Facture</a>
    </div>

    <div class="manage-grid">
        <div class="left-col">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin: 0;">Infos Client & Événement</h3>
                    <button type="button" onclick="document.getElementById('editInfoForm').style.display='block'; document.getElementById('viewInfo').style.display='none';" class="contact-btn" style="padding: 5px 15px; font-size: 0.85rem; background: #6366f1; border: none; cursor: pointer;"><i class="fas fa-edit"></i> Modifier</button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-top: 15px; font-size: 0.9rem; font-weight: 600;">
                        Informations mises à jour ✅
                    </div>
                <?php endif; ?>

                <div id="viewInfo" style="display: <?php echo isset($_GET['edit']) ? 'none' : 'grid'; ?>; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <p style="color: #666; font-size: 0.85rem;">Client</p>
                        <p><strong><?php echo htmlspecialchars($res['customer_name']); ?></strong></p>
                        <p><?php echo htmlspecialchars($res['customer_phone']); ?></p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 0.85rem;">Date, Durée & Lieu</p>
                        <p><strong><?php echo date('d/m/Y', strtotime($res['event_date'])); ?></strong> (<?php echo $res['duration_days']; ?>j)</p>
                        <p><?php echo htmlspecialchars($res['event_location']); ?> <?php if($res['distance_km']) echo '- '.$res['distance_km'].' km'; ?></p>
                    </div>
                </div>

                <form id="editInfoForm" method="POST" style="display: <?php echo isset($_GET['edit']) ? 'block' : 'none'; ?>; margin-top: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Nom du Client</label>
                            <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($res['customer_name']); ?>" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Téléphone</label>
                            <input type="text" name="customer_phone" class="form-control" value="<?php echo htmlspecialchars($res['customer_phone']); ?>" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Date</label>
                            <input type="date" name="event_date" class="form-control" value="<?php echo htmlspecialchars($res['event_date']); ?>" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Durée (Jours)</label>
                            <input type="number" name="duration_days" class="form-control" value="<?php echo $res['duration_days']; ?>" required min="1" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Lieu</label>
                            <input type="text" name="event_location" class="form-control" value="<?php echo htmlspecialchars($res['event_location']); ?>" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Distance (KM)</label>
                            <input type="number" name="distance_km" class="form-control" value="<?php echo $res['distance_km']; ?>" min="0" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                    </div>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" name="update_info" class="contact-btn" style="border:none; padding: 12px 15px; cursor: pointer; flex: 1;">Enregistrer les modifs</button>
                        <button type="button" onclick="document.getElementById('editInfoForm').style.display='none'; document.getElementById('viewInfo').style.display='grid';" style="padding: 12px 15px; background: #e5e7eb; border: none; border-radius: 8px; cursor: pointer; color: #374151;">Annuler</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin: 0;">Articles</h3>
                    <button type="button" id="openEditItemsBtn" onclick="document.getElementById('editItemsForm').style.display='block'; document.getElementById('viewItems').style.display='none'; updateLiveEditSummary();" class="contact-btn" style="padding: 5px 15px; font-size: 0.85rem; background: #6366f1; border: none; cursor: pointer;"><i class="fas fa-edit"></i> Modifier Articles</button>
                </div>

                <?php if (isset($_GET['success_items'])): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-top: 15px; font-size: 0.9rem; font-weight: 600;">
                        Articles mis à jour avec succès ✅
                    </div>
                <?php endif; ?>

                <div id="viewItems">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <tr style="text-align: left; color: #666; font-size: 0.85rem; border-bottom: 1px solid #eee;">
                            <th style="padding: 10px;">Item</th>
                            <th style="padding: 10px;">Qté</th>
                            <th style="padding: 10px;">Prix U</th>
                            <th style="padding: 10px;">Total</th>
                        </tr>
                        <?php foreach ($items as $it): ?>
                        <tr style="border-bottom: 1px solid #fafafa;">
                            <td style="padding: 10px; display: flex; align-items: center; gap: 10px;">
                                <?php if (!empty($it['image_url'])): ?>
                                    <img src="../<?php echo htmlspecialchars($it['image_url']); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: #eee; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 0.8rem;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($it['item_name']); ?></span>
                            </td>
                            <td style="padding: 10px;"><?php echo $it['quantity']; ?></td>
                            <td style="padding: 10px;"><?php echo number_format($it['price_at_time'], 0); ?> <?php echo getCurrency(); ?></td>
                            <td style="padding: 10px;"><strong><?php echo number_format($it['price_at_time'] * $it['quantity'], 0); ?> F</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <?php if ($res['discount_amount'] > 0): ?>
                    <div style="text-align: right; margin-top: 15px; font-size: 1rem; color: #15803d; font-weight: 700;">
                        Remise Promo <?php echo $res['promo_code_name'] ? '(<i class="fas fa-tag"></i> ' . htmlspecialchars($res['promo_code_name']) . ')' : ''; ?> : - <?php echo number_format($res['discount_amount'], 0); ?> <?php echo getCurrency(); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div style="text-align: right; margin-top: 10px; font-size: 1.2rem; color: var(--secondary-orange); font-weight: 800;">
                        TOTAL : <?php echo number_format($res['total_price'], 0); ?> <?php echo getCurrency(); ?>
                    </div>
                </div>

                <!-- Formulaire Modif Articles -->
                <form id="editItemsForm" method="POST" style="display:none; margin-top: 20px;">
                    <?php 
                    $curr_qtys = [];
                    foreach ($items as $it) {
                        $curr_qtys[$it['item_id']] = $it['quantity'];
                    }
                    ?>
                    <div style="max-height: 400px; overflow-y: auto; padding-right: 15px;">
                        <?php foreach ($all_items_catalog as $cat => $cat_items): ?>
                            <div class="category-title" style="margin-top: 15px; font-size: 0.9rem; color: #666; border-bottom: 2px solid #eee; padding-bottom: 5px;"><strong><?php echo htmlspecialchars($cat); ?></strong></div>
                            <?php foreach ($cat_items as $it): 
                                $q = $curr_qtys[$it['id']] ?? 0;
                            ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f9f9f9;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if (!empty($it['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($it['image_url']); ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: #eee; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #999;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong style="color: var(--dark-blue); font-size: 0.9rem;"><?php echo htmlspecialchars($it['name']); ?></strong><br>
                                            <small style="color: #666;"><?php echo number_format($it['price_per_day'], 0); ?> F</small>
                                        </div>
                                    </div>
                                    <input type="number" name="items[<?php echo $it['id']; ?>]" class="item-qty-edit" value="<?php echo $q; ?>" min="0" 
                                           data-id="<?php echo $it['id']; ?>" data-price="<?php echo $it['price_per_day']; ?>" data-name="<?php echo htmlspecialchars($it['name']); ?>"
                                           style="width: 60px; text-align: center; padding: 5px; border-radius: 5px; border: 1px solid #ccc;">
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 20px; border: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #64748b; font-size: 0.9rem;">
                            <span>Nouveau Total Estimé :</span>
                            <span id="newTotalPreview" style="font-weight: 800; color: var(--secondary-orange); font-size: 1.2rem;">CALCUL...</span>
                        </div>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin: 0;"><i class="fas fa-info-circle"></i> Le prix tient compte de la durée (<?php echo $res['duration_days']; ?>j), la distance et du code promo utilisé.</p>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" name="update_items" class="contact-btn" style="border:none; padding: 12px 15px; cursor: pointer; flex: 1;">Enregistrer Articles</button>
                        <button type="button" onclick="document.getElementById('editItemsForm').style.display='none'; document.getElementById('viewItems').style.display='block';" style="padding: 12px 15px; background: #e5e7eb; border: none; border-radius: 8px; cursor: pointer; color: #374151;">Annuler</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Historique des paiements</h3>
                <?php if (empty($payments)): ?>
                    <p style="color: #999; margin-top: 15px;">Aucun paiement enregistré.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <?php foreach ($payments as $p): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                            <td style="padding: 10px;"><span class="method-tag"><?php echo strtoupper($p['payment_method']); ?></span></td>
                            <td style="padding: 10px; font-weight: 700; color: #166534;">+ <?php echo number_format($p['amount'], 0); ?> <?php echo getCurrency(); ?></td>
                            <td style="padding: 10px; font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($p['transaction_ref']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-col">
            <div class="card">
                <h3>Statut Actuel</h3>
                <div style="margin: 20px 0;">
                    <span class="status-tag <?php echo $res['status']; ?>"><?php echo strtoupper($res['status']); ?></span>
                </div>
                <form method="POST">
                    <label style="font-size: 0.85rem; color: #666;">Changer Statut</label>
                    <select name="status" class="form-control" style="width: 100%; padding: 10px; margin: 10px 0;">
                        <option value="pending" <?php if ($res['status'] == 'pending') echo 'selected'; ?>>En attente</option>
                        <option value="approved" <?php if ($res['status'] == 'approved') echo 'selected'; ?>>Approuvée</option>
                        <option value="rejected" <?php if ($res['status'] == 'rejected') echo 'selected'; ?>>Rejetée</option>
                        <option value="completed" <?php if ($res['status'] == 'completed') echo 'selected'; ?>>Terminée</option>
                    </select>
                    <button type="submit" name="update_status" class="contact-btn" style="width:100%; border:none; padding:10px;">Mettre à jour</button>
                </form>
            </div>

            <div class="card" style="border-top: 4px solid #10b981;">
                <h3>Enregistrer Paiement</h3>
                <div style="margin: 15px 0;">
                    <div style="font-size: 0.9rem; color: #666;">Reste à payer :</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #991b1b;"><?php echo number_format($res['total_price'] - $res['amount_paid'], 0); ?> <?php echo getCurrency(); ?></div>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label>Montant (F)</label>
                        <input type="number" name="amount" class="form-control" required style="width:100%; padding: 10px; margin-bottom: 10px;">
                    </div>
                    <div class="form-group">
                        <label>Méthode</label>
                        <select name="method" class="form-control" style="width:100%; padding: 10px; margin-bottom: 10px;">
                            <option value="cash">Espèces</option>
                            <option value="orange_money">Orange Money</option>
                            <option value="MyNiTa">MyNiTa</option>
                            <option value="AmanaTa">AmanaTa</option>
                            <option value="moov_money">Moov Money</option>
                            <option value="card">Carte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Référence (Facultatif)</label>
                        <input type="text" name="ref" class="form-control" placeholder="ID Transaction / N° Reçu" style="width:100%; padding: 10px; margin-bottom: 15px;">
                    </div>
                    <button type="submit" name="record_payment" class="btn-reserve" style="width: 100%; border: none; padding: 15px; background: #10b981;">Valider le paiement</button>
                </form>
            </div>
            
            <?php if ($res['payment_proof']): ?>
            <div class="card">
                <h3>Preuve Online Client</h3>
                <a href="../uploads/proofs/<?php echo $res['payment_proof']; ?>" target="_blank">
                    <img src="../uploads/proofs/<?php echo $res['payment_proof']; ?>" style="width: 100%; border-radius: 10px; margin-top: 10px;">
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script src="../assets/js/admin.js?v=7"></script>

<script>
    const resData = {
        duration: <?php echo $res['duration_days']; ?>,
        distance: <?php echo $res['distance_km']; ?>,
        is_weekend: <?php echo (date('N', strtotime($res['event_date'])) >= 6) ? 'true' : 'false'; ?>,
        promo: "<?php echo htmlspecialchars($res['promo_code_name'] ?? ''); ?>"
    };

    async function updateLiveEditSummary() {
        const items = {};
        document.querySelectorAll('.item-qty-edit').forEach(i => {
            if (i.value > 0) items[i.dataset.id] = i.value;
        });

        const data = {
            items: items,
            duration: resData.duration,
            distance: resData.distance,
            is_weekend: resData.is_weekend === true || resData.is_weekend === 'true',
            promo: resData.promo
        };

        try {
            const response = await fetch('../api/calculate_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            document.getElementById('newTotalPreview').innerText = (result.total || 0).toLocaleString() + ' <?php echo getCurrency(); ?>';
        } catch(e) {
            console.error('Erreur API:', e);
            document.getElementById('newTotalPreview').innerText = 'Erreur';
        }
    }

    document.querySelectorAll('.item-qty-edit').forEach(i => {
        i.addEventListener('input', updateLiveEditSummary);
        i.addEventListener('change', updateLiveEditSummary);
    });
</script>

</body>
</html>

