<?php
/**
 * Bot Control API
 * Handles bot process control, logs, and webhook management
 */

require_once __DIR__ . '/auth.php';

// Require authentication
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check admin permission
check_permission('administrator');

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'start':
        exec('supervisorctl start mirza_bot 2>&1', $output, $return_code);
        if ($return_code === 0) {
            log_activity($_SESSION['admin_id'], 'bot_start', 'Started bot process');
            echo json_encode(['success' => true, 'message' => 'ربات با موفقیت راه‌اندازی شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در راه‌اندازی ربات: ' . implode("\n", $output)]);
        }
        break;
        
    case 'stop':
        exec('supervisorctl stop mirza_bot 2>&1', $output, $return_code);
        if ($return_code === 0) {
            log_activity($_SESSION['admin_id'], 'bot_stop', 'Stopped bot process');
            echo json_encode(['success' => true, 'message' => 'ربات با موفقیت متوقف شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در توقف ربات: ' . implode("\n", $output)]);
        }
        break;
        
    case 'restart':
        exec('supervisorctl restart mirza_bot 2>&1', $output, $return_code);
        if ($return_code === 0) {
            log_activity($_SESSION['admin_id'], 'bot_restart', 'Restarted bot process');
            echo json_encode(['success' => true, 'message' => 'ربات با موفقیت راه‌اندازی مجدد شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در راه‌اندازی مجدد ربات: ' . implode("\n", $output)]);
        }
        break;
        
    case 'webhook':
        require_once __DIR__ . '/../../config.php';
        
        if (!isset($APIKEY) || empty($APIKEY)) {
            echo json_encode(['success' => false, 'message' => 'توکن ربات تنظیم نشده است']);
            break;
        }
        
        // Determine webhook URL
        $webhook_url = '';
        if (!empty($domainhosts)) {
            $webhook_url = "https://{$domainhosts}/webhooks.php";
        } else {
            $webhook_url = "http://{$_SERVER['SERVER_ADDR']}/webhooks.php";
        }
        
        // Set webhook
        $ch = curl_init("https://api.telegram.org/bot{$APIKEY}/setWebhook");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $webhook_url]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($http_code === 200 && ($data['ok'] ?? false)) {
            log_activity($_SESSION['admin_id'], 'webhook_update', "Updated webhook to: {$webhook_url}");
            echo json_encode(['success' => true, 'message' => 'وب‌هوک با موفقیت تنظیم شد']);
        } else {
            $error_msg = $data['description'] ?? 'Unknown error';
            echo json_encode(['success' => false, 'message' => "خطا در تنظیم وب‌هوک: {$error_msg}"]);
        }
        break;
        
    case 'logs':
        $log_file = '/var/log/mirza_bot.log';
        
        if (!file_exists($log_file)) {
            // Try alternative location
            $log_file = __DIR__ . '/../../logs/bot.log';
        }
        
        if (file_exists($log_file)) {
            // Get last 500 lines
            exec("tail -n 500 {$log_file} 2>&1", $output, $return_code);
            
            if ($return_code === 0) {
                $logs = implode("\n", $output);
                echo json_encode(['success' => true, 'logs' => $logs]);
            } else {
                echo json_encode(['success' => false, 'message' => 'خطا در خواندن لاگ‌ها']);
            }
        } else {
            echo json_encode(['success' => true, 'logs' => 'فایل لاگ یافت نشد']);
        }
        break;
        
    case 'clear_logs':
        $log_file = '/var/log/mirza_bot.log';
        
        if (!file_exists($log_file)) {
            $log_file = __DIR__ . '/../../logs/bot.log';
        }
        
        if (file_exists($log_file)) {
            if (file_put_contents($log_file, '') !== false) {
                log_activity($_SESSION['admin_id'], 'logs_cleared', 'Cleared bot logs');
                echo json_encode(['success' => true, 'message' => 'لاگ‌ها با موفقیت پاک شدند']);
            } else {
                echo json_encode(['success' => false, 'message' => 'خطا در پاک کردن لاگ‌ها']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'فایل لاگ یافت نشد']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
