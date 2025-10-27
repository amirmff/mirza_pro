<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bot_core.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$payment_id = $_POST['payment_id'] ?? null;
$reason = $_POST['reason'] ?? 'رد شده توسط ادمین';

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit;
}

try {
    // Use bot_core's rejectPayment which:
    // 1. Updates Payment_report.payment_Status to 'rejected'
    // 2. Sends Telegram notification to user with rejection reason
    $result = rejectPayment($payment_id, $reason);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Payment rejected and user notified' : 'Failed to reject payment'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
