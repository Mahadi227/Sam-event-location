<?php
// admin/users.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

$msg = '';
$error = '';

// Add/Edit User
if (isset($_POST['save_user'])) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $password = $_POST['password'];

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
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $hashed, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $id]);
            }
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, role, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $role, $hashed]);
        }
        $msg = "Utilisateur enregistré !";
    }
}

// Delete
if (isset($_GET['delete'])) {
    try {
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
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
    $params[] = "%".$_GET['search']."%";
}
if (!empty($_GET['role'])) {
    $where[] = "role = ?";
    $params[] = $_GET['role'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereClause");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);
$users = $pdo->prepare("SELECT * FROM users $whereClause ORDER BY role, name LIMIT $limit OFFSET $offset");
$users->execute($params);
$users = $users->fetchAll();
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
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
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
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <a href="users.php" class="active"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
        <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <h2 style="margin: 0;">Gestion du Personnel</h2>
            <button onclick="document.getElementById('userModal').style.display='flex'" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">+ Nouvel Utilisateur</button>
        </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 25px; padding: 20px;">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
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

    <div class="card">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #eee;">
                        <th style="padding: 15px;">Nom</th>
                        <th style="padding: 15px;">Email</th>
                        <th style="padding: 15px;">Rôle</th>
                        <th style="padding: 15px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid #f9f9f9;">
                        <td style="padding: 15px;"><strong><?php echo htmlspecialchars($u['name']); ?></strong></td>
                        <td style="padding: 15px;"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td style="padding: 15px;"><span class="role-badge <?php echo $u['role']; ?>"><?php echo str_replace('_', ' ', $u['role']); ?></span></td>
                        <td style="padding: 15px;">
                            <button onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)" style="background:none; border:none; color: #4338ca; cursor: pointer; margin-right: 10px;" title="Modifier"><i class="fas fa-edit"></i></button>
                            <a href="user_history.php?id=<?php echo $u['id']; ?>" style="color: #6366f1; margin-right: 10px;" title="Historique"><i class="fas fa-history"></i></a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?php echo $u['id']; ?>" onclick="return confirm('Supprimer cet utilisateur ?')" style="color: #ef4444;" title="Supprimer"><i class="fas fa-trash"></i></a>
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
        <button onclick="this.parentElement.parentElement.style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="id" id="user_id">
            <div class="form-group">
                <label>Nom complet</label>
                <input type="text" name="name" id="user_name" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="user_email" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                </div>
                <div class="form-group">
                    <label>Rôle</label>
                    <select name="role" id="user_role" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                        <option value="client">Client</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="mini_admin">Mini Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="phone" id="user_phone" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
            </div>
            <div class="form-group">
                <label>Mot de passe (Laisser vide si inchangé)</label>
                <input type="password" name="password" id="user_pass" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">
            </div>
            <button type="submit" name="save_user" class="contact-btn" style="width:100%; border:none; padding:15px; cursor: pointer;">Valider</button>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
<script>
function editUser(user) {
    document.getElementById('user_id').value = user.id;
    document.getElementById('user_name').value = user.name;
    document.getElementById('user_email').value = user.email;
    document.getElementById('user_role').value = user.role;
    document.getElementById('user_phone').value = user.phone;
    document.getElementById('user_pass').value = '';
    document.getElementById('modalTitle').innerText = 'Modifier l\'Utilisateur';
    document.getElementById('userModal').style.display = 'flex';
}
</script>

</body>
</html>

