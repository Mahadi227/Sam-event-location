<?php
// admin/api_inventory_scan.php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$branchSql = getBranchSqlFilter('inv');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if (!$barcode || !$item_id) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit;
    }

    try {
        // Find the inventory item
        $stmt = $pdo->prepare("SELECT inv.*, i.name as item_name FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.barcode = ? AND inv.item_id = ? $branchSql");
        $stmt->execute([$barcode, $item_id]);
        $inventory = $stmt->fetch();

        if (!$inventory) {
            echo json_encode(['success' => false, 'message' => 'Code-barre introuvable pour cet article']);
            exit;
        }

        // We just log the scan to barcode_scans with type 'inventory_check'
        // But to prevent duplicate scans in the current session, we can check if it was scanned today
        $today = date('Y-m-d');
        $stmtCheck = $pdo->prepare("SELECT id FROM barcode_scans WHERE inventory_id = ? AND scan_type = 'inventory_check' AND DATE(created_at) = ?");
        $stmtCheck->execute([$inventory['id'], $today]);
        if ($stmtCheck->fetch()) {
             echo json_encode(['success' => false, 'message' => "Déjà scanné aujourd'hui!"]);
             exit;
        }

        $pdo->prepare("INSERT INTO barcode_scans (inventory_id, branch_id, scanned_by, scan_type) VALUES (?, ?, ?, 'inventory_check')")
            ->execute([$inventory['id'], $inventory['branch_id'], $user_id]);

        echo json_encode([
            'success' => true, 
            'message' => "Code OK: {$inventory['barcode']}", 
            'inventory_id' => $inventory['id'],
            'status' => $inventory['status']
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
}
