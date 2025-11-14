<?php
/**
 * Bot Control API
 * Handles bot process control, logs, and webhook management
 */

// Prevent any output before JSON
ob_start();

require_once __DIR__ . '/auth.php';

// Clear any output
ob_clean();

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

// Actions that don't need bot_core.php (avoid loading config.php which may have errors)
$no_bot_core_actions = ['set_domain', 'set_webhook', 'webhook', 'get_webhook', 'delete_webhook', 'issue_ssl', 'renew_ssl'];

// Only load bot_core.php for actions that need it (for $pdo, log_activity, etc.)
if (!in_array($action, $no_bot_core_actions)) {
    try {
        require_once __DIR__ . '/bot_core.php';
    } catch (Exception $e) {
        error_log("bot_core.php load error: " . $e->getMessage());
        // Continue anyway for actions that don't need it
    }
}

function log_activity($admin_id, $action, $description) {
    global $pdo;
    if (!isset($pdo)) {
        // If bot_core.php wasn't loaded, try to load it now
        try {
            require_once __DIR__ . '/bot_core.php';
        } catch (Exception $e) {
            error_log('log_activity: bot_core.php not available');
            return;
        }
    }
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
        // Get APIKEY and domain from config file directly (avoid loading full config.php)
        $config_content = file_get_contents(__DIR__ . '/../../config.php');
        preg_match("/\\\$APIKEY\s*=\s*['\"]([^'\"]+)['\"];/", $config_content, $api_matches);
        preg_match("/\\\$domainhosts\s*=\s*['\"]([^'\"]+)['\"];/", $config_content, $domain_matches);
        
        $bot_token = $api_matches[1] ?? '';
        $domain = $_POST['domain'] ?? ($domain_matches[1] ?? '');
        
        if (empty($bot_token) || $bot_token === '{API_KEY}') {
            ob_clean();
            echo json_encode(['success'=>false,'message'=>'توکن ربات تنظیم نشده است']);
            exit;
        }
        
        $scheme = (!empty($domain) && $domain !== '{domain_name}' ? 'https' : 'http');
        // Telegram webhook endpoint is index.php in this project
        if (!empty($domain) && $domain !== '{domain_name}') {
            $webhook_url = "https://{$domain}/index.php";
        } else {
            $server_ip = $_SERVER['SERVER_ADDR'] ?? 'localhost';
            $webhook_url = "http://{$server_ip}/index.php";
        }
        
        $ch = curl_init("https://api.telegram.org/bot{$bot_token}/setWebhook");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['url' => $webhook_url],
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if ($http_code === 200 && ($data['ok'] ?? false)) {
            log_activity($_SESSION['admin_id'], 'webhook_update', "Updated webhook to: {$webhook_url}");
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'وب‌هوک با موفقیت تنظیم شد', 'url'=>$webhook_url]);
        } else {
            $error_msg = $data['description'] ?? 'Unknown error';
            ob_clean();
            echo json_encode(['success' => false, 'message' => "خطا در تنظیم وب‌هوک: {$error_msg}", 'response'=>$response]);
        }
        exit;

    case 'get_webhook':
        // Get APIKEY from config file directly (avoid loading full config.php)
        $config_content = file_get_contents(__DIR__ . '/../../config.php');
        preg_match("/\\\$APIKEY\s*=\s*['\"]([^'\"]+)['\"];/", $config_content, $matches);
        $bot_token = $matches[1] ?? '';
        
        if (empty($bot_token) || $bot_token === '{API_KEY}') {
            ob_clean();
            echo json_encode(['success'=>false,'message'=>'توکن ربات تنظیم نشده است']);
            exit;
        }
        
        $res = file_get_contents("https://api.telegram.org/bot{$bot_token}/getWebhookInfo");
        ob_clean();
        echo $res ?: json_encode(['success'=>false]);
        exit;
        
    case 'delete_webhook':
        // Get APIKEY from config file directly (avoid loading full config.php)
        $config_content = file_get_contents(__DIR__ . '/../../config.php');
        preg_match("/\\\$APIKEY\s*=\s*['\"]([^'\"]+)['\"];/", $config_content, $matches);
        $bot_token = $matches[1] ?? '';
        
        if (empty($bot_token) || $bot_token === '{API_KEY}') {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'توکن ربات تنظیم نشده است']);
            exit;
        }
        
        $ch = curl_init("https://api.telegram.org/bot{$bot_token}/deleteWebhook");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['drop_pending_updates' => 'true'],
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if ($http_code === 200 && ($data['ok'] ?? false)) {
            log_activity($_SESSION['admin_id'], 'webhook_delete', 'Deleted webhook');
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Webhook حذف شد']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'خطا در حذف Webhook: ' . ($data['description'] ?? 'Unknown error')]);
        }
        exit;

    case 'set_domain':
        $domain = trim($_POST['domain'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($domain === '') { 
            ob_clean();
            echo json_encode(['success'=>false,'message'=>'دامنه را وارد کنید']); 
            exit;
        }
        
        try {
            // Update config.php using ConfigUpdater
            require_once __DIR__ . '/config_updater.php';
            require_once __DIR__ . '/nginx_manager.php';
            
            $cfg = __DIR__ . '/../../config.php';
            $updater = new ConfigUpdater($cfg);
            $updater->set('domainhosts', $domain);
            $updater->update();
            
            // Update Nginx configuration
            $nginx = new NginxManager();
            $nginx->updateDomain($domain);
            $nginx->reload();
            
            // Automatically install SSL
            $ssl_success = false;
            $ssl_msg = '';
            $ssl_email = $email ?: "admin@{$domain}";
            
            try {
                $nginx->installSSL($domain, $ssl_email);
                $ssl_success = true;
                $ssl_msg = '✓ SSL certificate issued successfully';
                
                // Update webhook to HTTPS
                $config_content = file_get_contents(__DIR__ . '/../../config.php');
                preg_match("/\\\$APIKEY\s*=\s*['\"]([^'\"]+)['\"];/", $config_content, $matches);
                $bot_token = $matches[1] ?? '';
                
                if (!empty($bot_token) && $bot_token !== '{API_KEY}') {
                    $webhook_url = "https://{$domain}/index.php";
                    $ch = curl_init("https://api.telegram.org/bot{$bot_token}/setWebhook");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => ['url' => $webhook_url],
                        CURLOPT_TIMEOUT => 10
                    ]);
                    $webhook_response = curl_exec($ch);
                    $webhook_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($webhook_http_code === 200) {
                        $ssl_msg .= ' | Webhook تنظیم شد';
                    }
                }
            } catch (Exception $ssl_e) {
                $ssl_msg = '⚠ SSL setup failed: ' . $ssl_e->getMessage();
                error_log("SSL installation error: " . $ssl_e->getMessage());
            }
            
            log_activity($_SESSION['admin_id'], 'set_domain', "domain={$domain}, ssl=" . ($ssl_success ? 'installed' : 'failed'));
            
            $message = 'دامنه و Nginx بروزرسانی شد';
            if ($ssl_success) {
                $message .= ' | SSL نصب شد و Webhook تنظیم شد';
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => $message,
                'ssl' => $ssl_msg,
                'ssl_success' => $ssl_success
            ]);
        } catch (Exception $e) {
            error_log("Domain setup error: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success'=>false,'message'=>'خطا: ' . $e->getMessage()]);
        }
        exit;

    case 'issue_ssl':
        $domain = trim($_POST['domain'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($domain==='' || $email==='') { 
            echo json_encode(['success'=>false,'message'=>'دامنه و ایمیل لازم است']); 
            break; 
        }
        
        // Ensure certbot packages
        [$install_code, $install_out] = run_cmd("which certbot || (apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq certbot python3-certbot-nginx 2>&1)");
        
        // Issue SSL certificate
        [$code, $out] = run_cmd("certbot --nginx -d {$domain} --redirect --non-interactive --agree-tos -m {$email} 2>&1");
        
        if ($code === 0) {
            // Update webhook to HTTPS
            // Get APIKEY from config without requiring full config.php load
            $config_content = file_get_contents(__DIR__ . '/../../config.php');
            preg_match("/\\\$APIKEY\s*=\s*['\"]([^'\"]+)['\"];/", $config_content, $matches);
            $bot_token = $matches[1] ?? '';
            
            if (!empty($bot_token) && $bot_token !== '{API_KEY}') {
                $webhook_url = "https://{$domain}/index.php";
                $ch = curl_init("https://api.telegram.org/bot{$bot_token}/setWebhook");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => ['url' => $webhook_url],
                    CURLOPT_TIMEOUT => 10
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
            log_activity($_SESSION['admin_id'], 'ssl_install', "SSL installed for: {$domain}");
        }
        
        ob_clean();
        // $out is a string, get last 10 lines
        $out_lines = explode("\n", $out);
        echo json_encode([
            'success' => $code === 0,
            'message' => $code === 0 ? 'SSL صادر شد' : 'خطا در صدور SSL',
            'details' => implode("\n", array_slice($out_lines, -10))
        ]);
        exit;

    case 'renew_ssl':
        [$code, $out] = run_cmd('certbot renew --nginx --no-random-sleep-on-renew -n');
        ob_clean();
        echo json_encode(['success'=> $code===0, 'message'=> $code===0?'تمدید SSL اجرا شد':'خطا در تمدید SSL', 'details'=>$out]);
        exit;
        
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
