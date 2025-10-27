<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $activities = [];
    
    // Get recent users (last 24 hours)
    $stmt = $db->prepare("SELECT username_id, Date_start FROM user WHERE Date_start >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY Date_start DESC LIMIT 3");
    $stmt->execute();
    $newUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($newUsers as $user) {
        $timeAgo = timeAgo(strtotime($user['Date_start']));
        $activities[] = [
            'icon' => '👤',
            'text' => 'کاربر جدید ثبت نام کرد: ' . htmlspecialchars($user['username_id']),
            'time' => $timeAgo,
            'type' => 'success'
        ];
    }
    
    // Get recent payments (last 24 hours)
    $stmt = $db->prepare("SELECT Price, timestamp FROM payment WHERE status = 'completed' AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY timestamp DESC LIMIT 3");
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as $payment) {
        $timeAgo = timeAgo(strtotime($payment['timestamp']));
        $activities[] = [
            'icon' => '💰',
            'text' => 'پرداخت ' . number_format($payment['Price']) . ' تومان انجام شد',
            'time' => $timeAgo,
            'type' => 'success'
        ];
    }
    
    // Get recent services (last 24 hours)
    $stmt = $db->prepare("SELECT Service_id, Date_start FROM invoices WHERE Date_start >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY Date_start DESC LIMIT 3");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($services as $service) {
        $timeAgo = timeAgo(strtotime($service['Date_start']));
        $activities[] = [
            'icon' => '📋',
            'text' => 'سرویس جدید ایجاد شد: ' . htmlspecialchars($service['Service_id']),
            'time' => $timeAgo,
            'type' => 'info'
        ];
    }
    
    // Get expired services (last 24 hours)
    $stmt = $db->prepare("SELECT Service_id, Date_end FROM invoices WHERE status = 'expired' AND Date_end >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY Date_end DESC LIMIT 2");
    $stmt->execute();
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expired as $exp) {
        $timeAgo = timeAgo(strtotime($exp['Date_end']));
        $activities[] = [
            'icon' => '⚠️',
            'text' => 'سرویس منقضی شد: ' . htmlspecialchars($exp['Service_id']),
            'time' => $timeAgo,
            'type' => 'warning'
        ];
    }
    
    // Sort all activities by timestamp (most recent first)
    usort($activities, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });
    
    // Limit to 10 most recent activities
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
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'همین الان';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' دقیقه پیش';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' ساعت پیش';
    } else {
        $days = floor($difference / 86400);
        return $days . ' روز پیش';
    }
}
?>
