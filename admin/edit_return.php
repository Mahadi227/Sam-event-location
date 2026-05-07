<?php
// admin/edit_return.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/engine.php';
requireAdmin();

$return_id = $_GET['id'] ?? ($_POST['return_id'] ?? null);
if (!$return_id) {
    die("ID de retour manquant.");
}

// Fetch return
$stmt = $pdo->prepare("SELECT ret.*, r.customer_name, r.customer_phone FROM returns ret JOIN reservations r ON ret.reservation_id = r.id WHERE ret.id = ?");
$stmt->execute([$return_id]);
$return_data = $stmt->fetch();

if (!$return_data) {
    die("Retour introuvable.");
}

$reservation_id = $return_data['reservation_id'];

// Fetch items from return_items
$stmt = $pdo->prepare("
    SELECT ri.*, i.name, i.price_per_day, i.quantity_total 
    FROM return_items ri 
    JOIN items i ON ri.item_id = i.id 
    WHERE ri.return_id = ?
");
$stmt->execute([$return_id]);
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
        
        foreach ($items as $item) {
            $id = $item['item_id'];
            $expected = $item['qty_expected'];
            
            $old_returned = $item['qty_returned'];
            $old_damaged = $item['qty_damaged'];
            $old_missing = $item['qty_missing'];
            
            $new_returned = (int)($returned_qty[$id] ?? 0);
            $new_damaged = (int)($damaged_qty[$id] ?? 0);
            $new_missing = (int)($missing_qty[$id] ?? 0);
            
            // Validate math
            if ($new_returned + $new_damaged + $new_missing != $expected) {
                // If they don't add up, adjust missing
                $new_missing = max(0, $expected - $new_returned - $new_damaged);
            }
            
            if ($new_returned < $expected) {
                $status = 'partial';
            }
            
            $penalty = (float)($penalty_amounts[$id] ?? 0);
            $total_penalty += $penalty;
            
            // Update return item
            $stmt_ret_item = $pdo->prepare("UPDATE return_items SET qty_returned = ?, qty_damaged = ?, qty_missing = ?, penalty_amount = ? WHERE id = ?");
            $stmt_ret_item->execute([$new_returned, $new_damaged, $new_missing, $penalty, $item['id']]);
            
            // Handle Stock Diff
            $damaged_diff = $new_damaged - $old_damaged;
            if ($damaged_diff > 0) {
                updateItemMaintenance($id, $damaged_diff, 'mark_damaged');
                logStockMovement($id, $reservation_id, 0, "Modification retour: $damaged_diff endommagé(s) mis en maintenance.");
            } elseif ($damaged_diff < 0) {
                updateItemMaintenance($id, abs($damaged_diff), 'restore');
                logStockMovement($id, $reservation_id, 0, "Modification retour: retrait de " . abs($damaged_diff) . " article(s) de la maintenance.");
            }
            
            $missing_diff = $new_missing - $old_missing;
            if ($missing_diff > 0) {
                $stmt_upd = $pdo->prepare("UPDATE items SET quantity_total = GREATEST(0, quantity_total - ?) WHERE id = ?");
                $stmt_upd->execute([$missing_diff, $id]);
                logStockMovement($id, $reservation_id, -$missing_diff, "Modification retour: $missing_diff manquant(s) déduit(s) du stock total.");
            } elseif ($missing_diff < 0) {
                $stmt_upd = $pdo->prepare("UPDATE items SET quantity_total = quantity_total + ? WHERE id = ?");
                $stmt_upd->execute([abs($missing_diff), $id]);
                logStockMovement($id, $reservation_id, abs($missing_diff), "Modification retour: restitution de " . abs($missing_diff) . " article(s) manquant(s).");
            }
        }
        
        // Update Return status, penalty and notes
        $stmt_update_ret = $pdo->prepare("UPDATE returns SET status = ?, penalty_total = ?, notes = ? WHERE id = ?");
        $stmt_update_ret->execute([$status, $total_penalty, $notes, $return_id]);
        
        $pdo->commit();
        
        logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'EDIT_RETURN', "Retour #$return_id modifié pour la réservation #$reservation_id.");
        
        header("Location: returns.php?msg=success");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la modification: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Retour - Sam Admin</title>
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
                <h2 style="margin: 0;">Modifier Retour #<?php echo htmlspecialchars($return_id); ?></h2>
                <p style="color: #666; margin-top: 5px;">Client: <?php echo htmlspecialchars($return_data['customer_name']); ?> (<?php echo htmlspecialchars($return_data['customer_phone']); ?>)</p>
            </div>
            <a href="returns.php" class="contact-btn" style="padding: 10px 20px; text-decoration: none; background: #888;">← Annuler</a>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="card" id="returnForm">
            <input type="hidden" name="return_id" value="<?php echo htmlspecialchars($return_id); ?>">
            
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
                                    <?php echo $item['qty_expected']; ?>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" name="qty_returned[<?php echo $item['item_id']; ?>]" 
                                           class="form-control qty-returned" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           data-expected="<?php echo $item['qty_expected']; ?>"
                                           value="<?php echo $item['qty_returned']; ?>" min="0" max="<?php echo $item['qty_expected']; ?>" style="width: 80px; text-align: center;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" name="qty_damaged[<?php echo $item['item_id']; ?>]" 
                                           class="form-control qty-damaged" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           value="<?php echo $item['qty_damaged']; ?>" min="0" max="<?php echo $item['qty_expected']; ?>" style="width: 80px; text-align: center; color: #d97706;">
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" name="qty_missing[<?php echo $item['item_id']; ?>]" 
                                           class="form-control qty-missing" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           value="<?php echo $item['qty_missing']; ?>" min="0" max="<?php echo $item['qty_expected']; ?>" style="width: 80px; text-align: center; color: #ef4444;" readonly>
                                </td>
                                <td style="text-align: right;">
                                    <input type="number" name="penalty_amount[<?php echo $item['item_id']; ?>]" 
                                           class="form-control penalty-amount" 
                                           data-id="<?php echo $item['item_id']; ?>" 
                                           data-price="<?php echo $item['price_per_day']; ?>" 
                                           value="<?php echo number_format($item['penalty_amount'], 0, '', ''); ?>" min="0" style="width: 100px; text-align: right; color: #ef4444; font-weight: bold;" data-manually-edited="true">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; font-weight: 800; font-size: 1.2rem;">TOTAL PÉNALITÉS :</td>
                            <td style="text-align: right; font-weight: 800; font-size: 1.4rem; color: #ef4444;" id="totalPenalty">
                                <?php echo number_format($return_data['penalty_total'], 0, '', ''); ?> <?php echo getCurrency(); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Notes d'inspection</label>
                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($return_data['notes'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="contact-btn" style="width: 100%; border: none; padding: 15px; font-size: 1.1rem; cursor: pointer; background: #6366f1;">
                <i class="fas fa-save"></i> Enregistrer les modifications
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
