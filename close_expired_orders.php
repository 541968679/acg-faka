<?php
date_default_timezone_set('Asia/Shanghai');

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'acgfaka';
$dbUser = getenv('DB_USERNAME') ?: 'faka';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbPrefix = getenv('DB_PREFIX') ?: 'acg_';
$timeoutMinutes = 10;
$logPath = __DIR__ . '/runtime/order_cleanup.log';

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbPrefix)) {
    $dbPrefix = 'acg_';
}

$orderTable = sprintf('`%sorder`', str_replace('`', '``', $dbPrefix));

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+08:00'");

    $stmt = $pdo->prepare(
        "UPDATE {$orderTable} SET status = 2 WHERE status = 0 AND create_time < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)"
    );
    $stmt->execute(['minutes' => $timeoutMinutes]);
    $count = $stmt->rowCount();

    if ($count > 0) {
        $logLine = date('Y-m-d H:i:s') . " - Closed $count expired order(s)\n";
        file_put_contents($logPath, $logLine, FILE_APPEND);
    }
} catch (PDOException $e) {
    $logLine = date('Y-m-d H:i:s') . ' - ERROR: ' . $e->getMessage() . "\n";
    file_put_contents($logPath, $logLine, FILE_APPEND);
}
