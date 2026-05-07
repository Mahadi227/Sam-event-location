<?php
// admin/caisse.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireAdmin();

$msg = '';

// Add/Edit Payment
if (isset($_POST['save_payment'])) {
    $id = $_POST['id'] ?? null;
    $reservation_id = $_POST['reservation_id'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $transaction_ref = $_POST['transaction_ref'];
    $processed_by = $_SESSION['user_id'];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE payments SET reservation_id = ?, amount = ?, payment_method = ?, transaction_ref = ? WHERE id = ?");
        $stmt->execute([$reservation_id, $amount, $payment_method, $transaction_ref, $id]);
        
        $pdo->prepare("UPDATE reservations r SET amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = r.id AND payment_type = 'rental') WHERE id = ?")->execute([$reservation_id]);
        
        $msg = "Paiement mis à jour avec succès !";
    } else {
        $stmt = $pdo->prepare("INSERT INTO payments (reservation_id, amount, payment_method, transaction_ref, processed_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$reservation_id, $amount, $payment_method, $transaction_ref, $processed_by]);
        
        $pdo->prepare("UPDATE reservations r SET amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = r.id AND payment_type = 'rental') WHERE id = ?")->execute([$reservation_id]);
        
        // Notify Client & Staff
        $resQ = $pdo->prepare("SELECT user_id, customer_name FROM reservations WHERE id = ?");
        $resQ->execute([$reservation_id]);
        $resInfo = $resQ->fetch();
        if ($resInfo && $resInfo['user_id']) {
            createNotification($resInfo['user_id'], "Paiement Enregistré", "Votre paiement de " . number_format($amount, 0) . " F a été validé. Merci.", "payment", $reservation_id);
        }
        
        $staff_name = $_SESSION['name'] ?? 'Admin';
        $processor_id = $_SESSION['user_id'] ?? null;
        notifyPaymentProcessed("Paiement Encaissé", "Paiement de " . number_format($amount, 0) . " F par $staff_name (Ref Réservation: #$reservation_id - " . ($resInfo['customer_name'] ?? 'Client') . ").", $reservation_id, $processor_id);

        $msg = "Paiement enregistré avec succès !";
    }
}

// Delete Payment
if (isset($_GET['delete'])) {
    if (!hasRole('super_admin')) {
        die("Accès refusé. Seul le Super Administrateur peut supprimer un paiement dans la caisse.");
    }
    
    $id_to_del = $_GET['delete'];
    $resQ = $pdo->prepare("SELECT reservation_id, payment_type FROM payments WHERE id = ?");
    $resQ->execute([$id_to_del]);
    $payment_info = $resQ->fetch();
    
    if ($payment_info) {
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$id_to_del]);
        
        if ($payment_info['payment_type'] === 'rental') {
            $pdo->prepare("UPDATE reservations r SET amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = r.id AND payment_type = 'rental') WHERE id = ?")->execute([$payment_info['reservation_id']]);
        } else {
            $pdo->prepare("UPDATE returns SET penalty_paid = (SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id = ? AND payment_type = 'penalty') WHERE reservation_id = ?")->execute([$payment_info['reservation_id'], $payment_info['reservation_id']]);
        }
    }
    
    header("Location: caisse.php");
    exit;
}

// Active branch handling for Super Admin
if (hasRole('super_admin') && isset($_GET['branch'])) {
    if ($_GET['branch'] === 'all') {
        unset($_SESSION['active_branch']);
    } else {
        $_SESSION['active_branch'] = (int)$_GET['branch'];
    }
}
$active_branch = hasRole('super_admin') ? ($_SESSION['active_branch'] ?? null) : $_SESSION['branch_id'];
$branchSql = getBranchSqlFilter('r');
$userBranchFilter = getBranchSqlFilter('u');

// Fetch branches for filter
$branches = [];
if (hasRole('super_admin')) {
    $branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
}

// Get Reservations for Dropdown
$reservations = $pdo->query("SELECT r.id, r.customer_name, r.total_price, r.amount_paid FROM reservations r WHERE 1=1 $branchSql ORDER BY id DESC")->fetchAll();

// Date parsing for the report
$date = $_GET['date'] ?? date('Y-m-d');
$filter_user = $_GET['user_id'] ?? 'all';
$period = $_GET['period'] ?? 'day';

// Fetch users for dropdown
$all_staff = [];
$stmt = $pdo->query("SELECT u.id, u.name, u.role FROM users u WHERE u.role IN ('super_admin', 'mini_admin', 'receptionist') $userBranchFilter");
$all_staff = $stmt->fetchAll();

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
if ($filter_user !== 'all') {
    $userFilterSql = " AND p.processed_by = ?";
    $userParams = [$filter_user];
}

$queryParams = array_merge($dateParams, $userParams);

// Analytics Queries
// 1. Total par methode de paiement (Période)
$stmt = $pdo->prepare("
    SELECT p.payment_method, SUM(p.amount) as total 
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    WHERE $dateFilterSql $userFilterSql $branchSql
    GROUP BY p.payment_method
");
$stmt->execute($queryParams);
$methods_today = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Total global (Période choisie)
$stmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE $dateFilterSql $userFilterSql $branchSql");
$stmt->execute($queryParams);
$total_today = $stmt->fetchColumn() ?: 0;

// Total mois en cours (pour référence)
$stmt = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN reservations r ON p.reservation_id = r.id WHERE MONTH(p.created_at) = MONTH(?) AND YEAR(p.created_at) = YEAR(?)$userFilterSql $branchSql");
$stmt->execute(array_merge([$date, $date], $userParams));
$total_month = $stmt->fetchColumn() ?: 0;

// Pagination setup
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    LEFT JOIN users u ON p.processed_by = u.id
    WHERE $dateFilterSql $userFilterSql $branchSql
");
$countStmt->execute($queryParams);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("
    SELECT p.*, r.customer_name, u.name as staff_name 
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    LEFT JOIN users u ON p.processed_by = u.id
    WHERE $dateFilterSql $userFilterSql $branchSql
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($queryParams);
$transactions = $stmt->fetchAll();

$query_string_params = $_GET;
unset($query_string_params['page']);
$base_url = '?';
if (!empty($query_string_params)) $base_url = '?' . http_build_query($query_string_params) . '&';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caisse Globale - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=7">
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
    <div style="font-weight: 800; color: white;">Sam Management</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="admin-sidebar">
        <h2>Sam Management</h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> &nbsp; Dashboard</a>
        <a href="items.php"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="returns.php"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
            <a href="transfers.php"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
        <a href="caisse.php" class="active"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="branches.php"><i class="fas fa-building"></i> &nbsp; Branches</a>
        <?php endif; ?>
        <?php if (hasRole('super_admin') || hasRole('mini_admin')): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>
            <a href="logs.php"><i class="fas fa-history"></i> &nbsp; Journal d'Activité</a>
        <?php endif; ?>
        <?php if (hasRole('super_admin')): ?>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
            <div>
                <h2>Supervision Caisse Globale</h2>
                <p style="color: #666;">Vue des encaissements globaux ou individuels par personnel.</p>
            </div>
            
            <button onclick="newPayment()" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">+ Nouvel Encaissement</button>
            
            <?php if ($msg): ?>
                <div style="width: 100%; background: #d4edda; color: #155724; padding: 15px; border-radius: 10px;">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); width: 100%;">
                <?php if (hasRole('super_admin')): ?>
                <div style="border-right: 1px solid #eee; padding-right: 15px;">
                    <span style="font-size: 0.8rem; color: #999; display: block; margin-bottom: 5px;">Succursale</span>
                    <select name="branch" onchange="this.form.submit()" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-weight: 600;">
                        <option value="all">Toutes les branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $active_branch == $b['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="border-right: 1px solid #eee; padding-right: 15px;">
                    <span style="font-size: 0.8rem; color: #999; display: block; margin-bottom: 5px;">Filtrer par Personnel</span>
                    <select name="user_id" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-weight: 600;">
                        <option value="all" <?php echo $filter_user === 'all' ? 'selected' : ''; ?>>Tous</option>
                        <?php foreach ($all_staff as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>" <?php echo $filter_user == $staff['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff['name']) . ' (' . strtoupper($staff['role']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
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
                    <i class="fas fa-chart-line fa-lg" style="color: #6b7280;"></i>
                </div>
            </div>
        </div>

        <!-- Methods Breakdown -->
        <h3 style="margin-bottom: 15px;"><i class="fas fa-chart-pie"></i> Répartition par Méthode</h3>
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
                            <th style="padding: 15px;">Personnel</th>
                            <th style="padding: 15px;">Méthode</th>
                            <th style="padding: 15px;">Référence</th>
                            <th style="padding: 15px;">Montant</th>
                            <th style="padding: 15px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="8" style="padding: 30px; text-align: center; color: #999;">Aucun encaissement trouvé à cette date.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr style="border-bottom: 1px solid #f9f9f9;">
                            <td style="padding: 15px; font-weight: 500;"><?php echo date('H:i', strtotime($t['created_at'])); ?></td>
                            <td style="padding: 15px;">
                                <a href="manage.php?id=<?php echo $t['reservation_id']; ?>" style="color: var(--primary-blue); text-decoration: none; font-weight: 700;">#<?php echo $t['reservation_id']; ?></a>
                            </td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($t['customer_name']); ?></td>
                            <td style="padding: 15px; color: #555;"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($t['staff_name'] ?? 'Admin/Système'); ?></td>
                            <td style="padding: 15px;">
                                <span class="method-tag"><?php echo strtoupper($t['payment_method']); ?></span>
                                <?php if ($t['payment_type'] === 'penalty'): ?>
                                    <br><span style="background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-top: 5px; display: inline-block;">PÉNALITÉ</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; font-size: 0.8rem; color: #888;"><?php echo $t['transaction_ref'] ? htmlspecialchars($t['transaction_ref']) : '-'; ?></td>
                            <td style="padding: 15px; font-weight: 800; color: #166534;">+ <?php echo number_format($t['amount'], 0); ?> <?php echo getCurrency(); ?></td>
                            <td style="padding: 15px;">
                                <?php if ($t['payment_type'] === 'rental'): ?>
                                    <button onclick="editPayment(<?php echo htmlspecialchars(json_encode($t)); ?>)" style="background:none; border:none; color: #4338ca; cursor: pointer; margin-right: 10px;"><i class="fas fa-edit"></i></button>
                                <?php endif; ?>
                                <?php if (hasRole('super_admin')): ?>
                                    <a href="?delete=<?php echo $t['id']; ?>" onclick="return confirm('Confirmer la suppression de ce paiement ?')" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
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

<!-- Modal Form -->
<div id="paymentModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; position: relative;">
        <h3 id="modalTitle">Paiement</h3>
        <button type="button" onclick="this.parentElement.parentElement.style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="id" id="payment_id">
            
            <div class="form-group">
                <label>Réservation</label>
                <select name="reservation_id" id="payment_res" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    <option value="">-- Sélectionner --</option>
                    <?php foreach ($reservations as $res): ?>
                        <option value="<?php echo $res['id']; ?>">
                            #<?php echo $res['id']; ?> - <?php echo htmlspecialchars($res['customer_name']); ?> 
                            (Reste: <?php echo number_format($res['total_price'] - $res['amount_paid'], 0, ',', ' '); ?> F)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Montant (FCFA)</label>
                <input type="number" name="amount" id="payment_amount" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
            </div>

            <div class="form-group">
                <label>Méthode de paiement</label>
                <select name="payment_method" id="payment_method" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    <option value="cash">CASH</option>
                    <option value="orange_money">Orange Money</option>
                    <option value="moov_money">Moov Money</option>
                    <option value="card">Card</option>
                </select>
            </div>

            <div class="form-group">
                <label>Référence Transaction (Optionnel)</label>
                <input type="text" name="transaction_ref" id="payment_ref" placeholder="Ex: OM123456" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">
            </div>
            
            <button type="submit" name="save_payment" class="contact-btn" style="width:100%; border:none; padding:15px; cursor: pointer;">Valider le Paiement</button>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js?v=7"></script>
<script>
function newPayment() {
    document.getElementById('payment_id').value = '';
    document.getElementById('payment_res').value = '';
    document.getElementById('payment_amount').value = '';
    document.getElementById('payment_method').value = 'cash';
    document.getElementById('payment_ref').value = '';
    document.getElementById('modalTitle').innerText = 'Nouvel Encaissement';
    document.getElementById('paymentModal').style.display = 'flex';
}

function editPayment(p) {
    document.getElementById('payment_id').value = p.id;
    document.getElementById('payment_res').value = p.reservation_id;
    document.getElementById('payment_amount').value = p.amount;
    document.getElementById('payment_method').value = p.payment_method;
    document.getElementById('payment_ref').value = p.transaction_ref || '';
    document.getElementById('modalTitle').innerText = 'Modifier l\'Encaissement';
    document.getElementById('paymentModal').style.display = 'flex';
}
</script>
</body>
</html>
