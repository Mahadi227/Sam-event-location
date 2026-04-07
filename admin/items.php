<?php
// admin/items.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$msg = '';

// Add/Edit Item
if (isset($_POST['save_item'])) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'];
    $cat_id = $_POST['category_id'];
    $price = $_POST['price'];
    $qty = $_POST['quantity'];
    $status = $_POST['status'];
    $image_url = $_POST['existing_image'] ?? null;

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
        if ($image_url !== null) {
            $stmt = $pdo->prepare("UPDATE items SET name = ?, category_id = ?, price_per_day = ?, quantity_total = ?, status = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$name, $cat_id, $price, $qty, $status, $image_url, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE items SET name = ?, category_id = ?, price_per_day = ?, quantity_total = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $cat_id, $price, $qty, $status, $id]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO items (name, category_id, price_per_day, quantity_total, status, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $cat_id, $price, $qty, $status, $image_url]);
    }
    $msg = "Produit enregistré avec succès !";
}

// Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
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

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM items i $whereClause");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);
$items = $pdo->prepare("SELECT i.*, c.name as cat_name FROM items i JOIN categories c ON i.category_id = c.id $whereClause ORDER BY c.name, i.name LIMIT $limit OFFSET $offset");
$items->execute($params);
$items = $items->fetchAll();
$query_string_params = $_GET;
unset($query_string_params['page']);
$base_url = '?';
if (!empty($query_string_params)) $base_url = '?' . http_build_query($query_string_params) . '&';

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock & Produits - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
        <a href="reservations.php"><i class="fas fa-calendar-check"></i> &nbsp; Réservations</a>
        <a href="payments.php"><i class="fas fa-money-bill-wave"></i> &nbsp; Paiements</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse</a>
        <?php if (hasRole('super_admin')): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> &nbsp; Utilisateurs</a>
            <a href="settings.php"><i class="fas fa-tools"></i> &nbsp; Paramètres</a>
        <?php endif; ?>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
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
                <label style="font-size: 0.85rem; font-weight: 700; color: #666;">Catégorie</label>
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
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="contact-btn" style="padding: 10px 20px; border: none; cursor: pointer;">Filtrer</button>
                <a href="items.php" class="btn-reserve" style="background: #888; text-decoration: none; padding: 10px 15px; border-radius: 8px;">Reset</a>
            </div>
        </form>
    </div>

    <?php if ($msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 2px solid #eee;">
                        <th style="padding: 15px;">Image</th>
                        <th style="padding: 15px;">Catégorie</th>
                        <th style="padding: 15px;">Désignation</th>
                        <th style="padding: 15px;">Prix/Jour</th>
                        <th style="padding: 15px;">Stock Total</th>
                        <th style="padding: 15px;">Statut</th>
                        <th style="padding: 15px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr style="border-bottom: 1px solid #f9f9f9;">
                        <td style="padding: 15px;">
                            <?php if (!empty($it['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($it['image_url']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #eee; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px;"><span style="color: #666; font-size: 0.85rem;"><?php echo htmlspecialchars($it['cat_name']); ?></span></td>
                        <td style="padding: 15px;"><strong><?php echo htmlspecialchars($it['name']); ?></strong></td>
                        <td style="padding: 15px;"><?php echo number_format($it['price_per_day'], 0); ?> F</td>
                        <td style="padding: 15px;"><?php echo $it['quantity_total']; ?></td>
                        <td style="padding: 15px;"><span class="status-pill <?php echo $it['status']; ?>"><?php echo strtoupper($it['status']); ?></span></td>
                        <td style="padding: 15px;">
                            <button onclick="editItem(<?php echo htmlspecialchars(json_encode($it)); ?>)" style="background:none; border:none; color: #4338ca; cursor: pointer; margin-right: 10px;"><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?php echo $it['id']; ?>" onclick="return confirm('Confirmer la suppression ?')" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
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
<div id="itemModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index: 2000;">
    <div style="background: white; padding: 30px; border-radius: 20px; width: 500px; max-width: 90%; position: relative;">
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
                </div>
            </div>
            <div class="form-group">
                <label>Nom du produit</label>
                <input type="text" name="name" id="item_name" required class="form-control" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 15px;">
            </div>
            <div class="form-group">
                <label>Catégorie</label>
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

<script src="../assets/js/admin.js"></script>
<script>
function newItem() {
    document.getElementById('item_id').value = '';
    document.getElementById('existing_image').value = '';
    document.getElementById('item_name').value = '';
    document.getElementById('item_cat').value = '';
    document.getElementById('item_price').value = '';
    document.getElementById('item_qty').value = '';
    document.getElementById('item_status').value = 'available';
    document.getElementById('modalTitle').innerText = 'Nouveau Produit';
    
    document.getElementById('current_image_preview').style.display = 'none';
    document.getElementById('item_image').value = '';
    
    document.getElementById('itemModal').style.display = 'flex';
}

function editItem(item) {
    document.getElementById('item_id').value = item.id;
    document.getElementById('existing_image').value = item.image_url || '';
    document.getElementById('item_name').value = item.name;
    document.getElementById('item_cat').value = item.category_id;
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

    document.getElementById('itemModal').style.display = 'flex';
}
</script>

</body>
</html>

