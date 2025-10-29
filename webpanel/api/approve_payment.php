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
$note = $_POST['note'] ?? '';
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
    // Centralized approval: updates status to 'paid', updates balance, sends Telegram DMs, posts admin report.
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
