<?php
// admin/logs.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Handling branch filtering specifically for super_admin
$isGlobalView = false;
$current_branch_id = null;

if (hasRole('super_admin')) {
    if (isset($_GET['branch'])) {
        if ($_GET['branch'] === 'all') {
            unset($_SESSION['active_branch']);
        } else {
            $_SESSION['active_branch'] = (int)$_GET['branch'];
        }
    }
    $current_branch_id = $_SESSION['active_branch'] ?? null;
    $isGlobalView = empty($current_branch_id);
} else {
    $current_branch_id = $_SESSION['branch_id'];
    $isGlobalView = false;
}

$where = [];
$params = [];

if (!$isGlobalView) {
    $where[] = "(al.branch_id = ? OR u.branch_id = ?)";
    $params[] = $current_branch_id;
    $params[] = $current_branch_id;
}

if (!empty($_GET['action_filter'])) {
    $where[] = "al.action = ?";
    $params[] = $_GET['action_filter'];
}

if (!empty($_GET['user_filter'])) {
    $where[] = "al.user_id = ?";
    $params[] = $_GET['user_filter'];
}

if (!empty($_GET['date_start'])) {
    $where[] = "DATE(al.created_at) >= ?";
    $params[] = $_GET['date_start'];
}

if (!empty($_GET['date_end'])) {
    $where[] = "DATE(al.created_at) <= ?";
    $params[] = $_GET['date_end'];
}

if (!empty($_GET['search'])) {
    $where[] = "al.description LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id $whereClause");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$query = "SELECT al.*, u.name as user_name, u.role, b.name as branch_name 
          FROM activity_logs al 
          LEFT JOIN users u ON al.user_id = u.id 
          LEFT JOIN branches b ON al.branch_id = b.id 
          $whereClause 
          ORDER BY al.created_at DESC 
          LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// For filters
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$users_query = "SELECT id, name FROM users";
if (!$isGlobalView) {
    $users_query .= " WHERE branch_id = '$current_branch_id'";
}
$users_query .= " ORDER BY name";
$users_filter_list = $pdo->query($users_query)->fetchAll();

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
    <title>Journal d'Activité - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
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
        <a href="returns.php"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
            <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
            <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
            <a href="profile.php"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>

            <?php if (hasRole('super_admin')): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e214a4ff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;">Super Admin</div>
                <a href="branches.php"><i class="fas fa-building"></i> &nbsp; Succursales</a>
            <?php endif; ?>
            <?php if (hasRole('super_admin') || hasRole('mini_admin')): ?>
                <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>
                <a href="logs.php" class="active"><i class="fas fa-history"></i> &nbsp; Journal d'Activité</a>
            <?php endif; ?>
            <?php if (hasRole('super_admin')): ?>
                <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
            <?php endif; ?>

            <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
        </div>

        <div class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-history" style="color: var(--primary-blue);"></i> Journal d'Activité Sécurisé</h2>
                    <p style="color: #666; margin-top: 5px;">Trace inaltérable de toutes les opérations critiques du système.</p>
                </div>
                <?php if (hasRole('super_admin')): ?>
                    <div style="background: white; border-radius: 8px; border: 1px solid #e5e7eb; padding: 2px 10px; display: flex; align-items: center;">
                        <i class="fas fa-building" style="color: #6b7280; margin-right: 10px;"></i>
                        <select onchange="window.location.href='?branch=' + this.value" style="border: none; outline: none; padding: 8px 0; font-family: inherit; font-size: 0.95rem; color: #1f2937; background: transparent; cursor: pointer;">
                            <option value="all" <?php echo $current_branch_id === null ? 'selected' : ''; ?>>Toutes les succursales</option>
                            <?php 
                            $branches_list = $pdo->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();
                            foreach($branches_list as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo $current_branch_id == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Filter Bar -->
            <div class="card" style="margin-bottom: 30px; padding: 20px;">
                <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                    <div class="form-group" style="width: 200px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Action</label>
                        <select name="action_filter" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                            <option value="">Toutes les actions</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act); ?>" <?php echo ($_GET['action_filter'] ?? '') === $act ? 'selected' : ''; ?>><?php echo htmlspecialchars($act); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="width: 200px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Utilisateur</label>
                        <select name="user_filter" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                            <option value="">Tous les utilisateurs</option>
                            <?php foreach ($users_filter_list as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($_GET['user_filter'] ?? '') == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="width: 150px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Date début</label>
                        <input type="date" name="date_start" value="<?php echo htmlspecialchars($_GET['date_start'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>
                    
                    <div class="form-group" style="width: 150px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Date fin</label>
                        <input type="date" name="date_end" value="<?php echo htmlspecialchars($_GET['date_end'] ?? ''); ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>

                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche Description</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Mots-clés..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                        <a href="logs.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                                <th style="padding: 15px;">Date & Heure</th>
                                <th style="padding: 15px;">Utilisateur</th>
                                <th style="padding: 15px;">Succursale</th>
                                <th style="padding: 15px;">Action</th>
                                <th style="padding: 15px; width: 40%;">Description</th>
                                <th style="padding: 15px;">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr style="border-bottom: 1px solid #f9f9f9;">
                                    <td style="padding: 15px; color: #64748b; font-size: 0.9rem;">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td style="padding: 15px;">
                                        <?php if ($log['user_name']): ?>
                                            <strong><?php echo htmlspecialchars($log['user_name']); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($log['role']); ?></small>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;"><em>Inconnu / Système</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 15px; font-size: 0.9rem;">
                                        <?php echo $log['branch_name'] ? htmlspecialchars($log['branch_name']) : '<span style="color: #94a3b8;"><em>Général</em></span>'; ?>
                                    </td>
                                    <td style="padding: 15px;">
                                        <?php 
                                            $actionColor = '#64748b';
                                            if (strpos($log['action'], 'DELETE') !== false) $actionColor = '#ef4444';
                                            elseif (strpos($log['action'], 'UPDATE') !== false) $actionColor = '#f59e0b';
                                            elseif (strpos($log['action'], 'CREATE') !== false || strpos($log['action'], 'ADD') !== false) $actionColor = '#10b981';
                                            elseif (strpos($log['action'], 'LOGIN') !== false) $actionColor = '#3b82f6';
                                        ?>
                                        <span style="background: <?php echo $actionColor; ?>20; color: <?php echo $actionColor; ?>; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.5px;">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px; font-size: 0.95rem; line-height: 1.4;">
                                        <?php echo htmlspecialchars($log['description']); ?>
                                    </td>
                                    <td style="padding: 15px; color: #94a3b8; font-family: monospace; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" style="padding: 20px; text-align: center; color: #94a3b8;">Aucun journal d'activité trouvé.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; padding-bottom: 20px;">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page - 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;"><i class="fas fa-chevron-left"></i> Précédent</a>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($total_pages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
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

    <script src="../assets/js/admin.js?v=2"></script>
</body>
</html>
