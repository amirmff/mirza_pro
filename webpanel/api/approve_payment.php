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
$note = $_POST['note'] ?? '';

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit;
}

try {
    // Use bot_core's approvePayment which:
    // 1. Updates Payment_report.payment_Status to 'completed'
    // 2. Adds balance to user.Balance
    // 3. Sends Telegram notification to user
    // 4. Sends notification to admin channel
    $admin = $auth->getCurrentAdmin();
    $admin_note = "Approved by {$admin['username']}" . ($note ? ": $note" : "");
    
    $result = approvePayment($payment_id, $admin_note);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Payment approved and user notified via Telegram' : 'Failed to approve payment'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
