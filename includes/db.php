<?php
// includes/db.php

$host = 'localhost';
$db   = 'sam_event_db';
$user = 'root'; // Par défaut sous XAMPP
$pass = '';     // Par défaut sous XAMPP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Erreur de connexion : " . $e->getMessage());
}

if (!function_exists('getCurrency')) {
    function getCurrency() {
        global $pdo;
        static $currency = null;
        if ($currency !== null) return $currency;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency'");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                if ($val) {
                    $currency = $val;
                    return $currency;
                }
            } catch (Exception $e) {}
        }
        $currency = "FCFA";
        return $currency;
    }
}

if (!function_exists('getTaxRate')) {
    function getTaxRate() {
        global $pdo;
        static $tax_rate = null;
        if ($tax_rate !== null) return $tax_rate;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                if ($val !== false) {
                    $tax_rate = (float)$val;
                    return $tax_rate;
                }
            } catch (Exception $e) {}
        }
        $tax_rate = 0.0;
        return $tax_rate;
    }
}

if (!function_exists('logActivity')) {
    function logActivity($user_id, $branch_id, $action, $description) {
        global $pdo;
        if (!isset($pdo)) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, branch_id, action, description, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $branch_id, $action, $description, $ip]);
        } catch (Exception $e) {
            // Silently ignore to avoid breaking the application
        }
    }
}
?>
