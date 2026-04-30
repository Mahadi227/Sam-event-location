<?php
// includes/notifications.php
require_once __DIR__ . '/db.php';

/**
 * Creates a notification for a specific user
 */
function createNotification($user_id, $title, $message, $type = 'system', $reference_id = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, reference_id) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type, $reference_id]);
}

/**
 * Sends a notification to all users matching a specific role
 */
function notifyRole($role, $title, $message, $type = 'system', $reference_id = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
    $stmt->execute([$role]);
    $users = $stmt->fetchAll();
    
    $success = true;
    foreach ($users as $u) {
        if (!createNotification($u['id'], $title, $message, $type, $reference_id)) {
            $success = false;
        }
    }
    return $success;
}

/**
 * Sends a notification to all administrative staff (super_admin, mini_admin, receptionist)
 */
function notifyStaff($title, $message, $type = 'system', $reference_id = null) {
    notifyRole('super_admin', $title, $message, $type, $reference_id);
    notifyRole('mini_admin', $title, $message, $type, $reference_id);
    notifyRole('receptionist', $title, $message, $type, $reference_id);
}

/**
 * Gets unread count for a user
 */
function getUnreadNotificationCount($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * Notifies all admins, plus the user who processed the transaction (no duplicates)
 */
function notifyPaymentProcessed($title, $message, $reference_id, $processor_user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('super_admin', 'mini_admin') OR id = ?");
    $stmt->execute([$processor_user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach (array_unique($users) as $uid) {
        createNotification($uid, $title, $message, 'payment', $reference_id);
    }
}

/**
 * Notifies staff of a specific branch (and all super admins globally)
 */
function notifyBranch($branch_id, $title, $message, $type = 'system', $reference_id = null) {
    global $pdo;
    
    // Add super_admins (branch_id IS NULL or ignored) and specific branch staff
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'super_admin' OR (branch_id = ? AND role IN ('mini_admin', 'receptionist'))");
    $stmt->execute([$branch_id]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach (array_unique($users) as $uid) {
        createNotification($uid, $title, $message, $type, $reference_id);
    }
}
