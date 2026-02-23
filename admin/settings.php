<?php
// admin/settings.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

$msg = '';

if (isset($_POST['save_settings'])) {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    $msg = "Paramètres mis à jour !";
}

// Add Promo Code
if (isset($_POST['add_promo'])) {
    $code = strtoupper($_POST['code']);
    $discount = $_POST['discount'];
    $valid_until = $_POST['valid_until'];
    
    $stmt = $pdo->prepare("INSERT INTO promo_codes (code, discount_percent, valid_until) VALUES (?, ?, ?)");
    $stmt->execute([$code, $discount, $valid_until]);
    $msg = "Code promo ajouté !";
}

// Delete Promo Code
if (isset($_GET['delete_promo'])) {
    $stmt = $pdo->prepare("DELETE FROM promo_codes WHERE id = ?");
    $stmt->execute([$_GET['delete_promo']]);
    $msg = "Code promo supprimé !";
}

$settings = $pdo->query("SELECT * FROM settings")->fetchAll();
$promo_codes = $pdo->query("SELECT * FROM promo_codes ORDER BY valid_until DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres - Sam SuperAdmin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger "><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
        <a href="settings.php" class="active"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Paramètres du Système</h2>
        <p style="color: #666; margin-bottom: 30px;">Configuration globale des tarifs et taxes.</p>

    <?php if ($msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 700px;">
        <form method="POST">
            <?php foreach ($settings as $s): ?>
                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 700; margin-bottom: 8px;"><?php echo htmlspecialchars($s['description']); ?></label>
                    <input type="text" name="settings[<?php echo $s['setting_key']; ?>]" value="<?php echo htmlspecialchars($s['setting_value']); ?>" class="form-control" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd;">
                    <small style="color: #888;">Clé : <code><?php echo $s['setting_key']; ?></code></small>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" name="save_settings" class="contact-btn" style="width: 100%; border: none; padding: 15px;">Enregistrer les modifications</button>
        </form>
    </div>

    <!-- Promo Codes Section -->
    <h3 style="margin: 40px 0 20px 0;">Gestion des Codes Promos</h3>
    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start;">
        <!-- Add Promo Form -->
        <div class="card">
            <h4>Ajouter un Code</h4>
            <form method="POST" style="margin-top: 20px;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Code (ex: ETE2024)</label>
                    <input type="text" name="code" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Réduction (%)</label>
                    <input type="number" step="0.01" name="discount" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Valide jusqu'au</label>
                    <input type="date" name="valid_until" required class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <button type="submit" name="add_promo" class="contact-btn" style="width: 100%; border: none; padding: 12px; cursor: pointer;">Ajouter le code</button>
            </form>
        </div>

        <!-- Promo List -->
        <div class="card">
            <h4>Codes Actifs</h4>
            <div class="table-responsive" style="margin-top: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 10px;">Code</th>
                            <th style="padding: 10px;">Réduc.</th>
                            <th style="padding: 10px;">Validité</th>
                            <th style="padding: 10px;">Utilisé</th>
                            <th style="padding: 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promo_codes as $pc): ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 10px;"><strong><?php echo htmlspecialchars($pc['code']); ?></strong></td>
                            <td style="padding: 10px;"><?php echo number_format($pc['discount_percent'], 0); ?>%</td>
                            <td style="padding: 10px; font-size: 0.85rem; color: #666;"><?php echo date('d/m/Y', strtotime($pc['valid_until'])); ?></td>
                            <td style="padding: 10px;"><?php echo $pc['times_used']; ?></td>
                            <td style="padding: 10px;">
                                <a href="?delete_promo=<?php echo $pc['id']; ?>" onclick="return confirm('Supprimer ce code promo ?')" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($promo_codes)): ?>
                            <tr><td colspan="5" style="padding: 20px; text-align: center; color: #888;">Aucun code promo actif</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
