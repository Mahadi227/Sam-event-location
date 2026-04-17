<?php
// receptionist/caisse.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStaff();

// Identity checks
$user_id = $_SESSION['user_id'];
$is_admin = hasRole('super_admin') || hasRole('mini_admin');

// Date parsing for the report
$date = $_GET['date'] ?? date('Y-m-d');
$filter_user = $is_admin ? ($_GET['user_id'] ?? $user_id) : $user_id;
$period = $_GET['period'] ?? 'day';

// Fetch users for dropdown if admin
$all_staff = [];
if ($is_admin) {
    $stmt = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('super_admin', 'mini_admin', 'receptionist')");
    $all_staff = $stmt->fetchAll();
}

// Construct Date Filter SQL and Parameters
$dateFilterSql = "";
$dateParams = [];

switch ($period) {
    case 'week':
        $dateFilterSql = "YEARWEEK(p.created_at, 1) = YEARWEEK(?, 1)";
        $dateParams = [$date];
        $label_date = "Semaine du " . date('d/m/Y', strtotime($date));
        $label_trans = "Historique de la semaine";
        break;
    case 'month':
        $dateFilterSql = "MONTH(p.created_at) = MONTH(?) AND YEAR(p.created_at) = YEAR(?)";
        $dateParams = [$date, $date];
        $label_date = "Mois de " . date('F Y', strtotime($date));
        $label_trans = "Historique du mois";
        break;
    case 'year':
        $dateFilterSql = "YEAR(p.created_at) = YEAR(?)";
        $dateParams = [$date];
        $label_date = "Année " . date('Y', strtotime($date));
        $label_trans = "Historique de l'année";
        break;
    case 'day':
    default:
        $dateFilterSql = "DATE(p.created_at) = ?";
        $dateParams = [$date];
        $label_date = "Le " . date('d/m/Y', strtotime($date));
        $label_trans = "Historique de la journée";
        break;
}

// User filter appending
$userFilterSql = "";
$userParams = [];
if ($filter_user !== 'all' && $filter_user !== '') {
    $userFilterSql = " AND p.processed_by = ?";
    $userParams = [$filter_user];
}

$queryParams = array_merge($dateParams, $userParams);

// Analytics Queries
// 1. Total par methode de paiement (Période)
$stmt = $pdo->prepare("
    SELECT p.payment_method, SUM(p.amount) as total 
    FROM payments p
    WHERE $dateFilterSql $userFilterSql
    GROUP BY p.payment_method
");
$stmt->execute($queryParams);
$methods_today = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Total global (Période choisie)
$stmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p WHERE $dateFilterSql $userFilterSql");
$stmt->execute($queryParams);
$total_today = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p WHERE MONTH(p.created_at) = MONTH(?) AND YEAR(p.created_at) = YEAR(?)$userFilterSql");
$stmt->execute(array_merge([$date, $date], $userParams));
$total_month = $stmt->fetchColumn() ?: 0;

// 3. Transactions de la période

// Pagination setup
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total transactions
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    WHERE $dateFilterSql $userFilterSql
");
$countStmt->execute($queryParams);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("
    SELECT p.*, r.customer_name 
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    WHERE $dateFilterSql $userFilterSql
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($queryParams);
$transactions = $stmt->fetchAll();

// Base URL for pagination
$query_string_params = $_GET;
unset($query_string_params['page']);
$base_url = '?';
if (!empty($query_string_params)) {
    $base_url = '?' . http_build_query($query_string_params) . '&';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caisse - Sam Reception</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .method-card {
            background: white; padding: 25px; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-left: 5px solid;
            display: flex; flex-direction: column; justify-content: center;
        }
        .method-card h4 { margin: 0; color: #666; font-size: 0.9rem; text-transform: uppercase; }
        .method-card h2 { margin: 10px 0 0; font-size: 1.8rem; font-weight: 900; }
        .method-cash { border-color: #10b981; } .method-cash h2 { color: #047857; }
        .method-orange { border-color: #f97316; } .method-orange h2 { color: #c2410c; }
        .method-moov { border-color: #0ea5e9; } .method-moov h2 { color: #0369a1; }
        .method-mynita { border-color: #facc15; } .method-mynita h2 { color: #a16207; }
        .method-amanata { border-color: #22c55e; } .method-amanata h2 { color: #166534; }
    </style>
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
        <a href="reservations.php"><i class="fas fa-list"></i> &nbsp; Reservations</a>
        <a href="calendar.php"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>
        <a href="caisse.php" class="active"><i class="fas fa-cash-register"></i> &nbsp; Caisse (Shift)</a>
        <a href="profile.php"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
            <div>
                <h2>Fermeture de Caisse</h2>
                <p style="color: #666;">Vue des transactions encaissées personnellement. Sécurisé.</p>
            </div>
            
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <?php if ($is_admin): ?>
                    <div style="border-right: 1px solid #eee; padding-right: 15px;">
                        <span style="font-size: 0.8rem; color: #999; display: block; margin-bottom: 5px;">Superviser Employé (Admin)</span>
                        <select name="user_id" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-weight: 600;">
                            <?php foreach ($all_staff as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $filter_user == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['name']) . ' (' . strtoupper($staff['role']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div style="border-right: 1px solid #eee; padding-right: 15px;">
                    <span style="font-size: 0.8rem; color: #999; display: block; margin-bottom: 5px;">Période</span>
                    <select name="period" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-weight: 600;">
                        <option value="day" <?php echo $period === 'day' ? 'selected' : ''; ?>>Journée</option>
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Semaine</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Mois</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Année</option>
                    </select>
                </div>

                <div>
                    <span style="font-size: 0.8rem; color: #999; display: block; margin-bottom: 5px;">Date de référence</span>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-weight: 600;">
                </div>
                
                <button type="submit" class="contact-btn" style="padding: 8px 15px; margin-top: 20px;"><i class="fas fa-sync-alt"></i></button>
            </form>
        </div>

        <!-- Global Totals -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: linear-gradient(135deg, #1f2937, #111827); color: white; padding: 30px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="color: #9ca3af; font-size: 1rem; margin-bottom: 5px; font-weight: 500;">Total Encaissé (<?php echo $label_date; ?>)</h3>
                    <h1 style="font-size: 2.5rem; margin: 0; font-weight: 900; color: var(--accent-gold);"><?php echo number_format($total_today, 0); ?> F</h1>
                </div>
                <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; justify-content: center; align-items: center;">
                    <i class="fas fa-wallet fa-2x" style="color: var(--accent-gold);"></i>
                </div>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee;">
                <div>
                    <h3 style="color: #6b7280; font-size: 1rem; margin-bottom: 5px; font-weight: 500;">Total Mois (<?php echo date('F Y', strtotime($date)); ?>)</h3>
                    <h1 style="font-size: 2rem; margin: 0; font-weight: 800; color: #111827;"><?php echo number_format($total_month, 0); ?> F</h1>
                </div>
                <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 50%; display: flex; justify-content: center; align-items: center;">
                    <i class="fas fa-calendar-check fa-lg" style="color: #6b7280;"></i>
                </div>
            </div>
        </div>

        <!-- Methods Breakdown -->
        <h3 style="margin-bottom: 15px;"><i class="fas fa-chart-pie"></i> Clôture par Méthode</h3>
        <div class="grid-stats" style="margin-bottom: 40px;">
            <div class="method-card method-cash">
                <h4><i class="fas fa-money-bill-wave"></i> Espèces (Cash)</h4>
                <h2><?php echo number_format($methods_today['cash'] ?? 0, 0); ?> F</h2>
            </div>
            <div class="method-card method-orange">
                <h4><i class="fas fa-mobile-alt"></i> Orange Money</h4>
                <h2><?php echo number_format($methods_today['orange_money'] ?? 0, 0); ?> F</h2>
            </div>
            <div class="method-card method-moov">
                <h4><i class="fas fa-mobile-alt"></i> Moov Money</h4>
                <h2><?php echo number_format($methods_today['moov_money'] ?? 0, 0); ?> F</h2>
            </div>
            <div class="method-card method-mynita">
                <h4><i class="fas fa-wallet"></i> MyNiTa</h4>
                <h2><?php echo number_format($methods_today['MyNiTa'] ?? 0, 0); ?> F</h2>
            </div>
            <div class="method-card method-amanata">
                <h4><i class="fas fa-wallet"></i> AmanaTa</h4>
                <h2><?php echo number_format($methods_today['AmanaTa'] ?? 0, 0); ?> F</h2>
            </div>
            <div class="method-card" style="border-left-color: #64748b;">
                <h4><i class="fas fa-credit-card"></i> Carte</h4>
                <h2 style="color: #334155;"><?php echo number_format($methods_today['card'] ?? 0, 0); ?> F</h2>
            </div>
        </div>

        <!-- Transactions History -->
        <div class="card">
            <h3><?php echo $label_trans; ?></h3>
            <div class="table-responsive" style="margin-top: 15px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 15px;">Heure</th>
                            <th style="padding: 15px;">Réservation</th>
                            <th style="padding: 15px;">Client</th>
                            <th style="padding: 15px;">Méthode</th>
                            <th style="padding: 15px;">Référence</th>
                            <th style="padding: 15px;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="6" style="padding: 30px; text-align: center; color: #999;">Aucun encaissement réalisé à cette date.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 15px; font-weight: 500;"><?php echo date('H:i', strtotime($t['created_at'])); ?></td>
                            <td style="padding: 15px;">
                                <a href="manage.php?id=<?php echo $t['reservation_id']; ?>" style="color: var(--primary-blue); text-decoration: none; font-weight: 700;">#<?php echo $t['reservation_id']; ?></a>
                            </td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($t['customer_name']); ?></td>
                            <td style="padding: 15px;"><span class="method-tag"><?php echo strtoupper($t['payment_method']); ?></span></td>
                            <td style="padding: 15px; font-size: 0.8rem; color: #888;"><?php echo $t['transaction_ref'] ? htmlspecialchars($t['transaction_ref']) : '-'; ?></td>
                            <td style="padding: 15px; font-weight: 800; color: #166534;">+ <?php echo number_format($t['amount'], 0); ?> F</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Links -->
            <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; padding-bottom: 20px;">
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page - 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;"><i class="fas fa-chevron-left"></i> Précédent</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="<?php echo htmlspecialchars($base_url . 'page=' . $i); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; <?php echo $i === $page ? 'background: #03117a; color: white; border-color: #03117a;' : 'background: white; color: #333;'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page + 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;">Suivant <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        </div>

    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>

