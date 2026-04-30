<?php
$file = 'index.php';
$content = file_get_contents($file);

$search = '$stmt = $pdo->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.status = \'available\' ORDER BY i.category_id, i.name");
$items = $stmt->fetchAll();';

$replace = '$stmt = $pdo->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.status = \'available\' ORDER BY i.category_id, i.name");
$allItems = $stmt->fetchAll();
$items = [];
$seen = [];
foreach ($allItems as $item) {
    if (!isset($seen[$item[\'name\']])) {
        $seen[$item[\'name\']] = true;
        $items[] = $item;
    }
}';

$search = str_replace("\r\n", "\n", $search);
$replace = str_replace("\r\n", "\n", $replace);

$content = str_replace("\r\n", "\n", $content);
$content = str_replace($search, $replace, $content);

file_put_contents($file, $content);
echo "Done";
?>
