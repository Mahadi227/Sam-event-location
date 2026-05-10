<?php
// admin/barcode_scan.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStaff(); // Both admin and receptionist can scan

$mode = $_GET['mode'] ?? 'checkout'; // checkout or return
$reservation_id = $_GET['reservation_id'] ?? null;

if (!$reservation_id) {
    die("ID de réservation manquant.");
}

$branchSql = getBranchSqlFilter('r');
$stmt = $pdo->prepare("
    SELECT r.* 
    FROM reservations r 
    WHERE r.id = ? $branchSql
");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    die("Réservation introuvable ou non autorisée.");
}

// Fetch expected items and scanned counts
$stmtItems = $pdo->prepare("
    SELECT ri.*, i.name as item_name,
        (SELECT COUNT(*) FROM barcode_scans bs 
         JOIN inventory inv ON bs.inventory_id = inv.id 
         WHERE bs.reservation_id = ? AND inv.item_id = ri.item_id AND bs.scan_type = ?) as scanned_count
    FROM reservation_items ri
    JOIN items i ON ri.item_id = i.id
    WHERE ri.reservation_id = ?
");
$stmtItems->execute([$reservation_id, $mode, $reservation_id]);
$expected_items = $stmtItems->fetchAll();

$is_complete = true;
foreach($expected_items as $ei) {
    if ($ei['scanned_count'] < $ei['quantity']) {
        $is_complete = false;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Code-Barre - <?php echo ucfirst($mode); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .scan-container {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }
        .scan-input {
            width: 100%;
            padding: 20px;
            font-size: 1.5rem;
            text-align: center;
            border: 3px solid var(--primary-blue);
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .scan-input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        .progress-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .progress-item:last-child { border: none; }
        .status-badge.complete { background: #d4edda; color: #155724; }
        .status-badge.incomplete { background: #fff3cd; color: #856404; }
        
        #scan-alert {
            display: none;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Scanner</div>
</div>

<div class="admin-container">
    <?php 
    if (hasRole('super_admin') || hasRole('mini_admin')) {
        include '../includes/admin_sidebar.php'; 
    } else {
        include '../includes/receptionist_sidebar.php'; 
    }
    ?>

    <div class="main-content">
        <div class="scan-container">
            <h2 style="margin-bottom: 10px;">Mode Scanner : <?php echo $mode === 'checkout' ? 'Sortie' : 'Retour'; ?></h2>
            <p style="color: #666; margin-bottom: 30px;">Réservation #<?php echo $reservation['id']; ?> - <?php echo htmlspecialchars($reservation['customer_name']); ?></p>

            <div id="scan-alert"></div>

            <input type="text" id="barcode_input" class="scan-input" placeholder="Scannez un code-barre ici..." autofocus autocomplete="off">
            <p style="color: #888; font-size: 0.85rem;">(Assurez-vous que le curseur clignote dans le champ ci-dessus)</p>

            <div class="card" style="margin-top: 30px; text-align: left;">
                <h3 style="margin-bottom: 15px;">Progression</h3>
                <div id="progress-container">
                    <?php foreach($expected_items as $ei): ?>
                        <?php $is_item_complete = $ei['scanned_count'] >= $ei['quantity']; ?>
                        <div class="progress-item" id="progress-item-<?php echo $ei['item_id']; ?>">
                            <div>
                                <strong><?php echo htmlspecialchars($ei['item_name']); ?></strong><br>
                                <span style="font-size: 0.85rem; color: #666;">ID Article: <?php echo $ei['item_id']; ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span style="font-size: 1.2rem; font-weight: bold;">
                                    <span class="scanned-val" data-item-id="<?php echo $ei['item_id']; ?>"><?php echo $ei['scanned_count']; ?></span> / <?php echo $ei['quantity']; ?>
                                </span>
                                <span class="status-badge <?php echo $is_item_complete ? 'complete' : 'incomplete'; ?>" style="padding: 5px 10px; border-radius: 20px; font-size: 0.8rem;">
                                    <?php echo $is_item_complete ? '<i class="fas fa-check"></i>' : 'En attente'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($is_complete): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; text-align: center; font-weight: bold;">
                        <i class="fas fa-check-circle"></i> Tous les articles ont été scannés !
                    </div>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="manage.php?id=<?php echo $reservation_id; ?>" class="contact-btn" style="text-decoration: none; padding: 10px 20px;">Retour à la Réservation</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Audio resources (using data URIs or short sounds if available, else simple beep JS) -->
<script>
    // Create a simple beep sound using AudioContext
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    
    function playBeep(type) {
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        
        if (type === 'success') {
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(800, audioCtx.currentTime); // High pitch
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.15);
        } else {
            oscillator.type = 'sawtooth';
            oscillator.frequency.setValueAtTime(200, audioCtx.currentTime); // Low pitch error
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.3);
        }
    }

    const barcodeInput = document.getElementById('barcode_input');
    const alertBox = document.getElementById('scan-alert');
    let isScanning = false;

    // Listen for "Enter" key which barcode scanners send automatically
    barcodeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const barcode = this.value.trim();
            if (barcode !== '' && !isScanning) {
                processScan(barcode);
            }
        }
    });

    function showAlert(message, type) {
        alertBox.textContent = message;
        alertBox.className = type === 'success' ? 'alert-success' : 'alert-error';
        alertBox.style.display = 'block';
        setTimeout(() => { alertBox.style.display = 'none'; }, 3000);
    }

    function processScan(barcode) {
        isScanning = true;
        barcodeInput.disabled = true;

        const formData = new FormData();
        formData.append('barcode', barcode);
        formData.append('mode', '<?php echo $mode; ?>');
        formData.append('reservation_id', '<?php echo $reservation_id; ?>');

        fetch('api_barcode_scan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                playBeep('success');
                showAlert(data.message, 'success');
                // Reload page after a short delay to update progress, or update DOM dynamically
                setTimeout(() => { location.reload(); }, 800);
            } else {
                playBeep('error');
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            playBeep('error');
            showAlert('Erreur de connexion', 'error');
        })
        .finally(() => {
            barcodeInput.value = '';
            barcodeInput.disabled = false;
            barcodeInput.focus();
            isScanning = false;
        });
    }
    
    // Keep focus on input
    document.addEventListener('click', function(e) {
        if(e.target.id !== 'barcode_input') {
            barcodeInput.focus();
        }
    });
</script>

</body>
</html>
