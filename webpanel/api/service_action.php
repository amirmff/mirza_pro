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
$invoice_id = $_POST['invoice_id'] ?? '';

if (empty($action) || empty($invoice_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $invoice = select('invoice', '*', 'id_invoice', $invoice_id, 'select');
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }

    $username = $invoice['username'];
    $name_panel = $invoice['Service_location'];

    // Fetch panel row to derive code_panel when needed
    $panelRow = getPanelByName($name_panel);
    $code_panel = $panelRow['code_panel'] ?? null;

    $result = null;

    // admin activity log helper
    $log = function($actionName,$desc) use ($admin) {
        try { global $pdo; $stmt=$pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at) VALUES (:a,:b,:c,:d,NOW())");
            $stmt->execute([':a'=>$admin['id_admin'],':b'=>$actionName,':c'=>$desc,':d'=>($_SERVER['REMOTE_ADDR']??'unknown')]); } catch (Exception $e) { error_log('admin log fail: '.$e->getMessage()); }
    };

    switch ($action) {
        case 'reset_usage':
            $result = (new ManagePanel())->ResetUserDataUsage($username, $name_panel);
            $log('service_reset_usage', "invoice $invoice_id user $username panel $name_panel");
            if ($result && ($result['status'] ?? false)) { sendNotification('services', "🔄 ریست مصرف | کاربر: {$username} | پنل: {$name_panel}"); }
            echo json_encode(['success' => (bool)($result['status'] ?? false), 'result' => $result]);
            break;

        case 'toggle_status':
            $result = (new ManagePanel())->Change_status($username, $name_panel);
            $log('service_toggle_status', "invoice $invoice_id user $username panel $name_panel");
            if ($result && ($result['status'] ?? false)) { sendNotification('services', "⏯️ تغییر وضعیت سرویس | کاربر: {$username} | پنل: {$name_panel}"); }
            echo json_encode(['success' => (bool)($result['status'] ?? false), 'result' => $result]);
            break;

        case 'revoke_sub':
            $result = (new ManagePanel())->Revoke_sub($name_panel, $username);
            $log('service_revoke_sub', "invoice $invoice_id user $username panel $name_panel");
            if ($result && ($result['status'] ?? false)) { sendNotification('services', "♻️ بازتولید لینک ساب | کاربر: {$username} | پنل: {$name_panel}"); }
            echo json_encode(['success' => (bool)($result['status'] ?? false), 'result' => $result]);
            break;

        case 'delete_service':
            $result = (new ManagePanel())->RemoveUser($name_panel, $username);
            if ($result && ($result['status'] ?? '') === 'successful') {
                update('invoice', 'Status', 'deactive', 'id_invoice', $invoice_id);
                sendNotification('services', "🗑️ حذف سرویس | کاربر: {$username} | پنل: {$name_panel}");
            }
            $log('service_delete', "invoice $invoice_id user $username panel $name_panel");
            echo json_encode(['success' => (bool)($result['status'] ?? false), 'result' => $result]);
            break;

        case 'extend_days':
            $days = max(0, (int)($_POST['days'] ?? 0));
            if ($days <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid days']); break; }
            $result = (new ManagePanel())->extra_time($username, $code_panel, $days);
            $log('service_extend_days', "invoice $invoice_id user $username +{$days}d");
            if ($result && ($result['status'] ?? false)) { sendNotification('services', "⏰ تمدید زمان | ${days} روز | کاربر: {$username}"); }
            echo json_encode(['success' => (bool)($result['status'] ?? false), 'result' => $result]);
            break;

        case 'add_volume':
            $gb = max(0, (int)($_POST['gb'] ?? 0));
            if ($gb <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid GB']); break; }
            $result = (new ManagePanel())->extra_volume($username, $code_panel, $gb);
            $log('service_add_volume', "invoice $invoice_id user $username +{$gb}GB");
            if ($result && ($result['status'] ?? false)) { sendNotification('services', "📦 افزایش حجم | ${gb}GB | کاربر: {$username}"); }
            echo json_encode(['success' => (bool)($result['status'] ?? false), 'result' => $result]);
            break;

        case 'extend_both':
            $days = max(0, (int)($_POST['days'] ?? 0));
            $gb = max(0, (int)($_POST['gb'] ?? 0));
            if ($days <= 0 && $gb <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid params']); break; }
            $method = 'اضافه شدن زمان و حجم به ماه بعد';
            $code_product = $invoice['code_product'] ?? 'custom_volume';
            $result = (new ManagePanel())->extend($method, $gb, $days, $username, $code_product, $code_panel);
            $log('service_extend_both', "invoice $invoice_id user $username +{$days}d +{$gb}GB");
            if ($result && ($result['status'] ?? false)) { sendNotification('services', "🔧 تمدید زمان/حجم | ${days}d/${gb}GB | کاربر: {$username}"); }
            echo json_encode(['success' => (bool)($result['status'] ?? false), 'result' => $result]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
