<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bot_core.php'; // provides $pdo

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    $activities = [];

    // Recent users (last 24 hours)
    $stmt = $pdo->prepare(
        "SELECT username, register FROM user WHERE register >= (UNIX_TIMESTAMP(NOW()) - 86400) ORDER BY register DESC LIMIT 3"
    );
    $stmt->execute();
    $newUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($newUsers as $user) {
        $activities[] = [
            'icon' => '👤',
            'text' => 'کاربر جدید ثبت نام کرد: ' . htmlspecialchars($user['username'] ?? 'بدون نام'),
            'time' => timeAgo((int)$user['register']),
            'type' => 'success'
        ];
    }

    // Recent completed payments (last 24 hours)
    $stmt = $pdo->prepare(
        "SELECT price, time FROM Payment_report WHERE payment_Status IN ('completed','paid') AND time >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY time DESC LIMIT 3"
    );
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($payments as $payment) {
        $ts = is_numeric($payment['time']) ? (int)$payment['time'] : strtotime($payment['time']);
        $activities[] = [
            'icon' => '💰',
            'text' => 'پرداخت ' . number_format((int)$payment['price']) . ' تومان انجام شد',
            'time' => timeAgo($ts),
            'type' => 'success'
        ];
    }

    // Recent invoices/services (last 24 hours by time_sell)
    $stmt = $pdo->prepare(
        "SELECT id_invoice, username, time_sell FROM invoice WHERE time_sell >= (UNIX_TIMESTAMP(NOW()) - 86400) ORDER BY time_sell DESC LIMIT 3"
    );
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($services as $service) {
        $activities[] = [
            'icon' => '📋',
            'text' => 'سرویس جدید ایجاد شد: ' . htmlspecialchars($service['username'] ?? $service['id_invoice']),
            'time' => timeAgo((int)$service['time_sell']),
            'type' => 'info'
        ];
    }

    // Sort newest first by time string weight is approximate; list already grouped by newest per-query
    // Limit to 10 most recent
    $activities = array_slice($activities, 0, 10);

    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function timeAgo($timestamp) {
    $difference = time() - (int)$timestamp;
    if ($difference < 60) return 'همین الان';
    if ($difference < 3600) return floor($difference / 60) . ' دقیقه پیش';
    if ($difference < 86400) return floor($difference / 3600) . ' ساعت پیش';
    return floor($difference / 86400) . ' روز پیش';
}
?>
