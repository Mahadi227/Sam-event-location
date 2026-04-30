<?php
// api/notifications.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    // 1. Get Unread Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();

    // 2. Get Last 10 Notifications (Both read and unread, but mostly recent)
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'unread_count' => $count,
        'notifications' => $notifications
    ]);
    exit;
} 
elseif ($action === 'mark_read') {
    $notif_id = $_POST['id'] ?? 0;
    if ($notif_id) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'ID manquant']);
    }
    exit;
}
elseif ($action === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Action invalide']);
