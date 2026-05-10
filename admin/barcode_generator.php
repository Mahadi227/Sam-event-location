<?php
// admin/barcode_generator.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$branchSql = getBranchSqlFilter();
$branch_id = getActiveBranch() ?: null;

// Fetch all items for dropdown with their traced count
$items = $pdo->query("
    SELECT i.*, 
           (SELECT COUNT(*) FROM inventory inv WHERE inv.item_id = i.id) as traced_count 
    FROM items i 
    WHERE 1=1 $branchSql 
    ORDER BY name ASC
")->fetchAll();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $item_id = (int)$_POST['item_id'];
    $prefix = trim(strtoupper($_POST['prefix']));
    $quantity = (int)$_POST['quantity'];
    
    if ($item_id && $prefix && $quantity > 0) {
        try {
            $pdo->beginTransaction();
            
            // Check if adding this quantity exceeds the total stock
            $stmtItem = $pdo->prepare("SELECT quantity_total, (SELECT COUNT(*) FROM inventory WHERE item_id = ?) as traced_count FROM items WHERE id = ?");
            $stmtItem->execute([$item_id, $item_id]);
            $itemInfo = $stmtItem->fetch();
            
            if ($itemInfo) {
                $remaining = $itemInfo['quantity_total'] - $itemInfo['traced_count'];
                if ($quantity > $remaining) {
                    throw new Exception("Vous ne pouvez pas générer plus de codes-barres que la quantité totale de l'article. Il reste $remaining étiquette(s) à générer.");
                }
            }
            
            // Find current max number for this prefix to continue the sequence
            $stmt = $pdo->prepare("SELECT item_code FROM inventory WHERE item_code LIKE ? ORDER BY LENGTH(item_code) DESC, item_code DESC LIMIT 1");
            $stmt->execute([$prefix . '-%']);
            $last_code = $stmt->fetchColumn();
            
            $start_num = 1;
            if ($last_code) {
                $parts = explode('-', $last_code);
                $last_num = (int)end($parts);
                $start_num = $last_num + 1;
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO inventory (branch_id, item_id, item_code, barcode) VALUES (?, ?, ?, ?)");
            
            for ($i = 0; $i < $quantity; $i++) {
                $num_str = str_pad($start_num + $i, 4, '0', STR_PAD_LEFT);
                $code = $prefix . '-' . $num_str;
                // Barcode and item_code are identical for simplicity and human readability
                $insertStmt->execute([$branch_id, $item_id, $code, $code]);
            }
            
            $pdo->commit();
            $msg = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'error';
            $error_details = $e->getMessage();
        }
    }
}

// Fetch existing inventory grouped by item
$inventoryBranchSql = getBranchSqlFilter('i');
$inventory_summary = $pdo->query("
    SELECT i.id as item_id, i.name, COUNT(inv.id) as total_barcodes,
           SUM(CASE WHEN inv.status = 'available' THEN 1 ELSE 0 END) as available,
           SUM(CASE WHEN inv.status = 'checked_out' THEN 1 ELSE 0 END) as checked_out
    FROM items i
    LEFT JOIN inventory inv ON i.id = inv.item_id
    WHERE 1=1 $inventoryBranchSql
    GROUP BY i.id
    HAVING total_barcodes > 0
    ORDER BY i.name ASC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générateur de Codes-Barres - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        function updatePrefix() {
            var select = document.getElementById("item_select");
            var option = select.options[select.selectedIndex];
            
            if(select.value !== "") {
                var name = option.text.split(' (Total:')[0].trim();
                // Generate a 3-4 letter prefix based on the name
                var prefix = name.replace(/[^A-Za-z0-9]/g, '').substring(0, 4).toUpperCase();
                document.getElementById("prefix_input").value = prefix;
                
                // Set the quantity to the exact remaining total
                var total = parseInt(option.getAttribute('data-total') || 0);
                var traced = parseInt(option.getAttribute('data-traced') || 0);
                var remaining = total - traced;
                
                if (remaining > 0) {
                    document.getElementById("quantity_input").value = remaining;
                    document.getElementById("quantity_input").max = remaining;
                    document.getElementById("quantity_input").readOnly = false;
                } else {
                    document.getElementById("quantity_input").value = 0;
                    document.getElementById("quantity_input").max = 0;
                    document.getElementById("quantity_input").readOnly = true;
                }
            } else {
                document.getElementById("prefix_input").value = '';
                document.getElementById("quantity_input").value = 10;
            }
        }
    </script>
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Admin</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <h2 style="margin-bottom: 20px;">Générateur de Codes-Barres</h2>
        
        <?php if ($msg === 'success'): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> Les codes-barres ont été générés avec succès !
            </div>
        <?php elseif ($msg === 'error'): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Erreur lors de la génération. <?php echo htmlspecialchars($error_details ?? ''); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            <!-- Generation Form -->
            <div class="card">
                <h3>Créer de nouveaux codes</h3>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">Générez des codes uniques pour identifier physiquement vos articles.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Article Modèle</label>
                        <select name="item_id" id="item_select" class="form-control" required onchange="updatePrefix()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                            <option value="">Sélectionner un article</option>
                            <?php foreach($items as $i): ?>
                                <?php $remaining = $i['quantity_total'] - $i['traced_count']; ?>
                                <option value="<?php echo $i['id']; ?>" data-total="<?php echo $i['quantity_total']; ?>" data-traced="<?php echo $i['traced_count']; ?>">
                                    <?php echo htmlspecialchars($i['name']); ?> 
                                    (Total: <?php echo $i['quantity_total']; ?> | Tracé: <?php echo $i['traced_count']; ?> | Reste: <?php echo $remaining; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Préfixe du Code</label>
                        <input type="text" name="prefix" id="prefix_input" required placeholder="Ex: CHAIR" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                        <small style="color: #888;">Le système ajoutera automatiquement -0001, -0002, etc.</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Quantité d'étiquettes à générer</label>
                        <input type="number" name="quantity" id="quantity_input" required min="1" max="1000" value="10" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>

                    <button type="submit" class="contact-btn" style="width: 100%; border: none; padding: 12px; cursor: pointer; background: var(--primary-blue); border-radius: 8px;"><i class="fas fa-barcode"></i> Générer les Codes</button>
                </form>
            </div>

            <!-- Existing Inventory Summary -->
            <div class="card">
                <h3>Inventaire Tracé (Codes-Barres)</h3>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr style="border-bottom: 2px solid #eee; text-align: left;">
                                <th style="padding: 10px;">Article</th>
                                <th style="padding: 10px; text-align: center;">Total Tracé</th>
                                <th style="padding: 10px; text-align: center;">Dispo.</th>
                                <th style="padding: 10px; text-align: center;">En Location</th>
                                <th style="padding: 10px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory_summary)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #666;">Aucun code-barre généré pour le moment.</td></tr>
                            <?php else: ?>
                                <?php foreach($inventory_summary as $inv): ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 10px;"><strong><?php echo htmlspecialchars($inv['name']); ?></strong></td>
                                        <td style="padding: 10px; text-align: center; font-weight: bold; color: var(--primary-blue);"><?php echo $inv['total_barcodes']; ?></td>
                                        <td style="padding: 10px; text-align: center; color: #10b981;"><?php echo $inv['available']; ?></td>
                                        <td style="padding: 10px; text-align: center; color: #ef4444;"><?php echo $inv['checked_out']; ?></td>
                                        <td style="padding: 10px; text-align: right;">
                                            <a href="print_barcodes.php?item_id=<?php echo $inv['item_id']; ?>" target="_blank" class="contact-btn" style="padding: 5px 10px; font-size: 0.8rem; text-decoration: none; background: #4f46e5; display: inline-block;">
                                                <i class="fas fa-print"></i> Imprimer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js?v=8"></script>
</body>
</html>
