<?php
// admin/print_barcodes.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$item_id = $_GET['item_id'] ?? null;
if (!$item_id) die("ID de l'article manquant.");

$branchSql = getBranchSqlFilter('inv');

// Fetch item details
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();
if (!$item) die("Article introuvable.");

// Fetch barcodes
$barcodes = $pdo->query("
    SELECT inv.* 
    FROM inventory inv 
    WHERE inv.item_id = " . (int)$item_id . " $branchSql 
    ORDER BY inv.item_code ASC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impression Codes-Barres - <?php echo htmlspecialchars($item['name']); ?></title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 20px;
        }
        .print-btn {
            display: block;
            width: 200px;
            margin: 0 auto 30px;
            padding: 15px;
            background: #4f46e5;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            border: none;
        }
        .labels-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            background: white;
            padding: 20mm 10mm;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .label {
            width: 60mm;
            height: 30mm;
            border: 1px dashed #ccc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 5px;
            box-sizing: border-box;
            background: #fff;
        }
        .label-title {
            font-size: 0.7rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }
        .label-img {
            max-width: 100%;
            height: 15mm;
        }
        .label-code {
            font-size: 0.65rem;
            margin-top: 3px;
            font-family: monospace;
        }

        @media print {
            body { background: white; padding: 0; }
            .print-btn { display: none; }
            .labels-container { box-shadow: none; padding: 0; width: 100%; max-width: 100%; }
            .label { border: 1px solid #eee; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="print-btn">🖨️ Imprimer les étiquettes</button>

    <div class="labels-container">
        <?php foreach($barcodes as $b): ?>
            <div class="label">
                <div class="label-title"><?php echo htmlspecialchars($item['name']); ?></div>
                <!-- Using an external free API to generate the barcode image on the fly -->
                <img class="label-img" src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($b['barcode']); ?>&code=Code128&dpi=96" alt="Barcode <?php echo $b['barcode']; ?>">
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
