<?php
// login.php
require_once 'includes/db.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = $_POST['login_input'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['branch_id'] = $user['branch_id'];

        logActivity($user['id'], $user['branch_id'], 'LOGIN', 'Connexion réussie via email/téléphone.');

        // Role-based redirection
        switch ($user['role']) {
            case 'super_admin':
            case 'mini_admin':
                header("Location: admin/dashboard.php");
                break;
            case 'receptionist':
                header("Location: receptionist/dashboard.php");
                break;
            case 'client':
                header("Location: client/dashboard.php");
                break;
            default:
                header("Location: index.php");
        }
        exit;
    } else {
        $error = "Identifiants invalides.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    // Google Sign-In verification
    $token = $_POST['credential'];
    $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
    $response = @file_get_contents($verify_url);

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['email'])) {
            $email = $data['email'];

            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];

                logActivity($user['id'], $user['branch_id'], 'LOGIN', 'Connexion réussie via Google Sign-In.');

                // Role-based redirection
                switch ($user['role']) {
                    case 'super_admin':
                    case 'mini_admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'receptionist':
                        header("Location: receptionist/dashboard.php");
                        break;
                    case 'client':
                        header("Location: client/dashboard.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit;
            } else {
                $error = "Aucun compte associé à cette adresse Google ($email).";
            }
        } else {
            $error = "Échec de la validation Google.";
        }
    } else {
        $error = "Erreur de connexion avec Google.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Sam Event Location</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-box {
            max-width: 450px;
            margin: 60px auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-strong);
            border-top: 6px solid var(--primary-blue);
        }
    </style>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>

<body style="background: var(--light-gray);">

    <div class="container" style="text-align: center;">
        <a href="index.php" class="logo-container"
            style="justify-content: center; margin-bottom: 30px; margin-top: 40px;">
            <div
                style="width: 60px; height: 60px; background: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border: 4px solid var(--primary-blue); font-size: 1.5rem;">
                S</div>
            <div class="logo-text" style="font-size: 1.8rem;">Sam Event <span>LOCATION</span></div>
        </a>

        <div class="login-box">
            <h2 style="margin-bottom: 5px;">Bienvenue</h2>
            <p style="color: #666; margin-bottom: 25px;">Connectez-vous pour accéder à votre espace</p>

            <?php if ($error): ?>
                <div
                    style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Google Sign-In Box -->
                <div id="g_id_onload"
                    data-client_id="533254255368-1ebu3uuktff7v3oigeq581ah7vqrm6i6.apps.googleusercontent.com"
                    data-context="signin" data-ux_mode="popup"
                    data-login_uri="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']; ?>"
                    data-auto_prompt="false">
                </div>
                <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
                    data-text="signin_with" data-size="large" data-logo_alignment="left"
                    style="display: flex; justify-content: center; margin-bottom: 20px;">
                </div>

                <div style="text-align: center; margin: 20px 0; color: #666; position: relative;">
                    <hr style="border: 0; border-top: 1px solid #ddd;">
                    <span
                        style="background: white; padding: 0 10px; position: absolute; top: -10px; left: 50%; transform: translateX(-50%);">OU</span>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label><i class="fas fa-user-circle"></i> Email ou Téléphone</label>
                    <input type="text" name="login_input" class="form-control" placeholder="votre@email.com ou 00000000"
                        required style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%;">
                </div>
                <div class="form-group" style="text-align: left; margin-top: 20px;">
                    <label><i class="fas fa-lock"></i> Mot de passe</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required
                            style="padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%; padding-right: 40px;">
                        <i class="fas fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 15px; cursor: pointer; color: #666;"></i>
                    </div>
                </div>

                <button type="submit" class="contact-btn"
                    style="width: 100%; border: none; cursor: pointer; justify-content: center; margin-top: 30px; padding: 15px; font-size: 1.1rem;">
                    Se connecter
                </button>
            </form>

            <p style="margin-top: 30px; color: #666;">
                Pas encore de compte ?
                <a href="register.php"
                    style="color: var(--secondary-orange); text-decoration: none; font-weight: 700;">S'inscrire ici</a>
                <br><br>
                <a href="index.php" style="color: var(--primary-blue); text-decoration: none; font-weight: 700;">←
                    Retour à l'accueil</a>
            </p>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>