<?php
// admin/profile.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$user_id = $_SESSION['user_id'];
$msg = '';
$error = '';

if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $old_password = $_POST['old_password'] ?? '';
    $password = $_POST['password'];

    // Update base info
    if (!empty($password)) {
        // Must verify old password first
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_hash = $stmt->fetchColumn();

        if (!password_verify($old_password, $current_hash)) {
            $error = "Erreur: L'ancien mot de passe est incorrect.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $hashed, $user_id]);
            $msg = "Profil et mot de passe mis à jour avec succès !";
        }
    } else {
        // Update without changing password
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $user_id]);
        $msg = "Profil mis à jour avec succès !";
    }
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// User Transactions Summary & Recent History
$stmt = $pdo->prepare("SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM payments WHERE processed_by = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT p.*, r.customer_name FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE p.processed_by = ? ORDER BY p.created_at DESC LIMIT 15");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Sam Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
        <a href="calendar.php"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>

        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <a href="profile.php" class="active"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>

        <?php if (hasRole('super_admin')): ?>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e214a4ff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">
                Super Admin</div>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>

        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp;
            Déconnexion</a>
    </div>

    <div class="main-content">
        <h2>Mon Profil (Administrateur)</h2>
        <p style="color: #666; margin-bottom: 30px;">Gérez vos informations personnelles et vos identifiants de connexion.</p>

        <?php if ($msg): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 25px;">
                <i class="fas fa-check-circle"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 25px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="manage-grid" style="align-items: start;">
            <!-- Left col: form -->
            <div class="card">
                <h3>Informations Personnelles</h3>
                <form method="POST" style="margin-top: 20px;">
                    <div class="form-group">
                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">Nom Complet</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="form-control" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">Email (Identifiant de connexion)</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-control" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">Téléphone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-control" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    </div>

                    <div class="form-group" style="margin-top: 30px; background: #fafafa; padding: 20px; border-radius: 10px; border: 1px solid #eee;">
                        <h3 style="font-size: 1.1rem; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; color: #d97706;"><i class="fas fa-lock"></i> Changer de Mot de passe</h3>
                        
                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">Ancien Mot de passe <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="old_password" class="form-control" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;" placeholder="Obligatoire pour changer le mot de passe">

                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">Nouveau Mot de passe</label>
                        <input type="password" name="password" class="form-control" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 10px;" placeholder="Laissez vide pour ne pas changer">
                    </div>

                    <button type="submit" name="update_profile" class="contact-btn" style="width: 100%; border: none; padding: 15px; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 20px;">Enregistrer les modifications</button>
                </form>
            </div>

            <!-- Right col: Analytics and History -->
            <div>
                <div class="grid-stats" style="margin-bottom: 20px; gap: 15px;">
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); text-align: center;">
                        <div style="color: #666; font-size: 0.9rem;">Total Encaissements Personnels</div>
                        <div style="font-size: 1.8rem; font-weight: 900; color: #1f2937; margin-top: 5px;"><?php echo $stats['total_count'] ?: 0; ?></div>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); text-align: center; border-bottom: 4px solid #10b981;">
                        <div style="color: #666; font-size: 0.9rem;">Valeur Générée (Personnel)</div>
                        <div style="font-size: 1.5rem; font-weight: 900; color: #10b981; margin-top: 5px;"><?php echo number_format((float)$stats['total_amount'], 0); ?> F</div>
                    </div>
                </div>

                <div class="card">
                    <h3>Mes Récentes Transactions</h3>
                    <div class="table-responsive" style="margin-top: 15px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="text-align: left; border-bottom: 2px solid #eee; font-size: 0.85rem; color: #666;">
                                    <th style="padding: 10px;">Date</th>
                                    <th style="padding: 10px;">Réservation</th>
                                    <th style="padding: 10px;">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_transactions)): ?>
                                    <tr>
                                        <td colspan="3" style="padding: 20px; text-align: center; color: #999;">Aucune transaction enregistrée sous votre compte.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($recent_transactions as $t): ?>
                                <tr style="border-bottom: 1px solid #f9f9f9; font-size: 0.9rem;">
                                    <td style="padding: 10px;"><?php echo date('d/m/y H:i', strtotime($t['created_at'])); ?></td>
                                    <td style="padding: 10px; font-weight: 600;">
                                        #<?php echo $t['reservation_id']; ?>
                                    </td>
                                    <td style="padding: 10px; font-weight: 800; color: #166534;">+<?php echo number_format($t['amount'], 0); ?> F</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 15px; text-align: center;">
                            <a href="caisse.php" style="color: #4338ca; text-decoration: none; font-size: 0.85rem; font-weight: 600;">Voir l'historique complet dans la Caisse <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>
