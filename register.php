<?php
// register.php
require_once 'includes/db.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si le téléphone ou l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? OR (email != '' AND email = ?)");
        $stmt->execute([$phone, $email]);
        if ($stmt->fetch()) {
            $error = "Ce numéro de téléphone ou cet email est déjà utilisé par un autre compte.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'client')");
                $stmt->execute([$name, $email, $phone, $hashed_password]);
                
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['name'] = $name;
                $_SESSION['role'] = 'client';
                header("Location: client/dashboard.php");
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Erreur de base de données : conflit de validation (nom, email ou téléphone déjà existant).";
                } else {
                    $error = "Une erreur est survenue lors de la création du compte. Veuillez réessayer plus tard.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Sam Event Location</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .register-box {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-strong);
            border-top: 6px solid var(--secondary-orange);
        }
    </style>
</head>

<body style="background: var(--light-gray);">

    <div class="container" style="text-align: center;">
        <a href="index.php" class="logo-container"
            style="justify-content: center; margin-bottom: 30px; margin-top: 30px;">
            <div
                style="width: 60px; height: 60px; background: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 4px solid var(--primary-blue); font-size: 1.5rem;">
                S</div>
            <div class="logo-text" style="font-size: 1.8rem;">Sam Event <span>LOCATION</span></div>
        </a>

        <div class="register-box">
            <h2 style="margin-bottom: 25px;">Créer un Compte</h2>

            <?php if ($error): ?>
                <div
                    style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; text-align: left;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div
                    style="background: #d4edda; color: #155724; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; text-align: left;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group" style="text-align: left;">
                    <label>Nom Complet</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Moussa Abdou" required
                        style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
                </div>
                <div class="form-group" style="text-align: left; margin-top: 15px;">
                    <label>Email <span style="color: #999; font-size: 0.85em;">(Optionnel)</span></label>
                    <input type="email" name="email" class="form-control" placeholder="votre@email.com"
                        style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
                </div>
                <div class="form-group" style="text-align: left; margin-top: 15px;">
                    <label>Téléphone</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+227 00 00 00 00" required
                        style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
                </div>
                <div class="form-group" style="text-align: left; margin-top: 15px;">
                    <label>Mot de passe</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required
                            style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%; padding-right: 40px;">
                        <i class="fas fa-eye togglePassword" data-target="password" style="position: absolute; right: 15px; top: 15px; cursor: pointer; color: #666;"></i>
                    </div>
                </div>
                <div class="form-group" style="text-align: left; margin-top: 15px;">
                    <label>Confirmer le mot de passe</label>
                    <div style="position: relative;">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required
                            style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%; padding-right: 40px;">
                        <i class="fas fa-eye togglePassword" data-target="confirm_password" style="position: absolute; right: 15px; top: 15px; cursor: pointer; color: #666;"></i>
                    </div>
                </div>

                <button type="submit" class="contact-btn"
                    style="width: 100%; border: none; cursor: pointer; justify-content: center; margin-top: 30px; padding: 15px; font-size: 1.1rem;">
                    S'inscrire
                </button>
            </form>

            <p style="margin-top: 30px; color: #666;">
                Déjà un compte ?
                <a href="login.php" style="color: var(--primary-blue); text-decoration: none; font-weight: 700;">Se
                    connecter</a>
            </p>
        </div>
    </div>

    <script>
        document.querySelectorAll('.togglePassword').forEach(icon => {
            icon.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>

</html>