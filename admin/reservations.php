<?php
// admin/reservations.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/engine.php';
requireAdmin();
// Active branch handling for Super Admin
if (hasRole('super_admin') && isset($_GET['branch'])) {
    if ($_GET['branch'] === 'all') {
        unset($_SESSION['active_branch']);
    } else {
        $_SESSION['active_branch'] = (int)$_GET['branch'];
    }
}
$active_branch = hasRole('super_admin') ? ($_SESSION['active_branch'] ?? null) : $_SESSION['branch_id'];
$branchSql = getBranchSqlFilter();

// Fetch branches for filter
$branches = [];
if (hasRole('super_admin')) {
    $branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
}

// Handle single or bulk deletion
$deleted_count = 0;
if (isset($_POST['bulk_delete']) && hasRole('super_admin')) {
    $ids = $_POST['reservation_ids'] ?? [];
    if (!empty($ids)) {
        foreach ($ids as $res_id) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM stock_log WHERE reservation_id = ?")->execute([$res_id]);
                $pdo->prepare("DELETE FROM payments WHERE reservation_id = ?")->execute([$res_id]);
                $pdo->prepare("DELETE FROM reservation_items WHERE reservation_id = ?")->execute([$res_id]);
                $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$res_id]);
                $pdo->commit();
                
                logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'DELETE_RESERVATION', "Réservation #$res_id supprimée en masse.");
                
                $deleted_count++;
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
        header("Location: reservations.php?msg=deleted&count=" . $deleted_count);
        exit;
    }
} elseif (isset($_GET['delete']) && hasRole('super_admin')) {
    $res_id = $_GET['delete'];
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM stock_log WHERE reservation_id = ?")->execute([$res_id]);
        $pdo->prepare("DELETE FROM payments WHERE reservation_id = ?")->execute([$res_id]);
        $pdo->prepare("DELETE FROM reservation_items WHERE reservation_id = ?")->execute([$res_id]);
        $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$res_id]);
        $pdo->commit();
        
        logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'DELETE_RESERVATION', "Réservation #$res_id supprimée.");
        
        header("Location: reservations.php?msg=deleted&count=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur de suppression : " . $e->getMessage());
    }
}

// Build Filter Query
$where = [];
$params = [];

if (!empty($_GET['status'])) {
    $where[] = "status = ?";
    $params[] = $_GET['status'];
}

$period = $_GET['period'] ?? '';
$ref_date = $_GET['date'] ?? '';

if (!empty($ref_date)) {
    if ($period === 'week') {
        $where[] = "YEARWEEK(event_date, 1) = YEARWEEK(?, 1)";
        $params[] = $ref_date;
    } elseif ($period === 'month') {
        $where[] = "MONTH(event_date) = MONTH(?) AND YEAR(event_date) = YEAR(?)";
        $params[] = $ref_date;
        $params[] = $ref_date;
    } elseif ($period === 'year') {
        $where[] = "YEAR(event_date) = YEAR(?)";
        $params[] = $ref_date;
    } else {
        $where[] = "DATE(event_date) = ?";
        $params[] = $ref_date;
    }
}

if (!empty($_GET['search'])) {
    $where[] = "(customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}

$promo_info = null;
if (!empty($_GET['promo_id'])) {
    $where[] = "promo_code_id = ?";
    $params[] = $_GET['promo_id'];
    
    $stmt = $pdo->prepare("SELECT code FROM promo_codes WHERE id = ?");
    $stmt->execute([$_GET['promo_id']]);
    $promo_info = $stmt->fetchColumn();
}

$extraWhere = !empty($where) ? " AND " . implode(" AND ", $where) : "";
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE 1=1 $branchSql $extraWhere");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);
$reservations = $pdo->prepare("SELECT * FROM reservations WHERE 1=1 $branchSql $extraWhere ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$reservations->execute($params);
$reservations = $reservations->fetchAll();
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
    <title>Reservations - Sam Admin</title>
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
        <a href="reservations.php" class="active"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="returns.php"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
            <a href="transfers.php"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0;">Toutes les Réservations <?php echo $promo_info ? "<span style='color: var(--primary-blue); font-size: 1.2rem;'><br><i class='fas fa-tag'></i> Code Promo: " . htmlspecialchars($promo_info) . "</span>" : ""; ?></h2>
                <p style="color: #666; margin-top: 5px;">Gestion centralisée de toutes les réservations du système.</p>
            </div>
            <a href="create_reservation.php" class="contact-btn" style="padding: 10px 20px; border: none; text-decoration: none;">+ Nouvelle Réservation</a>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 30px; padding: 20px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <?php if (!empty($_GET['promo_id'])): ?>
                    <input type="hidden" name="promo_id" value="<?php echo htmlspecialchars($_GET['promo_id']); ?>">
                <?php endif; ?>
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
                    <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche (Nom/Tel)</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Rechercher..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div class="form-group" style="width: 180px;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Statut</label>
                    <select name="status" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($_GET['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="in_preparation" <?php echo ($_GET['status'] ?? '') == 'in_preparation' ? 'selected' : ''; ?>>In Preparation</option>
                        <option value="completed" <?php echo ($_GET['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="returned" <?php echo ($_GET['status'] ?? '') == 'returned' ? 'selected' : ''; ?>>Returned</option>
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
                    <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Date Réf. (Event)</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                    <a href="reservations.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <form method="POST">
                <?php if (hasRole('super_admin')): ?>
                    <button type="submit" name="bulk_delete" onclick="return confirm('Confirmez-vous la suppression des réservations sélectionnées ? Cette action est irréversible.')" class="btn-reserve" style="background:#ef4444; border:none; padding:10px 15px; margin-bottom:15px; cursor:pointer;"><i class="fas fa-trash"></i> Supprimer la sélection</button>
                <?php endif; ?>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #eee;">
                        <?php if (hasRole('super_admin')): ?>
                        <th style="padding: 15px; width: 40px;"><input type="checkbox" id="selectAll"></th>
                        <?php endif; ?>
                        <th style="padding: 15px;">Date</th>
                        <th style="padding: 15px;">Client</th>
                        <th style="padding: 15px;">Total</th>
                        <th style="padding: 15px;">Payé</th>
                        <th style="padding: 15px;">Statut</th>
                        <th style="padding: 15px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr style="border-bottom: 1px solid #f9f9f9;">
                        <?php if (hasRole('super_admin')): ?>
                        <td style="padding: 15px;">
                            <input type="checkbox" name="reservation_ids[]" value="<?php echo $r['id']; ?>" class="row-checkbox">
                        </td>
                        <?php endif; ?>
                        <td style="padding: 15px;"><?php echo date('d/m/y', strtotime($r['event_date'])); ?></td>
                        <td style="padding: 15px;">
                            <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($r['customer_phone']); ?></small>
                        </td>
                        <td style="padding: 15px;"><?php echo number_format($r['total_price'], 0); ?> F</td>
                        <td style="padding: 15px;"><?php echo number_format($r['amount_paid'], 0); ?> F</td>
                        <td style="padding: 15px;"><span class="status-badge <?php echo $r['status']; ?>"><?php echo strtoupper($r['status']); ?></span></td>
                        <td style="padding: 15px;">
                            <a href="manage.php?id=<?php echo $r['id']; ?>" style="color: #4338ca; margin-right: 10px;" title="Voir"><i class="fas fa-eye"></i></a>
                            <a href="manage.php?id=<?php echo $r['id']; ?>&edit=1" style="color: #6366f1; margin-right: 15px;" title="Modifier"><i class="fas fa-edit"></i></a>
                            <?php if (hasRole('super_admin')): ?>
                                <a href="?delete=<?php echo $r['id']; ?>" onclick="return confirm('Confirmez-vous la suppression définitive ?')" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
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

<script src="../assets/js/admin.js?v=7"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function(e) {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
        });
    }
});
</script>
</body>
</html>

