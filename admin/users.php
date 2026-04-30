<?php
// admin/users.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$current_branch_id = $_SESSION['active_branch'] ?? $_SESSION['branch_id'];
$isGlobalView = (empty($current_branch_id) || $current_branch_id == 'all');

$msg = '';
$error = '';

// AJAX Handler for User Activity
if (isset($_GET['ajax_user_activity'])) {
    header('Content-Type: application/json');
    $user_id = (int)$_GET['ajax_user_activity'];
    
    $stmt1 = $pdo->prepare("SELECT 'Réservation' as type, id as ref_id, total_price as amount, created_at, status FROM reservations WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt1->execute([$user_id]);
    $res = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt2 = $pdo->prepare("SELECT 'Encaissement' as type, reservation_id as ref_id, amount, created_at, payment_method as status FROM payments WHERE processed_by = ? ORDER BY created_at DESC LIMIT 20");
    $stmt2->execute([$user_id]);
    $pay = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    $combined = array_merge($res, $pay);
    usort($combined, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    $combined = array_slice($combined, 0, 20);
    echo json_encode($combined);
    exit;
}

// Add/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $password = $_POST['password'] ?? '';
    
    $branch_id = $_POST['branch_id'];
    if (!hasRole('super_admin')) {
        $branch_id = $current_branch_id;
        if (!in_array($role, ['mini_admin', 'receptionist'])) {
            $role = 'receptionist';
        }
    } else {
        if (empty($branch_id) || $role === 'super_admin' || $role === 'client') {
            $branch_id = null;
        }
    }

    // Uniqueness validation
    if ($id) {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (phone = ? OR (email != '' AND email = ?)) AND id != ?");
        $stmt_check->execute([$phone, $email, $id]);
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (phone = ? OR (email != '' AND email = ?))");
        $stmt_check->execute([$phone, $email]);
    }

    if ($stmt_check->fetch()) {
        $error = "Erreur: Ce numéro de téléphone ou cet email est déjà pris par un autre compte.";
    } else {
        if ($id) {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, branch_id = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $branch_id, $hashed, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, branch_id = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $branch_id, $id]);
            }
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, role, branch_id, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $role, $branch_id, $hashed]);
        }
        $msg = "Utilisateur enregistré !";
    }
}

// Delete
if (isset($_GET['delete'])) {
    try {
        if (!hasRole('super_admin')) {
            die("Non autorisé. Seul le Super Admin peut supprimer un utilisateur.");
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
        header("Location: users.php");
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Erreur : Impossible de supprimer cet utilisateur car il est lié à des réservations ou d'autres données.";
        } else {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}
if (!empty($_GET['role'])) {
    $where[] = "u.role = ?";
    $params[] = $_GET['role'];
}
if (!hasRole('super_admin')) {
    $where[] = "u.branch_id = ?";
    $params[] = $current_branch_id;
    $where[] = "u.role != 'super_admin'";
} elseif (!empty($_GET['branch'])) {
    $where[] = "u.branch_id = ?";
    $params[] = $_GET['branch'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereClause");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Users & Branches
$users = $pdo->prepare("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id $whereClause ORDER BY u.role, u.name LIMIT $limit OFFSET $offset");
$users->execute($params);
$users = $users->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();

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
    <title>Utilisateurs - Sam SuperAdmin</title>
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
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="transfers.php"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="branches.php"><i class="fas fa-building"></i> &nbsp; Succursales</a>
        <?php endif; ?>
        <a href="users.php" class="active"><i class="fas fa-users-cog"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>
        <?php if (hasRole('super_admin')): ?>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <h2 style="margin: 0;"><?php echo hasRole('super_admin') ? 'Gestion du Personnel' : 'Mon Personnel'; ?></h2>
            <button onclick="openModal()" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">+ Nouvel Utilisateur</button>
        </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 25px; padding: 20px;">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <?php if (hasRole('super_admin')): ?>
            <div class="form-group" style="width: 200px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Succursale</label>
                <select name="branch" onchange="this.form.submit()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="">Toutes les succursales</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($_GET['branch'] ?? '') == $b['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Nom, Email ou Tel..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div class="form-group" style="width: 200px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Rôle</label>
                <select name="role" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="">Tous les rôles</option>
                    <option value="super_admin" <?php echo ($_GET['role'] ?? '') == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                    <option value="mini_admin" <?php echo ($_GET['role'] ?? '') == 'mini_admin' ? 'selected' : ''; ?>>Mini Admin</option>
                    <option value="receptionist" <?php echo ($_GET['role'] ?? '') == 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                    <option value="client" <?php echo ($_GET['role'] ?? '') == 'client' ? 'selected' : ''; ?>>Client</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                <a href="users.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
            </div>
        </form>
    </div>

    <?php if ($msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #eee; background: #fafafa;">
                        <th style="padding: 15px; font-weight: 600; color: #64748b;">Date d'ajout</th>
                        <th style="padding: 15px; font-weight: 600; color: #64748b;">Utilisateur</th>
                        <th style="padding: 15px; font-weight: 600; color: #64748b;">Contact</th>
                        <th style="padding: 15px; font-weight: 600; color: #64748b;">Rôle</th>
                        <th style="padding: 15px; font-weight: 600; color: #64748b;">Succursale</th>
                        <th style="padding: 15px; font-weight: 600; color: #64748b; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" style="padding: 30px; text-align: center; color: #999;">Aucun utilisateur trouvé.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): 
                        $colors = [
                            'super_admin' => ['bg' => '#fef2f2', 'text' => '#dc2626', 'icon' => 'fa-crown', 'label' => 'Super Admin'],
                            'mini_admin'  => ['bg' => '#e0e7ff', 'text' => '#4f46e5', 'icon' => 'fa-user-shield', 'label' => 'Mini Admin'],
                            'receptionist'=> ['bg' => '#e6fffa', 'text' => '#0d9488', 'icon' => 'fa-concierge-bell', 'label' => 'Réceptionniste'],
                            'client'      => ['bg' => '#f3f4f6', 'text' => '#4b5563', 'icon' => 'fa-user', 'label' => 'Client']
                        ];
                        $r = $colors[$u['role']] ?? $colors['client'];
                    ?>
                    <tr style="border-bottom: 1px solid #f9f9f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 15px; color: #64748b; font-size: 0.9rem;"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                        <td style="padding: 15px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if (!empty($u['profile_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($u['profile_picture']); ?>" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 35px; height: 35px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                            </div>
                        </td>
                        <td style="padding: 15px;">
                            <div style="font-size: 0.85rem; color: #475569; margin-bottom: 3px;"><i class="fas fa-envelope" style="color: #cbd5e1; width: 15px;"></i> <?php echo htmlspecialchars($u['email'] ?: '-'); ?></div>
                            <div style="font-size: 0.85rem; color: #475569;"><i class="fas fa-phone-alt" style="color: #cbd5e1; width: 15px;"></i> <?php echo htmlspecialchars($u['phone'] ?: '-'); ?></div>
                        </td>
                        <td style="padding: 15px;">
                            <span style="background: <?php echo $r['bg']; ?>; color: <?php echo $r['text']; ?>; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas <?php echo $r['icon']; ?>"></i> <?php echo $r['label']; ?>
                            </span>
                        </td>
                        <td style="padding: 15px; color: #475569; font-size: 0.9rem;">
                            <?php if(in_array($u['role'], ['mini_admin', 'receptionist'])): ?>
                                <strong><?php echo htmlspecialchars($u['branch_name'] ?? '-'); ?></strong>
                            <?php else: ?>
                                <span style="color: #cbd5e1;">Global</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px; text-align: right;">
                            <button onclick='viewUser(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)' style="background:none; border:none; color: #0ea5e9; cursor: pointer; margin-right: 10px;" title="Voir"><i class="fas fa-eye"></i></button>
                            <button onclick='openModal(<?php echo json_encode($u['id']); ?>, <?php echo json_encode($u['name']); ?>, <?php echo json_encode($u['email']); ?>, <?php echo json_encode($u['phone']); ?>, <?php echo json_encode($u['role']); ?>, <?php echo json_encode($u['branch_id']); ?>)' style="background:none; border:none; color: #4338ca; cursor: pointer; margin-right: 10px;" title="Modifier"><i class="fas fa-edit"></i></button>
                            <?php if ($u['id'] != $_SESSION['user_id'] && hasRole('super_admin')): ?>
                            <a href="?delete=<?php echo $u['id']; ?>" onclick="return confirm('Confirmer la suppression de cet utilisateur ?')" style="color: #ef4444;" title="Supprimer"><i class="fas fa-trash"></i></a>
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

<!-- User Modal -->
<div id="userModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; position: relative;">
        <h3 id="modalTitle">Utilisateur</h3>
        <button onclick="closeModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="id" id="user_id">
            <div class="form-group">
                <label>Nom complet</label>
                <input type="text" name="name" id="name" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="email" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                </div>
                <div class="form-group">
                    <label>Rôle</label>
                    <select name="role" id="role" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;" onchange="toggleBranchField()">
                        <?php if (hasRole('super_admin')): ?>
                        <option value="client">Client</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="mini_admin">Mini Admin</option>
                        <option value="super_admin">Super Admin</option>
                        <?php else: ?>
                        <option value="receptionist">Receptionist</option>
                        <option value="mini_admin">Mini Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <?php if (hasRole('super_admin')): ?>
            <div class="form-group" id="branchGroup">
                <label>Succursale</label>
                <select name="branch_id" id="branch_id" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    <option value="">-- Aucune --</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="branch_id" id="branch_id" value="<?php echo $current_branch_id; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="phone" id="phone" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
            </div>
            <div class="form-group">
                <label>Mot de passe (Laisser vide si inchangé)</label>
                <input type="password" name="password" id="password" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">
            </div>
            <button type="submit" name="save_user" class="contact-btn" style="width:100%; border:none; padding:15px; cursor: pointer;">Valider</button>
        </form>
    </div>
</div>

<!-- View User Modal -->
<div id="viewUserModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; position: relative;">
        <h3 style="margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 15px;">Détails de l'Utilisateur</h3>
        <button onclick="document.getElementById('viewUserModal').style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <div style="text-align: center; margin-top: 20px; margin-bottom: 20px;">
            <img id="view_avatar" src="" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; display: none; margin: 0 auto;">
            <div id="view_avatar_placeholder" style="width: 100px; height: 100px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #94a3b8; margin: 0 auto;">
                <i class="fas fa-user"></i>
            </div>
            <h2 id="view_name" style="margin: 15px 0 5px 0; color: #1e293b;"></h2>
            <span id="view_role" style="background: #f3f4f6; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; color: #4b5563;"></span>
        </div>

        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; display: flex; flex-direction: column; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 30px; text-align: center; color: #94a3b8;"><i class="fas fa-envelope"></i></div>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Email</div>
                    <div id="view_email" style="font-weight: 600; color: #334155;"></div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 30px; text-align: center; color: #94a3b8;"><i class="fas fa-phone-alt"></i></div>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Téléphone</div>
                    <div id="view_phone" style="font-weight: 600; color: #334155;"></div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;" id="view_branch_container">
                <div style="width: 30px; text-align: center; color: #94a3b8;"><i class="fas fa-building"></i></div>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Succursale</div>
                    <div id="view_branch" style="font-weight: 600; color: #334155;"></div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 30px; text-align: center; color: #94a3b8;"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Date d'inscription</div>
                    <div id="view_date" style="font-weight: 600; color: #334155;"></div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <h4 style="margin-bottom: 10px; color: #475569; font-size: 0.9rem; text-transform: uppercase;">Historique Récent</h4>
            <div id="view_transactions" style="max-height: 200px; overflow-y: auto; background: #f8fafc; border-radius: 12px; padding: 15px; font-size: 0.85rem; border: 1px solid #e2e8f0;">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js?v=7"></script>
<script>
    function toggleBranchField() {
        var role = document.getElementById('role').value;
        var branchGroup = document.getElementById('branchGroup');
        if (branchGroup) {
            if(role === 'super_admin' || role === 'client') {
                branchGroup.style.display = 'none';
            } else {
                branchGroup.style.display = 'block';
            }
        }
    }

    function openModal(id = '', name = '', email = '', phone = '', role = 'client', branch = '') {
        document.getElementById('user_id').value = id;
        document.getElementById('name').value = name;
        document.getElementById('email').value = email;
        document.getElementById('phone').value = phone;
        document.getElementById('role').value = role;
        document.getElementById('branch_id').value = branch;
        document.getElementById('modalTitle').innerText = id ? 'Modifier l\'Utilisateur' : 'Ajouter un Utilisateur';
        document.getElementById('userModal').style.display = 'flex';
        toggleBranchField();
    }

    function viewUser(u) {
        document.getElementById('view_name').innerText = u.name;
        document.getElementById('view_email').innerText = u.email || 'Non renseigné';
        document.getElementById('view_phone').innerText = u.phone || 'Non renseigné';
        
        let roleLabels = {
            'super_admin': 'Super Admin',
            'mini_admin': 'Mini Admin',
            'receptionist': 'Réceptionniste',
            'client': 'Client'
        };
        document.getElementById('view_role').innerText = roleLabels[u.role] || u.role;
        
        if (u.profile_picture) {
            document.getElementById('view_avatar').src = '../' + u.profile_picture;
            document.getElementById('view_avatar').style.display = 'block';
            document.getElementById('view_avatar_placeholder').style.display = 'none';
        } else {
            document.getElementById('view_avatar').style.display = 'none';
            document.getElementById('view_avatar_placeholder').style.display = 'flex';
        }

        if (u.role === 'mini_admin' || u.role === 'receptionist') {
            document.getElementById('view_branch_container').style.display = 'flex';
            document.getElementById('view_branch').innerText = u.branch_name || 'Aucune succursale assignée';
        } else {
            document.getElementById('view_branch_container').style.display = 'none';
        }

        let dateObj = new Date(u.created_at);
        document.getElementById('view_date').innerText = dateObj.toLocaleDateString('fr-FR');
        
        document.getElementById('view_transactions').innerHTML = '<div style="text-align:center; color:#94a3b8; padding:20px;">Chargement de l\'historique...</div>';
        
        fetch('users.php?ajax_user_activity=' + u.id)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    document.getElementById('view_transactions').innerHTML = '<div style="text-align:center; color:#94a3b8; padding:10px;">Aucun historique récent.</div>';
                    return;
                }
                
                let html = '<table style="width: 100%; border-collapse: collapse;">';
                data.forEach(item => {
                    let date = new Date(item.created_at).toLocaleString('fr-FR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});
                    let color = item.type === 'Réservation' ? '#4f46e5' : '#059669';
                    let amountStr = new Intl.NumberFormat('fr-FR').format(item.amount) + ' F';
                    
                    html += `<tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 0; color: #64748b;">${date}</td>
                        <td style="padding: 8px 0; font-weight: 600; color: ${color};">${item.type} #${item.ref_id}</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700; color: #334155;">${amountStr}</td>
                    </tr>`;
                });
                html += '</table>';
                document.getElementById('view_transactions').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('view_transactions').innerHTML = '<div style="text-align:center; color:#ef4444; padding:10px;">Erreur de chargement.</div>';
            });
        
        document.getElementById('viewUserModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('userModal').style.display = 'none';
    }
    
    toggleBranchField();
</script>

</body>
</html>
