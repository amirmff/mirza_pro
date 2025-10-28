<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bot_core.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Forbidden']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = (int)($_POST['user_id'] ?? 0);

if ($user_id <= 0 || $action === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $user = select('user', '*', 'id', $user_id, 'select');
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // admin activity log helper
    $log = function($actionName,$desc) use ($admin) {
        try {
            global $pdo; $stmt=$pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at) VALUES (:a,:b,:c,:d,NOW())");
            $stmt->execute([':a'=>$admin['id_admin'],':b'=>$actionName,':c'=>$desc,':d'=>($_SERVER['REMOTE_ADDR']??'unknown')]);
        } catch (Exception $e) { error_log('admin log fail: '.$e->getMessage()); }
    };

    switch ($action) {
        case 'block':
            update('user', 'User_Status', 'block', 'id', $user_id);
            $log('user_block', "Blocked user $user_id");
            echo json_encode(['success' => true]);
            break;
        case 'unblock':
            update('user', 'User_Status', 'Active', 'id', $user_id);
            $log('user_unblock', "Unblocked user $user_id");
            echo json_encode(['success' => true]);
            break;
        case 'message':
            $text = trim($_POST['text'] ?? '');
            if ($text === '') { echo json_encode(['success'=>false,'message'=>'Empty message']); break; }
            $ok = sendTelegramMessage($user_id, $text);
            $log('user_message', "Message to $user_id");
            echo json_encode(['success' => (bool)$ok]);
            break;
        case 'balance_add':
            $amount = (int)($_POST['amount'] ?? 0);
            $new = max(0, ((int)$user['Balance']) + $amount);
            update('user', 'Balance', $new, 'id', $user_id);
            $log('balance_add', "Added $amount to $user_id");
            echo json_encode(['success' => true, 'balance' => $new]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
