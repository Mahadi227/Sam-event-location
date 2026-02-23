<?php
// receptionist/manage.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStaff();

$id = $_GET['id'] ?? null;
if (!$id) die("ID Manquant");

// Process status update
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $id]);
}

// Process payment
if (isset($_POST['record_payment'])) {
    $amount = $_POST['amount'];
    $method = $_POST['method'];
    $ref = $_POST['ref'] ?? '';
    
    $pdo->beginTransaction();
    try {
        // Record payment transaction
        $stmt = $pdo->prepare("INSERT INTO payments (reservation_id, amount, payment_method, transaction_ref) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $amount, $method, $ref]);
        
        // Update reservation total paid
        $stmt = $pdo->prepare("UPDATE reservations SET amount_paid = amount_paid + ? WHERE id = ?");
        $stmt->execute([$amount, $id]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur paiement : " . $e->getMessage());
    }
}

// Get reservation info
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch();

// Get items
$stmt = $pdo->prepare("SELECT ri.*, i.name as item_name FROM reservation_items ri JOIN items i ON ri.item_id = i.id WHERE ri.reservation_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("SELECT * FROM payments WHERE reservation_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gérer la Réservation #<?php echo $id; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                <h3>Infos Client & Événement</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <p style="color: #666; font-size: 0.85rem;">Client</p>
                        <p><strong><?php echo htmlspecialchars($res['customer_name']); ?></strong></p>
                        <p><?php echo htmlspecialchars($res['customer_phone']); ?></p>
                    </div>
                    <div>
                        <p style="color: #666; font-size: 0.85rem;">Date & Lieu</p>
                        <p><strong><?php echo date('d/m/Y', strtotime($res['event_date'])); ?></strong></p>
                        <p><?php echo htmlspecialchars($res['event_location']); ?></p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Articles</h3>
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <tr style="text-align: left; color: #666; font-size: 0.85rem; border-bottom: 1px solid #eee;">
                        <th style="padding: 10px;">Item</th>
                        <th style="padding: 10px;">Qté</th>
                        <th style="padding: 10px;">Prix U</th>
                        <th style="padding: 10px;">Total</th>
                    </tr>
                    <?php foreach ($items as $it): ?>
                    <tr style="border-bottom: 1px solid #fafafa;">
                        <td style="padding: 10px;"><?php echo htmlspecialchars($it['item_name']); ?></td>
                        <td style="padding: 10px;"><?php echo $it['quantity']; ?></td>
                        <td style="padding: 10px;"><?php echo number_format($it['price_at_time'], 0); ?> F</td>
                        <td style="padding: 10px;"><strong><?php echo number_format($it['price_at_time'] * $it['quantity'], 0); ?> F</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <div style="text-align: right; margin-top: 20px; font-size: 1.2rem; color: var(--secondary-orange); font-weight: 800;">
                    TOTAL : <?php echo number_format($res['total_price'], 0); ?> FCFA
                </div>
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
                            <td style="padding: 10px; font-weight: 700; color: #166534;">+ <?php echo number_format($p['amount'], 0); ?> F</td>
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
                    <div style="font-size: 1.5rem; font-weight: 800; color: #991b1b;"><?php echo number_format($res['total_price'] - $res['amount_paid'], 0); ?> FCFA</div>
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

<script src="../assets/js/admin.js"></script>
</body>
</html>
