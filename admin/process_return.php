<?php
// admin/process_return.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/engine.php';
requireAdmin();

$reservation_id = $_GET['id'] ?? ($_POST['reservation_id'] ?? null);
if (!$reservation_id) {
    die("ID de réservation manquant.");
}

// Fetch reservation
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    die("Réservation introuvable.");
}

// Fetch items
$stmt = $pdo->prepare("
    SELECT ri.*, i.name, i.price_per_day, i.quantity_total 
    FROM reservation_items ri 
    JOIN items i ON ri.item_id = i.id 
    WHERE ri.reservation_id = ?
");
$stmt->execute([$reservation_id]);
$items = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = $_POST['notes'] ?? '';
    $returned_qty = $_POST['qty_returned'] ?? [];
    $damaged_qty = $_POST['qty_damaged'] ?? [];
    $missing_qty = $_POST['qty_missing'] ?? [];
    $penalty_amounts = $_POST['penalty_amount'] ?? [];
    
    $total_penalty = 0;
    $status = 'complete';
    
    try {
        $pdo->beginTransaction();
        
        // Create Return Record
        $stmt = $pdo->prepare("INSERT INTO returns (reservation_id, checked_by, notes) VALUES (?, ?, ?)");
        $stmt->execute([$reservation_id, $_SESSION['user_id'], $notes]);
        $return_id = $pdo->lastInsertId();
        
        foreach ($items as $item) {
            $id = $item['item_id'];
            $expected = $item['quantity'];
            $returned = (int)($returned_qty[$id] ?? 0);
            $damaged = (int)($damaged_qty[$id] ?? 0);
            $missing = (int)($missing_qty[$id] ?? 0);
            
            // Validate math
            if ($returned + $damaged + $missing != $expected) {
                // If they don't add up, adjust missing
                $missing = max(0, $expected - $returned - $damaged);
            }
            
            if ($returned < $expected) {
                $status = 'partial';
            }
            
            $penalty = (float)($penalty_amounts[$id] ?? 0);
            $total_penalty += $penalty;
            
            // Insert return item
            $stmt_ret_item = $pdo->prepare("INSERT INTO return_items (return_id, item_id, qty_expected, qty_returned, qty_damaged, qty_missing, penalty_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_ret_item->execute([$return_id, $id, $expected, $returned, $damaged, $missing, $penalty]);
            
            // Handle Damaged (Add to maintenance)
            if ($damaged > 0) {
                updateItemMaintenance($id, $damaged, 'mark_damaged');
                logStockMovement($id, $reservation_id, 0, "Retour matériel: $damaged endommagé(s) mis en maintenance.");
            }
            
            // Handle Missing (Permanently remove from total stock)
            if ($missing > 0) {
                $stmt_upd = $pdo->prepare("UPDATE items SET quantity_total = GREATEST(0, quantity_total - ?) WHERE id = ?");
                $stmt_upd->execute([$missing, $id]);
                logStockMovement($id, $reservation_id, -$missing, "Retour matériel: $missing manquant(s) déduit(s) du stock total.");
            }
        }
        
        // Update Return status and penalty
        $stmt_update_ret = $pdo->prepare("UPDATE returns SET status = ?, penalty_total = ? WHERE id = ?");
        $stmt_update_ret->execute([$status, $total_penalty, $return_id]);
        
        // Update Reservation Status
        $stmt_res = $pdo->prepare("UPDATE reservations SET status = 'returned' WHERE id = ?");
        $stmt_res->execute([$reservation_id]);
        
        $pdo->commit();
        
        logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'PROCESS_RETURN', "Retour traité pour la réservation #$reservation_id. Pénalité: $total_penalty.");
        
        header("Location: returns.php?msg=success");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors du traitement: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traiter Retour - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0;">Traiter Retour #<?php echo htmlspecialchars($reservation_id); ?></h2>
                <p style="color: #666; margin-top: 5px;">Client: <?php echo htmlspecialchars($reservation['customer_name']); ?> (<?php echo htmlspecialchars($reservation['customer_phone']); ?>)</p>
            </div>
            <a href="returns.php" class="contact-btn" style="padding: 10px 20px; text-decoration: none; background: #888;">← Retour</a>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="card" id="returnForm">
            <input type="hidden" name="reservation_id" value="<?php echo htmlspecialchars($reservation_id); ?>">
            
            <div class="table-responsive" style="margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th style="text-align: center;">Attendu</th>
                            <th style="text-align: center;">Retourné Intact</th>
                            <th style="text-align: center;">Endommagé</th>
                            <th style="text-align: center;">Manquant</th>
                            <th style="text-align: right;">Pénalité (<?php echo getCurrency(); ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                    <small>Prix unitaire: <?php echo number_format($item['price_per_day'], 0); ?> <?php echo getCurrency(); ?></small>
                                </td>
                                <td style="text-align: center; font-size: 1.2rem; font-weight: bold; color: var(--primary-blue);">
                                    <?php echo $item['quantity']; ?>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" name="qty_returned[<?php echo $item['item_id']; ?>]" 
                                           class="form-control qty-returned" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           data-expected="<?php echo $item['quantity']; ?>"
                                           value="<?php echo $item['quantity']; ?>" min="0" max="<?php echo $item['quantity']; ?>" style="width: 80px; text-align: center;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" name="qty_damaged[<?php echo $item['item_id']; ?>]" 
                                           class="form-control qty-damaged" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           value="0" min="0" max="<?php echo $item['quantity']; ?>" style="width: 80px; text-align: center; color: #d97706;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" name="qty_missing[<?php echo $item['item_id']; ?>]" 
                                           class="form-control qty-missing" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           value="0" min="0" max="<?php echo $item['quantity']; ?>" style="width: 80px; text-align: center; color: #ef4444;" readonly>
                                </td>
                                <td style="text-align: right;">
                                    <input type="number" name="penalty_amount[<?php echo $item['item_id']; ?>]" 
                                           class="form-control penalty-amount" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           data-price="<?php echo $item['price_per_day']; ?>" 
                                           value="0" min="0" style="width: 100px; text-align: right; color: #ef4444; font-weight: bold;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; font-weight: 800; font-size: 1.2rem;">TOTAL PÉNALITÉS :</td>
                            <td style="text-align: right; font-weight: 800; font-size: 1.4rem; color: #ef4444;" id="totalPenalty">
                                0 <?php echo getCurrency(); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Notes d'inspection (Optionnel)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Ex: Traces de rayures sur la chaise 3, le client s'est excusé..."></textarea>
            </div>
            
            <button type="submit" class="contact-btn" style="width: 100%; border: none; padding: 15px; font-size: 1.1rem; cursor: pointer;">
                <i class="fas fa-check-circle"></i> Confirmer le Retour & Mettre à jour les stocks
            </button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const calculateRow = (id) => {
            const expected = parseInt(document.querySelector(`.qty-returned[data-id="${id}"]`).dataset.expected);
            let returned = parseInt(document.querySelector(`.qty-returned[data-id="${id}"]`).value) || 0;
            let damaged = parseInt(document.querySelector(`.qty-damaged[data-id="${id}"]`).value) || 0;
            
            if (returned + damaged > expected) {
                if (event.target.classList.contains('qty-damaged')) {
                    returned = expected - damaged;
                    document.querySelector(`.qty-returned[data-id="${id}"]`).value = returned;
                } else {
                    damaged = expected - returned;
                    document.querySelector(`.qty-damaged[data-id="${id}"]`).value = damaged;
                }
            }
            
            const missing = expected - returned - damaged;
            document.querySelector(`.qty-missing[data-id="${id}"]`).value = missing;
            
            const penaltyInput = document.querySelector(`.penalty-amount[data-id="${id}"]`);
            const price = parseFloat(penaltyInput.dataset.price);
            
            if (!penaltyInput.dataset.manuallyEdited) {
                const penalty = (damaged + missing) * price;
                penaltyInput.value = penalty;
            }
            
            updateTotalPenalty();
        };

        const updateTotalPenalty = () => {
            let total = 0;
            document.querySelectorAll('.penalty-amount').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('totalPenalty').innerText = total.toLocaleString() + ' <?php echo getCurrency(); ?>';
        };

        document.querySelectorAll('.qty-returned, .qty-damaged').forEach(input => {
            input.addEventListener('input', (e) => {
                const id = e.target.dataset.id;
                calculateRow(id);
            });
        });

        document.querySelectorAll('.penalty-amount').forEach(input => {
            input.addEventListener('input', () => {
                input.dataset.manuallyEdited = "true";
                updateTotalPenalty();
            });
        });
    });
</script>
</body>
</html>
