<?php
/**
 * Bot Control API
 * Handles bot process control, logs, and webhook management
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bot_core.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentAdmin = $auth->getCurrentAdmin();
if (!$currentAdmin || ($currentAdmin['rule'] ?? '') !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function log_activity($admin_id, $action, $description) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at) VALUES (:admin_id, :action, :description, :ip, NOW())");
        $stmt->execute([
            ':admin_id' => $admin_id,
            ':action' => $action,
            ':description' => $description,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    } catch (Exception $e) {
        error_log('log_activity failed: ' . $e->getMessage());
    }
}

// Helpers
function run_cmd($cmd) {
    $out = [];$code = 0; exec($cmd . ' 2>&1', $out, $code); return [$code, implode("\n", $out)];
}

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
        
    case 'set_webhook':
    case 'webhook':
        require_once __DIR__ . '/../../config.php';
        if (empty($APIKEY)) { echo json_encode(['success'=>false,'message'=>'توکن ربات تنظیم نشده است']); break; }
        $domain = $_POST['domain'] ?? ($domainhosts ?? '');
        $scheme = (!empty($domain) ? 'https' : 'http');
        // Telegram webhook endpoint is index.php in this project
        $webhook_url = !empty($domain) ? "$scheme://{$domain}/index.php" : (isset($_SERVER['SERVER_ADDR'])?"http://{$_SERVER['SERVER_ADDR']}/index.php":"");
        $ch = curl_init("https://api.telegram.org/bot{$APIKEY}/setWebhook");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>['url'=>$webhook_url]]);
        $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode($response, true);
        if ($http_code === 200 && ($data['ok'] ?? false)) {
            log_activity($_SESSION['admin_id'], 'webhook_update', "Updated webhook to: {$webhook_url}");
            echo json_encode(['success' => true, 'message' => 'وب‌هوک با موفقیت تنظیم شد', 'url'=>$webhook_url]);
        } else {
            $error_msg = $data['description'] ?? 'Unknown error';
            echo json_encode(['success' => false, 'message' => "خطا در تنظیم وب‌هوک: {$error_msg}", 'response'=>$response]);
        }
        break;

    case 'get_webhook':
        require_once __DIR__ . '/../../config.php';
        if (empty($APIKEY)) { echo json_encode(['success'=>false,'message'=>'توکن ربات تنظیم نشده است']); break; }
        $res = file_get_contents("https://api.telegram.org/bot{$APIKEY}/getWebhookInfo");
        echo $res ?: json_encode(['success'=>false]);
        break;

    case 'set_domain':
        $domain = trim($_POST['domain'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($domain === '') { echo json_encode(['success'=>false,'message'=>'دامنه را وارد کنید']); break; }
        // Update nginx server_name
        [$code1, $out1] = run_cmd("sed -i 's/server_name .\+;/server_name {$domain};/' /etc/nginx/sites-available/mirza_pro && nginx -t && systemctl reload nginx");
        // Update config.php $domainhosts
        $cfg = __DIR__ . '/../../config.php';
        if (is_writable($cfg)) {
            $content = file_get_contents($cfg);
            $content = preg_replace("/\$domainhosts\s*=\s*'[^']*';/", "\$domainhosts = '{$domain}';", $content);
            file_put_contents($cfg, $content);
        }
        // Optionally issue SSL immediately if email provided
        $ssl_msg = '';
        if ($email !== '') {
            [$c, $o] = run_cmd("certbot --nginx -d {$domain} --redirect -n --agree-tos -m {$email}");
            $ssl_msg = $o;
        }
        log_activity($_SESSION['admin_id'], 'set_domain', "domain={$domain}");
        echo json_encode(['success'=> $code1===0, 'message'=> $code1===0?'دامنه و Nginx بروزرسانی شد':'خطا در بروزرسانی Nginx', 'details'=>$out1, 'ssl'=>$ssl_msg]);
        break;

    case 'issue_ssl':
        $domain = trim($_POST['domain'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($domain==='' || $email==='') { echo json_encode(['success'=>false,'message'=>'دامنه و ایمیل لازم است']); break; }
        // Ensure certbot packages
        run_cmd("apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y certbot python3-certbot-nginx");
        [$code, $out] = run_cmd("certbot --nginx -d {$domain} --redirect -n --agree-tos -m {$email}");
        echo json_encode(['success'=> $code===0, 'message'=> $code===0?'SSL صادر شد':'خطا در صدور SSL', 'details'=>$out]);
        break;

    case 'renew_ssl':
        [$code, $out] = run_cmd('certbot renew --nginx --no-random-sleep-on-renew -n');
        echo json_encode(['success'=> $code===0, 'message'=> $code===0?'تمدید SSL اجرا شد':'خطا در تمدید SSL', 'details'=>$out]);
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
