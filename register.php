<?php
// register.php
require_once 'includes/db.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si l'email, le téléphone ou le nom existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? OR name = ?");
        $stmt->execute([$email, $phone, $name]);
        if ($stmt->fetch()) {
            $error = "Ce nom, email ou numéro de téléphone est déjà utilisé.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'client')");
            if ($stmt->execute([$name, $email, $phone, $hashed_password])) {
                $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
            } else {
                $error = "Une erreur est survenue lors de la création du compte.";
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
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="votre@email.com" required
                        style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
                </div>
                <div class="form-group" style="text-align: left; margin-top: 15px;">
                    <label>Téléphone</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+227 00 00 00 00" required
                        style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
                </div>
                <div class="form-group" style="text-align: left; margin-top: 15px;">
                    <label>Mot de passe</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required
                        style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
                </div>
                <div class="form-group" style="text-align: left; margin-top: 15px;">
                    <label>Confirmer le mot de passe</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required
                        style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
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

</body>

</html>