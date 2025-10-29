<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bot_core.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$admin = $auth->getCurrentAdmin();
if (!$admin || (($admin['rule'] ?? '') !== 'administrator')) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$payment_id = $_POST['payment_id'] ?? null;
$reason = $_POST['reason'] ?? 'رد شده توسط ادمین';
$csrf = $_POST['csrf_token'] ?? '';

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit;
}
if (!$auth->verifyCsrfToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Centralized rejection: updates status to 'rejected' and notifies user.
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
