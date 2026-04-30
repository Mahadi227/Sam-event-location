<?php
$file = 'items.php';
$content = file_get_contents($file);

$search1 = "if (!empty(\$_GET['status'])) {
    \$where[] = \"i.status = ?\";
    \$params[] = \$_GET['status'];
}

\$whereClause";

$replace1 = "if (!empty(\$_GET['status'])) {
    \$where[] = \"i.status = ?\";
    \$params[] = \$_GET['status'];
}
if (!empty(\$_GET['branch'])) {
    \$where[] = \"i.branch_id = ?\";
    \$params[] = \$_GET['branch'];
}

\$whereClause";

$search2 = "            <div class=\"form-group\" style=\"width: 150px;\">
                <label style=\"font-size: 0.85rem; font-weight: 700; color: #666;\">Statut</label>
                <select name=\"status\" style=\"width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;\">
                    <option value=\"\">Tous</option>
                    <option value=\"available\" <?php echo (\$_GET['status'] ?? '') == 'available' ? 'selected' : ''; ?>>Disponible</option>
                    <option value=\"out_of_stock\" <?php echo (\$_GET['status'] ?? '') == 'out_of_stock' ? 'selected' : ''; ?>>Rupture</option>
                </select>
            </div>";

$replace2 = "            <div class=\"form-group\" style=\"width: 150px;\">
                <label style=\"font-size: 0.85rem; font-weight: 700; color: #666;\">Statut</label>
                <select name=\"status\" style=\"width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;\">
                    <option value=\"\">Tous</option>
                    <option value=\"available\" <?php echo (\$_GET['status'] ?? '') == 'available' ? 'selected' : ''; ?>>Disponible</option>
                    <option value=\"out_of_stock\" <?php echo (\$_GET['status'] ?? '') == 'out_of_stock' ? 'selected' : ''; ?>>Rupture</option>
                </select>
            </div>
            <div class=\"form-group\" style=\"width: 180px;\">
                <label style=\"font-size: 0.85rem; font-weight: 700; color: #666;\">Succursale</label>
                <select name=\"branch\" style=\"width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;\">
                    <option value=\"\">Toutes</option>
                    <?php foreach (\$branches as \$b): ?>
                        <option value=\"<?php echo \$b['id']; ?>\" <?php echo (\$_GET['branch'] ?? '') == \$b['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(\$b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>";

// Normalize line endings for replacement
$search1 = str_replace("\r\n", "\n", $search1);
$replace1 = str_replace("\r\n", "\n", $replace1);
$search2 = str_replace("\r\n", "\n", $search2);
$replace2 = str_replace("\r\n", "\n", $replace2);

$content = str_replace("\r\n", "\n", $content);

$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "Done";
?>
