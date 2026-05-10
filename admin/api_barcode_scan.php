<?php
// admin/api_barcode_scan.php
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
    $mode = $_POST['mode'] ?? ''; // checkout, return
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if (!$barcode || !$mode || !$reservation_id) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Find the inventory item
        $stmt = $pdo->prepare("SELECT inv.*, i.name as item_name FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.barcode = ? $branchSql");
        $stmt->execute([$barcode]);
        $inventory = $stmt->fetch();

        if (!$inventory) {
            echo json_encode(['success' => false, 'message' => 'Code-barre introuvable dans cette succursale']);
            exit;
        }

        // 2. Fetch reservation details to ensure this item is part of it
        $stmtRes = $pdo->prepare("SELECT * FROM reservation_items WHERE reservation_id = ? AND item_id = ?");
        $stmtRes->execute([$reservation_id, $inventory['item_id']]);
        $resItem = $stmtRes->fetch();

        if (!$resItem) {
            echo json_encode(['success' => false, 'message' => "L'article ({$inventory['item_name']}) ne fait pas partie de cette réservation"]);
            exit;
        }

        if ($mode === 'checkout') {
            if ($inventory['status'] !== 'available') {
                echo json_encode(['success' => false, 'message' => "Cet article n'est pas disponible (Statut actuel: {$inventory['status']})"]);
                exit;
            }

            // Check if we already scanned enough items for this reservation
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM barcode_scans WHERE reservation_id = ? AND inventory_id IN (SELECT id FROM inventory WHERE item_id = ?) AND scan_type = 'checkout'");
            $stmtCount->execute([$reservation_id, $inventory['item_id']]);
            $scannedCount = $stmtCount->fetchColumn();

            if ($scannedCount >= $resItem['quantity']) {
                echo json_encode(['success' => false, 'message' => "Vous avez déjà scanné le nombre total prévu ({$resItem['quantity']}) pour cet article."]);
                exit;
            }

            // Update status & Log scan
            $pdo->prepare("UPDATE inventory SET status = 'checked_out' WHERE id = ?")->execute([$inventory['id']]);
            $pdo->prepare("INSERT INTO barcode_scans (inventory_id, branch_id, reservation_id, scanned_by, scan_type) VALUES (?, ?, ?, ?, 'checkout')")
                ->execute([$inventory['id'], $inventory['branch_id'], $reservation_id, $user_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "{$inventory['item_name']} scanné avec succès (Sortie).", 'item_name' => $inventory['item_name']]);

        } elseif ($mode === 'return') {
            if ($inventory['status'] !== 'checked_out') {
                echo json_encode(['success' => false, 'message' => "Cet article n'est pas marqué comme loué (Statut: {$inventory['status']})"]);
                exit;
            }

            // Check if it was checked out for THIS reservation
            $stmtCheck = $pdo->prepare("SELECT id FROM barcode_scans WHERE inventory_id = ? AND reservation_id = ? AND scan_type = 'checkout' ORDER BY created_at DESC LIMIT 1");
            $stmtCheck->execute([$inventory['id'], $reservation_id]);
            if (!$stmtCheck->fetch()) {
                 echo json_encode(['success' => false, 'message' => "Cet article n'a pas été scanné pour la sortie de CETTE réservation."]);
                 exit;
            }

            // Update status & Log scan
            $pdo->prepare("UPDATE inventory SET status = 'available' WHERE id = ?")->execute([$inventory['id']]);
            $pdo->prepare("INSERT INTO barcode_scans (inventory_id, branch_id, reservation_id, scanned_by, scan_type) VALUES (?, ?, ?, ?, 'return')")
                ->execute([$inventory['id'], $inventory['branch_id'], $reservation_id, $user_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "{$inventory['item_name']} scanné avec succès (Retour).", 'item_name' => $inventory['item_name']]);

        } else {
            echo json_encode(['success' => false, 'message' => "Mode de scan invalide"]);
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non supportée']);
}
