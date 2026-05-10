<?php
// admin/inventory_scan.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$branchSql = getBranchSqlFilter('inv');

// If no item selected, show selection
$item_id = $_GET['item_id'] ?? null;
$today = date('Y-m-d');

if (!$item_id) {
    $items = $pdo->query("SELECT * FROM items WHERE 1=1 " . getBranchSqlFilter() . " ORDER BY name ASC")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    if (!$item) die("Article introuvable");

    // Fetch all barcodes for this item
    $all_barcodes = $pdo->query("SELECT * FROM inventory inv WHERE item_id = " . (int)$item_id . " $branchSql ORDER BY item_code ASC")->fetchAll();
    
    // Fetch scanned barcodes today
    $scanned_today = $pdo->query("
        SELECT bs.inventory_id 
        FROM barcode_scans bs 
        JOIN inventory inv ON bs.inventory_id = inv.id 
        WHERE inv.item_id = " . (int)$item_id . " 
        AND bs.scan_type = 'inventory_check' 
        AND DATE(bs.created_at) = '$today'
    ")->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Inventaire - Sam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .scan-input {
            width: 100%; padding: 20px; font-size: 1.5rem; text-align: center;
            border: 3px solid var(--primary-blue); border-radius: 12px; margin-bottom: 20px;
        }
        .scan-input:focus { outline: none; border-color: #4f46e5; }
        .barcode-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .b-card { padding: 10px; text-align: center; border-radius: 8px; border: 1px solid #ddd; background: #fff; font-family: monospace; font-size: 0.9rem; }
        .b-card.scanned { background: #d4edda; border-color: #c3e6cb; color: #155724; font-weight: bold; }
        .b-card.missing { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .b-card.rented { background: #fff3cd; border-color: #ffeeba; color: #856404; }
    </style>
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Scanner</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <h2 style="margin-bottom: 20px;">Mode Audit Inventaire</h2>

        <?php if (!$item_id): ?>
            <div class="card" style="max-width: 500px;">
                <p>Sélectionnez un article pour commencer l'audit physique de l'entrepôt. Le système vérifiera quels articles ont été scannés aujourd'hui.</p>
                <form method="GET">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Article à auditer :</label>
                        <select name="item_id" class="form-control" required style="width: 100%; padding: 10px; border-radius: 8px;">
                            <option value="">Sélectionner...</option>
                            <?php foreach($items as $i): ?>
                                <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="contact-btn" style="width: 100%; border: none; padding: 12px; cursor: pointer;"><i class="fas fa-search"></i> Démarrer l'Audit</button>
                </form>
            </div>
        <?php else: ?>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0;">Audit: <?php echo htmlspecialchars($item['name']); ?></h3>
                    <p style="color: #666; margin: 5px 0 0;">Scannez physiquement chaque unité.</p>
                </div>
                <div>
                    <a href="inventory_scan.php" class="contact-btn" style="background: #64748b; text-decoration: none; padding: 8px 15px;">Changer d'article</a>
                </div>
            </div>

            <input type="text" id="barcode_input" class="scan-input" placeholder="Scannez ici..." autofocus autocomplete="off">
            <div id="scan-alert" style="padding: 10px; border-radius: 8px; margin-bottom: 15px; display: none; font-weight: bold; text-align: center;"></div>

            <div class="card">
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div style="padding: 5px 10px; background: #d4edda; border-radius: 5px; font-size: 0.8rem;">Scanné OK</div>
                    <div style="padding: 5px 10px; background: #fff3cd; border-radius: 5px; font-size: 0.8rem;">En Location (Normalement absent)</div>
                    <div style="padding: 5px 10px; background: #fff; border: 1px solid #ddd; border-radius: 5px; font-size: 0.8rem;">Manquant (Non scanné)</div>
                </div>

                <div class="barcode-grid">
                    <?php foreach($all_barcodes as $b): ?>
                        <?php 
                            $is_scanned = in_array($b['id'], $scanned_today);
                            $is_rented = ($b['status'] === 'checked_out');
                            
                            $classes = [];
                            if ($is_scanned) $classes[] = 'scanned';
                            elseif ($is_rented) $classes[] = 'rented';
                            
                            $icon = '';
                            if ($is_scanned) $icon = '<i class="fas fa-check-circle"></i> ';
                            elseif ($is_rented) $icon = '<i class="fas fa-truck"></i> ';
                        ?>
                        <div class="b-card <?php echo implode(' ', $classes); ?>" id="card-<?php echo $b['id']; ?>">
                            <?php echo $icon . htmlspecialchars($b['item_code']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <script>
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                function playBeep(type) {
                    const oscillator = audioCtx.createOscillator();
                    const gainNode = audioCtx.createGain();
                    oscillator.connect(gainNode);
                    gainNode.connect(audioCtx.destination);
                    if (type === 'success') {
                        oscillator.type = 'sine'; oscillator.frequency.setValueAtTime(800, audioCtx.currentTime); 
                        gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
                        oscillator.start(); oscillator.stop(audioCtx.currentTime + 0.15);
                    } else {
                        oscillator.type = 'sawtooth'; oscillator.frequency.setValueAtTime(200, audioCtx.currentTime);
                        gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
                        oscillator.start(); oscillator.stop(audioCtx.currentTime + 0.3);
                    }
                }

                const barcodeInput = document.getElementById('barcode_input');
                const alertBox = document.getElementById('scan-alert');
                let isScanning = false;

                barcodeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const barcode = this.value.trim();
                        if (barcode !== '' && !isScanning) processScan(barcode);
                    }
                });

                function showAlert(message, type) {
                    alertBox.textContent = message;
                    alertBox.style.background = type === 'success' ? '#d4edda' : '#f8d7da';
                    alertBox.style.color = type === 'success' ? '#155724' : '#721c24';
                    alertBox.style.display = 'block';
                    setTimeout(() => { alertBox.style.display = 'none'; }, 2000);
                }

                function processScan(barcode) {
                    isScanning = true;
                    barcodeInput.disabled = true;

                    const formData = new FormData();
                    formData.append('barcode', barcode);
                    formData.append('item_id', '<?php echo $item_id; ?>');

                    fetch('api_inventory_scan.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            playBeep('success');
                            showAlert(data.message, 'success');
                            const card = document.getElementById('card-' + data.inventory_id);
                            if (card) {
                                card.className = 'b-card scanned';
                                card.innerHTML = '<i class="fas fa-check-circle"></i> ' + card.innerText;
                            }
                        } else {
                            playBeep('error');
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(() => { playBeep('error'); showAlert('Erreur', 'error'); })
                    .finally(() => {
                        barcodeInput.value = '';
                        barcodeInput.disabled = false;
                        barcodeInput.focus();
                        isScanning = false;
                    });
                }
                
                document.addEventListener('click', function(e) {
                    if(e.target.id !== 'barcode_input') barcodeInput.focus();
                });
            </script>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/admin.js?v=8"></script>
</body>
</html>
