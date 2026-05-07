<?php
// admin/payments.php
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

    if ($id) {
        $stmt = $pdo->prepare("UPDATE payments SET reservation_id = ?, amount = ?, payment_method = ?, transaction_ref = ? WHERE id = ?");
        $stmt->execute([$reservation_id, $amount, $payment_method, $transaction_ref, $id]);
        
        $pdo->prepare("UPDATE reservations r SET amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = r.id) WHERE id = ?")->execute([$reservation_id]);
        
        $msg = "Paiement mis à jour avec succès !";
    } else {
        $stmt = $pdo->prepare("INSERT INTO payments (reservation_id, amount, payment_method, transaction_ref) VALUES (?, ?, ?, ?)");
        $stmt->execute([$reservation_id, $amount, $payment_method, $transaction_ref]);
        
        $pdo->prepare("UPDATE reservations r SET amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = r.id) WHERE id = ?")->execute([$reservation_id]);
        
        // Notify Client & Staff
        $resQ = $pdo->prepare("SELECT user_id, customer_name FROM reservations WHERE id = ?");
        $resQ->execute([$reservation_id]);
        $resInfo = $resQ->fetch();
        if ($resInfo && $resInfo['user_id']) {
            createNotification($resInfo['user_id'], "Paiement Enregistré", "Votre paiement de " . number_format($amount, 0) . " F a été validé. Merci.", "payment", $reservation_id);
        }
        $staff_name = $_SESSION['name'] ?? 'Admin';
        $processor_id = $_SESSION['user_id'] ?? null;
        notifyPaymentProcessed("Nouvel Encaissement", "Un paiement de " . number_format($amount, 0) . " F a été enregistré par $staff_name pour la réservation #$reservation_id (" . ($resInfo['customer_name'] ?? 'Client') . ").", $reservation_id, $processor_id);

        $msg = "Paiement enregistré avec succès !";
    }
}

// Delete Payment
if (isset($_GET['delete'])) {
    if (!hasRole('super_admin')) {
        die("Accès refusé. Seul un super administrateur peut supprimer un paiement.");
    }
    
    $id_to_del = $_GET['delete'];
    $resQ = $pdo->prepare("SELECT reservation_id FROM payments WHERE id = ?");
    $resQ->execute([$id_to_del]);
    $res_id = $resQ->fetchColumn();
    
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$id_to_del]);
    
    if ($res_id) {
        $pdo->prepare("UPDATE reservations r SET amount_paid = (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = r.id) WHERE id = ?")->execute([$res_id]);
    }
    
    header("Location: payments.php");
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

// Fetch branches for filter
$branches = [];
if (hasRole('super_admin')) {
    $branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
}

// Get Reservations for Dropdown
$reservations = $pdo->query("SELECT r.id, r.customer_name, r.total_price, r.amount_paid FROM reservations r WHERE 1=1 $branchSql ORDER BY id DESC")->fetchAll();

$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(r.customer_name LIKE ? OR p.transaction_ref LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}
if (!empty($_GET['method'])) {
    $where[] = "p.payment_method = ?";
    $params[] = $_GET['method'];
}
$period = $_GET['period'] ?? '';
$ref_date = $_GET['date'] ?? '';

if (!empty($ref_date)) {
    if ($period === 'week') {
        $where[] = "YEARWEEK(p.created_at, 1) = YEARWEEK(?, 1)";
        $params[] = $ref_date;
    } elseif ($period === 'month') {
        $where[] = "MONTH(p.created_at) = MONTH(?) AND YEAR(p.created_at) = YEAR(?)";
        $params[] = $ref_date;
        $params[] = $ref_date;
    } elseif ($period === 'year') {
        $where[] = "YEAR(p.created_at) = YEAR(?)";
        $params[] = $ref_date;
    } else {
        $where[] = "DATE(p.created_at) = ?";
        $params[] = $ref_date;
    }
}

$whereClause = (!empty($where) ? "WHERE " . implode(" AND ", $where) : "WHERE 1=1") . " $branchSql";
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN reservations r ON p.reservation_id = r.id $whereClause");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);
$payments = $pdo->prepare("SELECT p.*, r.customer_name FROM payments p JOIN reservations r ON p.reservation_id = r.id $whereClause ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
$payments->execute($params);
$payments = $payments->fetchAll();
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
    <title>Paiements - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=7">
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
        <a href="returns.php"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
        <a href="payments.php" class="active"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0;">Journal des Paiements</h2>
                <p style="color: #666; margin-top: 5px;">Historique complet des transactions financières.</p>
            </div>
            <button onclick="newPayment()" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">+ Nouveau Paiement</button>
        </div>
        
    <?php if ($msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 25px; padding: 20px;">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <?php if (hasRole('super_admin')): ?>
            <div class="form-group" style="width: 200px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Succursale</label>
                <select name="branch" onchange="this.form.submit()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="all">Toutes les branches</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $active_branch == $b['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Client ou Réf..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div class="form-group" style="width: 180px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Méthode</label>
                <select name="method" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="">Toutes</option>
                    <option value="cash" <?php echo ($_GET['method'] ?? '') == 'cash' ? 'selected' : ''; ?>>CASH</option>
                    <option value="orange_money" <?php echo ($_GET['method'] ?? '') == 'orange_money' ? 'selected' : ''; ?>>Orange Money</option>
                    <option value="moov_money" <?php echo ($_GET['method'] ?? '') == 'moov_money' ? 'selected' : ''; ?>>Moov Money</option>
                    <option value="card" <?php echo ($_GET['method'] ?? '') == 'card' ? 'selected' : ''; ?>>Card</option>
                </select>
            </div>
            <div class="form-group" style="width: 150px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Période</label>
                <select name="period" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="day" <?php echo ($_GET['period'] ?? '') == 'day' ? 'selected' : ''; ?>>Jour précis</option>
                    <option value="week" <?php echo ($_GET['period'] ?? '') == 'week' ? 'selected' : ''; ?>>Semaine</option>
                    <option value="month" <?php echo ($_GET['period'] ?? '') == 'month' ? 'selected' : ''; ?>>Mois complet</option>
                    <option value="year" <?php echo ($_GET['period'] ?? '') == 'year' ? 'selected' : ''; ?>>Année</option>
                </select>
            </div>
            <div class="form-group" style="width: 180px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Date Réf.</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                <a href="payments.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #eee;">
                        <th style="padding: 15px;">Date</th>
                        <th style="padding: 15px;">Réservation</th>
                        <th style="padding: 15px;">Client</th>
                        <th style="padding: 15px;">Méthode</th>
                        <th style="padding: 15px;">Montant</th>
                        <th style="padding: 15px;">Référence</th>
                        <th style="padding: 15px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr style="border-bottom: 1px solid #f9f9f9;">
                        <td style="padding: 15px;"><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                        <td style="padding: 15px;">#<?php echo $p['reservation_id']; ?></td>
                        <td style="padding: 15px;"><?php echo htmlspecialchars($p['customer_name']); ?></td>
                        <td style="padding: 15px;"><span class="method-tag"><?php echo strtoupper($p['payment_method']); ?></span></td>
                        <td style="padding: 15px; font-weight: 700; color: #166534;"><?php echo number_format($p['amount'], 0); ?> <?php echo getCurrency(); ?></td>
                        <td style="padding: 15px; font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($p['transaction_ref']); ?></td>
                        <td style="padding: 15px;">
                            <button onclick="editPayment(<?php echo htmlspecialchars(json_encode($p)); ?>)" style="background:none; border:none; color: #4338ca; cursor: pointer; margin-right: 10px;"><i class="fas fa-edit"></i></button>
                            <?php if (hasRole('super_admin')): ?>
                            <a href="?delete=<?php echo $p['id']; ?>" onclick="return confirm('Confirmer la suppression de ce paiement ?')" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
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
    document.getElementById('modalTitle').innerText = 'Nouveau Paiement';
    document.getElementById('paymentModal').style.display = 'flex';
}

function editPayment(p) {
    document.getElementById('payment_id').value = p.id;
    document.getElementById('payment_res').value = p.reservation_id;
    document.getElementById('payment_amount').value = p.amount;
    document.getElementById('payment_method').value = p.payment_method;
    document.getElementById('payment_ref').value = p.transaction_ref || '';
    document.getElementById('modalTitle').innerText = 'Modifier le Paiement';
    document.getElementById('paymentModal').style.display = 'flex';
}
</script>
</body>
</html>

