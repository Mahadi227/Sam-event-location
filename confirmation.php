<?php
// confirmation.php
require_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.*, b.name as branch_name 
    FROM reservations r 
    LEFT JOIN branches b ON r.branch_id = b.id 
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    die("Réservation introuvable.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation - Sam Event Location</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f4f6f9 0%, #e5e9f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        .header-simple {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: center;
        }

        .header-simple .logo-container {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 15px;
        }

        .header-simple .logo-icon {
            width: 45px;
            height: 45px;
            background: var(--accent-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            border: 3px solid var(--primary-blue);
            font-size: 1.2rem;
        }

        .header-simple .logo-text {
            color: var(--primary-blue);
            font-size: 1.5rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .header-simple .logo-text span {
            color: var(--accent-gold);
        }

        .success-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .confirmation-card {
            background: white;
            border-radius: 24px;
            padding: 50px 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideUpFade 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .confirmation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            background: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: popIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both, float 3s ease-in-out infinite alternate 1s;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.2);
        }

        .icon-wrapper i {
            font-size: 50px;
            color: #10b981;
        }

        .confirmation-card h1 {
            color: var(--dark-blue);
            font-size: 2.2rem;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .confirmation-card > p {
            color: #64748b;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 35px;
        }

        .details-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 25px;
            text-align: left;
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed #cbd5e1;
            font-size: 1.05rem;
        }

        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-row:first-child {
            padding-top: 0;
        }

        .detail-label {
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-label i {
            color: var(--primary-blue);
            width: 20px;
            text-align: center;
        }

        .detail-value {
            font-weight: 700;
            color: var(--dark-blue);
        }

        .status-badge {
            background: #fef3c7;
            color: #d97706;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .contact-info {
            background: rgba(3, 17, 122, 0.04);
            border-radius: 12px;
            padding: 20px;
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-glowing {
            background: linear-gradient(135deg, var(--accent-gold) 0%, #e6a300 100%);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(230, 163, 0, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-glowing:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(230, 163, 0, 0.4);
        }

        .btn-outline {
            border: 2px solid #e2e8f0;
            color: #64748b;
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: #f1f5f9;
            color: var(--dark-blue);
            border-color: #cbd5e1;
        }

        /* Animations */
        @keyframes slideUpFade {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.5); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            100% { transform: translateY(-8px); }
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<header class="header-simple">
    <a href="index.php" class="logo-container">
        <div class="logo-icon">S</div>
        <div class="logo-text">Sam Event <span>LOCATION</span></div>
    </a>
</header>

<div class="success-container">
    <div class="confirmation-card">
        <div class="icon-wrapper">
            <i class="fas fa-check"></i>
        </div>
        <h1>Merci, <?php echo htmlspecialchars($reservation['customer_name'] ?? ''); ?> !</h1>
        <p>Votre demande de réservation a été enregistrée avec succès. Notre équipe s'occupe de tout.</p>
        
        <div class="details-box">
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-hashtag"></i> Numéro de la réservation</span>
                <span class="detail-value">#<?php echo $reservation['id']; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-calendar-alt"></i> Date prévue</span>
                <span class="detail-value"><?php echo date('d/m/Y', strtotime($reservation['event_date'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-building"></i> Branch</span>
                <span class="detail-value"><?php echo htmlspecialchars($reservation['branch_name'] ?? 'Principale'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-wallet"></i> Montant Total</span>
                <span class="detail-value" style="color: var(--secondary-orange); font-size: 1.15rem;"><?php echo number_format($reservation['total_price'], 0, ',', ' '); ?> <?php echo getCurrency(); ?></span>
            </div>
            <div class="detail-row" style="align-items: center;">
                <span class="detail-label"><i class="fas fa-info-circle"></i> Statut</span>
                <span class="status-badge"><i class="fas fa-clock"></i> En attente</span>
            </div>
        </div>
        
        <div class="contact-info">
            <span>
Notre équipe vous contactera au <?php echo htmlspecialchars($reservation['customer_phone'] ?? ''); ?>  très prochainement pour confirmer la disponibilité et finaliser les détails.</span>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn-outline"><i class="fas fa-home"></i> Accueil</a>
            <a href="track_reservation.php?phone=<?php echo urlencode($reservation['customer_phone']); ?>" class="btn-glowing">Suivre la commande <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> Sam Event Location - Propulsé par la perfection</p>
</footer>

</body>
</html>
