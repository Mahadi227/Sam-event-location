<?php
// admin/items.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/engine.php';
requireAdmin();

$msg = '';

// Maintenance Actions
if (isset($_POST['maintenance_action'])) {
    $item_id = $_POST['maint_item_id'];
    $qty = (int)$_POST['maint_qty'];
    $action = $_POST['maint_action_type']; // 'mark_damaged' or 'restore'
    
    try {
        updateItemMaintenance($item_id, $qty, $action);
        $action_text = $action === 'mark_damaged' ? 'endommagé' : 'restauré';
        logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'UPDATE_INVENTORY', "{$qty}x Produit #$item_id marqué comme $action_text.");
        $msg = "Action de maintenance effectuée avec succès !";
    } catch (Exception $e) {
        $error = "Erreur de maintenance : " . $e->getMessage();
    }
}

// Add/Edit Item
if (isset($_POST['save_item'])) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $cat_id = $_POST['category_id'];
    $price = $_POST['price'];
    $qty = $_POST['quantity'];
    $status = $_POST['status'];
    $branch_id = $_POST['branch_id'];
    
    // Existing image or explicitly null if not set
    $image_url = !empty($_POST['existing_image']) ? $_POST['existing_image'] : null;
    
    // If delete image checkbox is checked, force null
    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        $image_url = null;
    }

    $upload_dir = '../uploads/items/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = $_FILES['image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            $new_name = uniqid('item_') . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $image_url = 'uploads/items/' . $new_name;
            }
        }
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE items SET name = ?, category_id = ?, branch_id = ?, price_per_day = ?, quantity_total = ?, status = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $cat_id, $branch_id, $price, $qty, $status, $image_url, $id]);
        logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'UPDATE_INVENTORY', "Produit #$id ($name) mis à jour.");
    } else {
        $stmt = $pdo->prepare("INSERT INTO items (name, category_id, branch_id, price_per_day, quantity_total, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $cat_id, $branch_id, $price, $qty, $status, $image_url]);
        $new_id = $pdo->lastInsertId();
        logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'UPDATE_INVENTORY', "Nouveau produit #$new_id ($name) ajouté.");
    }
    $msg = "Produit enregistré avec succes !";
}

// Direct Distribution (Super Admin Only)
if (isset($_POST['fast_distribute']) && hasRole('super_admin')) {
    $source_item_id = $_POST['dist_item_id'];
    $to_branch_id = $_POST['dist_branch_id'];
    $qty_to_move = (int)$_POST['dist_qty'];
    
    $pdo->beginTransaction();
    try {
        // Get source item details
        $stmt_s = $pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmt_s->execute([$source_item_id]);
        $source = $stmt_s->fetch();
        
        if ($source && $source['quantity_total'] >= $qty_to_move && $qty_to_move > 0) {
            if ($source['branch_id'] == $to_branch_id) {
                throw new Exception("Le produit est déjà dans cette branch.");
            }
            
            // Deduct
            $pdo->prepare("UPDATE items SET quantity_total = quantity_total - ? WHERE id = ?")->execute([$qty_to_move, $source_item_id]);
            
            // Check Dest
            $stmt_d = $pdo->prepare("SELECT id FROM items WHERE name = ? AND branch_id = ?");
            $stmt_d->execute([$source['name'], $to_branch_id]);
            $dest_id = $stmt_d->fetchColumn();
            
            if ($dest_id) {
                $pdo->prepare("UPDATE items SET quantity_total = quantity_total + ? WHERE id = ?")->execute([$qty_to_move, $dest_id]);
            } else {
                $pdo->prepare("INSERT INTO items (name, category_id, branch_id, price_per_day, quantity_total, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$source['name'], $source['category_id'], $to_branch_id, $source['price_per_day'], $qty_to_move, $source['status'], $source['image_url']]);
            }
            
            $pdo->commit();
            
            // Notify
            require_once '../includes/notifications.php';
            notifyBranch($to_branch_id, "Nouvel Arrivage", "Allocation directe de {$qty_to_move}x {$source['name']} depuis une autre branch.", "system");
            
            logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'UPDATE_INVENTORY', "Distribution rapide: {$qty_to_move}x {$source['name']} vers branch #$to_branch_id.");
            
            $msg = "Distribution logistique effectuée avec succès !";
        } else {
            $error = "Stock insuffisant ou quantité invalide.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur de distribution : " . $e->getMessage();
    }
}

// Delete
if (isset($_GET['delete'])) {
    $del_id = $_GET['delete'];
    $stmt_name = $pdo->prepare("SELECT name FROM items WHERE id = ?");
    $stmt_name->execute([$del_id]);
    $del_name = $stmt_name->fetchColumn();
    
    $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
    $stmt->execute([$del_id]);
    
    logActivity($_SESSION['user_id'], $_SESSION['branch_id'] ?? null, 'UPDATE_INVENTORY', "Produit #$del_id ($del_name) supprimé.");
    
    header("Location: items.php");
    exit;
}

// Get items and categories
$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "i.name LIKE ?";
    $params[] = "%".$_GET['search']."%";
}
if (!empty($_GET['category'])) {
    $where[] = "i.category_id = ?";
    $params[] = $_GET['category'];
}
if (!empty($_GET['status'])) {
    $where[] = "i.status = ?";
    $params[] = $_GET['status'];
}
$active_branch = getActiveBranch();
if ($active_branch) {
    $where[] = "i.branch_id = ?";
    $params[] = $active_branch;
} elseif (!empty($_GET['branch'])) {
    $where[] = "i.branch_id = ?";
    $params[] = $_GET['branch'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM items i $whereClause");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);
$items = $pdo->prepare("SELECT i.*, c.name as cat_name, b.name as branch_name FROM items i JOIN categories c ON i.category_id = c.id LEFT JOIN branches b ON i.branch_id = b.id $whereClause ORDER BY c.name, i.name LIMIT $limit OFFSET $offset");
$items->execute($params);
$items = $items->fetchAll();
// Inject dynamic daily availability for the UI snapshot
foreach ($items as &$it) {
    $it['quantity_reserved'] = getTodayReservedStock($it['id']);
    $it['quantity_available'] = getAvailableStock($it['id'], date('Y-m-d'));
}
unset($it);
$query_string_params = $_GET;
unset($query_string_params['page']);
$base_url = '?';
if (!empty($query_string_params)) $base_url = '?' . http_build_query($query_string_params) . '&';

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock & Produits - Sam Admin</title>
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
        <a href="items.php" class="active"><i class="fas fa-box"></i> &nbsp; Stock & Produits</a>
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Reservations</a>
        <a href="returns.php"><i class="fas fa-undo"></i> &nbsp; Retours Matériel</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
            <a href="transfers.php"><i class="fas fa-truck-loading"></i> &nbsp; Transferts Stock</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="branches.php"><i class="fas fa-building"></i> &nbsp; branchs</a>
        <?php endif; ?>
        <?php if (hasRole('super_admin') || hasRole('mini_admin')): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; <?php echo hasRole('super_admin') ? 'Utilisateurs' : 'Personnel'; ?></a>
            <a href="logs.php"><i class="fas fa-history"></i> &nbsp; Journal d'Activité</a>
        <?php endif; ?>
        <?php if (hasRole('super_admin')): ?>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Deconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
            <h2 style="margin: 0;">Gestion du Stock</h2>
            <button onclick="newItem()" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">+ Nouveau Produit</button>
            </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 25px; padding: 20px;">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Recherche</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Nom du produit..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            </div>
            <div class="form-group" style="width: 180px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">categorie</label>
                <select name="category" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="">Toutes</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="width: 150px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Statut</label>
                <select name="status" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="">Tous</option>
                    <option value="available" <?php echo ($_GET['status'] ?? '') == 'available' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="out_of_stock" <?php echo ($_GET['status'] ?? '') == 'out_of_stock' ? 'selected' : ''; ?>>Rupture</option>
                </select>
            </div>
            <?php if (hasRole('super_admin')): ?>
            <div class="form-group" style="width: 180px;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Branch</label>
                <select name="branch" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    <option value="">Toutes</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($_GET['branch'] ?? '') == $b['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                <a href="items.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
            </div>
        </form>
    </div>

    <?php if (isset($error) && $error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
        <?php foreach ($items as $it): ?>
            <div style="background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; position: relative;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.05)';">
                
                <!-- Image Header -->
                <div style="height: 180px; width: 100%; position: relative; background: #f8fafc; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($it['image_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($it['image_url']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-image" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <?php endif; ?>
                    
                    <!-- Floating Statut Pill -->
                    <div style="position: absolute; top: 12px; right: 12px; background: <?php echo $it['status'] == 'available' ? '#10b981' : '#ef4444'; ?>; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        <?php echo $it['status'] == 'available' ? 'DISPO' : 'RUPTURE'; ?>
                    </div>
                </div>

                <!-- Body Content -->
                <div style="padding: 20px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--accent-gold); text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($it['cat_name']); ?>
                    </div>
                    <h3 style="margin: 0 0 10px 0; font-size: 1.25rem; color: #1e293b; line-height: 1.3;">
                        <?php echo htmlspecialchars($it['name']); ?>
                    </h3>
                    
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px; font-size: 0.9rem; color: #64748b;">
                        <i class="fas fa-map-marker-alt" style="color: #94a3b8;"></i>
                        <?php echo htmlspecialchars($it['branch_name'] ?? 'Toutes branchs'); ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #f1f5f9; margin-bottom: 10px;">
                        <div>
                            <span style="font-size: 1.2rem; font-weight: 800; color: var(--primary-blue);"><?php echo number_format($it['price_per_day'], 0); ?> <?php echo getCurrency(); ?></span>
                            <span style="font-size: 0.8rem; color: #94a3b8;">/ jour</span>
                        </div>
                        <div style="text-align: right;">
                            <div style="background: <?php echo $it['quantity_available'] < 5 ? '#fee2e2' : '#e0e7ff'; ?>; color: <?php echo $it['quantity_available'] < 5 ? '#ef4444' : '#4338ca'; ?>; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 1rem;">
                                <i class="fas fa-check-circle"></i> <?php echo $it['quantity_available']; ?> Dispo (Auj.)
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; font-size: 0.8rem; text-align: center;">
                        <div style="background: #f1f5f9; padding: 5px; border-radius: 5px; color: #475569;">
                            <div style="font-weight: bold;"><?php echo $it['quantity_total']; ?></div>
                            <div style="font-size: 0.7rem;">Total</div>
                        </div>
                        <div style="background: #fffbeb; padding: 5px; border-radius: 5px; color: #d97706;">
                            <div style="font-weight: bold;"><?php echo $it['quantity_reserved']; ?></div>
                            <div style="font-size: 0.7rem;">Réservé (Auj.)</div>
                        </div>
                        <div style="background: #fef2f2; padding: 5px; border-radius: 5px; color: #ef4444;">
                            <div style="font-weight: bold;"><?php echo $it['quantity_maintenance']; ?></div>
                            <div style="font-size: 0.7rem;">Maint.</div>
                        </div>
                    </div>
                </div>

                <!-- Hover Overlay Actions -->
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 180px; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; gap: 15px; opacity: 0; transition: opacity 0.3s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                    <button onclick="editItem(<?php echo htmlspecialchars(json_encode($it)); ?>)" type="button" style="width: 45px; height: 45px; border-radius: 50%; background: white; color: #4338ca; border: none; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='none'">
                        <i class="fas fa-pen"></i>
                    </button>
                    <!-- Super Admin Only: Distribute -->
                    <?php if (hasRole('super_admin')): ?>
                    <button onclick="distributeItem(<?php echo htmlspecialchars(json_encode($it)); ?>)" type="button" title="Partager Stock" style="width: 45px; height: 45px; border-radius: 50%; background: #fffbeb; color: #d97706; border: none; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='none'">
                        <i class="fas fa-truck-fast"></i>
                    </button>
                    <?php endif; ?>
                    
                    <button onclick="maintenanceItem(<?php echo htmlspecialchars(json_encode($it)); ?>)" type="button" title="Gérer Maintenance" style="width: 45px; height: 45px; border-radius: 50%; background: #fef2f2; color: #ef4444; border: none; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='none'">
                        <i class="fas fa-tools"></i>
                    </button>
                    
                    <!-- Super Admin Only: Delete -->
                    <a href="?delete=<?php echo $it['id']; ?>" onclick="return confirm('Confirmer la suppression ?')" style="width: 45px; height: 45px; border-radius: 50%; background: #ef4444; color: white; border: none; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); text-decoration: none; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='none'">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; padding-bottom: 20px;">
            <?php if ($page > 1): ?>
                <a href="<?php echo htmlspecialchars($base_url . 'page=' . ($page - 1)); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; background: white;"><i class="fas fa-chevron-left"></i> Pr�c�dent</a>
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

<!-- Fast Distribute Modal -->
<div id="distributeModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; position: relative;">
        <h3 style="margin-top:0; color: #d97706;"><i class="fas fa-truck-fast"></i> Partage de Stock Immédiat</h3>
        <p style="color:#666; font-size: 0.9rem; margin-bottom: 20px;">Détachez une partie du stock de <strong id="dist_item_name_display"></strong> vers une autre branch. (Max: <span id="dist_max_qty_display"></span>)</p>
        
        <button onclick="this.parentElement.parentElement.style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <form method="POST" style="margin-top: 10px;">
            <input type="hidden" name="dist_item_id" id="dist_item_id">
            
            <div class="form-group">
                <label>Agence de destination</label>
                <select name="dist_branch_id" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantité à Allouer</label>
                <input type="number" name="dist_qty" id="dist_qty" required min="1" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 25px;">
            </div>
            
            <button type="submit" name="fast_distribute" class="contact-btn" style="width:100%; border:none; padding:15px; cursor: pointer; background: #d97706;">Exécuter le Transfert</button>
        </form>
    </div>
</div>

<!-- Maintenance Modal -->
<div id="maintenanceModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; position: relative;">
        <h3 style="margin-top:0; color: #ef4444;"><i class="fas fa-tools"></i> Gestion Maintenance</h3>
        <p style="color:#666; font-size: 0.9rem; margin-bottom: 20px;">Produit : <strong id="maint_item_name_display"></strong></p>
        
        <button onclick="this.parentElement.parentElement.style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <form method="POST" style="margin-top: 10px;">
            <input type="hidden" name="maint_item_id" id="maint_item_id">
            
            <div class="form-group">
                <label>Action</label>
                <select name="maint_action_type" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    <option value="mark_damaged">Signaler comme endommagé</option>
                    <option value="restore">Restaurer (Réparé)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantité</label>
                <input type="number" name="maint_qty" id="maint_qty" required min="1" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 25px;">
            </div>
            
            <button type="submit" name="maintenance_action" class="contact-btn" style="width:100%; border:none; padding:15px; cursor: pointer; background: #ef4444;">Confirmer</button>
        </form>
    </div>
</div>

<!-- Modal Form -->
<div id="itemModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; position: relative; max-height: 90vh; overflow-y: auto;">
        <h3 id="modalTitle">Produit</h3>
        <button onclick="this.parentElement.parentElement.style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
            <input type="hidden" name="id" id="item_id">
            <input type="hidden" name="existing_image" id="existing_image">
            <div class="form-group">
                <label>Image du produit (.jpg, .png, .webp)</label>
                <input type="file" name="image" id="item_image" class="form-control" accept="image/*" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                <div id="current_image_preview" style="margin-bottom: 15px; display: none;">
                    <img src="" id="preview_img" style="max-height: 100px; border-radius: 8px;">
                    <br>
                    <label style="display:inline-flex; align-items:center; cursor:pointer; color:#ef4444; margin-top:10px;">
                        <input type="checkbox" name="delete_image" id="delete_image" value="1" style="margin-right:5px;">
                        <span style="font-size: 0.9rem; font-weight: bold;">Supprimer l'image actuelle</span>
                    </label>
                </div>
            </div>
            <?php if (hasRole('super_admin')): ?>
            <div class="form-group">
                <label>Assigner à la branch</label>
                <select name="branch_id" id="item_branch" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="branch_id" id="item_branch" value="<?php echo $_SESSION['branch_id'] ?? 1; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Nom du produit</label>
                <input type="text" name="name" id="item_name" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
            </div>
            <div class="form-group">
                <label>categorie</label>
                <select name="category_id" id="item_cat" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Prix / Jour</label>
                    <input type="number" name="price" id="item_price" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                </div>
                <div class="form-group">
                    <label>Stock Total</label>
                    <input type="number" name="quantity" id="item_qty" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
                </div>
            </div>
            <div class="form-group">
                <label>Statut</label>
                <select name="status" id="item_status" class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">
                    <option value="available">Disponible</option>
                    <option value="out_of_stock">Rupture</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <button type="submit" name="save_item" class="contact-btn" style="width:100%; border:none; padding:15px; cursor: pointer;">Valider</button>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js?v=7"></script>
<script>
function newItem() {
    document.getElementById('item_id').value = '';
    document.getElementById('existing_image').value = '';
    document.getElementById('item_name').value = '';
    document.getElementById('item_cat').value = '';
    
    const branchField = document.getElementById('item_branch');
    if (branchField && branchField.tagName === 'SELECT') {
        branchField.selectedIndex = 0;
    }
    
    document.getElementById('item_price').value = '';
    document.getElementById('item_qty').value = '';
    document.getElementById('item_status').value = 'available';
    document.getElementById('modalTitle').innerText = 'Nouveau Produit';
    
    document.getElementById('current_image_preview').style.display = 'none';
    document.getElementById('item_image').value = '';
    document.getElementById('delete_image').checked = false;
    
    document.getElementById('itemModal').style.display = 'flex';
}

function editItem(item) {
    document.getElementById('item_id').value = item.id;
    document.getElementById('existing_image').value = item.image_url || '';
    document.getElementById('item_name').value = item.name;
    document.getElementById('item_cat').value = item.category_id;
    
    const branchField = document.getElementById('item_branch');
    if (branchField) {
        branchField.value = item.branch_id;
    }
    
    document.getElementById('item_price').value = item.price_per_day;
    document.getElementById('item_qty').value = item.quantity_total;
    document.getElementById('item_status').value = item.status;
    document.getElementById('modalTitle').innerText = 'Modifier le Produit';
    
    if (item.image_url) {
        document.getElementById('preview_img').src = '../' + item.image_url;
        document.getElementById('current_image_preview').style.display = 'block';
    } else {
        document.getElementById('current_image_preview').style.display = 'none';
    }
    document.getElementById('item_image').value = '';
    document.getElementById('delete_image').checked = false;

    document.getElementById('itemModal').style.display = 'flex';
}
function distributeItem(item) {
    document.getElementById('dist_item_id').value = item.id;
    document.getElementById('dist_item_name_display').innerText = item.name;
    document.getElementById('dist_max_qty_display').innerText = item.quantity_available;
    document.getElementById('dist_qty').max = item.quantity_available;
    document.getElementById('dist_qty').value = '';
    
    document.getElementById('distributeModal').style.display = 'flex';
}

function maintenanceItem(item) {
    document.getElementById('maint_item_id').value = item.id;
    document.getElementById('maint_item_name_display').innerText = item.name;
    document.getElementById('maint_qty').value = '';
    
    document.getElementById('maintenanceModal').style.display = 'flex';
}
</script>

</body>
</html>

