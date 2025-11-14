<?php
/**
 * Bot Control API
 * Handles bot process control, logs, and webhook management
 */

// Prevent any output before JSON
ob_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bot_core.php';

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
        if ($domain === '') { 
            echo json_encode(['success'=>false,'message'=>'دامنه را وارد کنید']); 
            break; 
        }
        
        // Update config.php using ConfigUpdater
        require_once __DIR__ . '/config_updater.php';
        $cfg = __DIR__ . '/../../config.php';
        try {
            $updater = new ConfigUpdater($cfg);
            $updater->set('domainhosts', $domain);
            $updater->update();
        } catch (Exception $e) {
            error_log("Config update error: " . $e->getMessage());
        }
        
        // Update nginx server_name
        $nginx_config = '/etc/nginx/sites-available/mirza_pro';
        if (file_exists($nginx_config)) {
            $nginx_content = file_get_contents($nginx_config);
            $nginx_content = preg_replace(
                "/server_name\s+[^;]+;/",
                "server_name {$domain};",
                $nginx_content
            );
            @file_put_contents($nginx_config, $nginx_content);
        }
        
        [$code1, $out1] = run_cmd("nginx -t && systemctl reload nginx 2>&1");
        
        // Automatically issue SSL
        $ssl_msg = '';
        $ssl_success = false;
        $ssl_email = $email ?: "admin@{$domain}";
        
        // Ensure certbot is installed
        [$install_code, $install_out] = run_cmd("which certbot || (apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq certbot python3-certbot-nginx 2>&1)");
        
        // Issue SSL certificate (run in background for faster response)
        $ssl_cmd = "certbot --nginx -d {$domain} --redirect --non-interactive --agree-tos -m {$ssl_email} 2>&1";
        [$ssl_code, $ssl_out] = run_cmd($ssl_cmd);
        
        if ($ssl_code === 0) {
            $ssl_success = true;
            $ssl_msg = '✓ SSL certificate issued successfully';
            
            // Update webhook to HTTPS
            require_once __DIR__ . '/../../config.php';
            if (!empty($APIKEY) && $APIKEY !== '{API_KEY}') {
                $webhook_url = "https://{$domain}/index.php";
                $ch = curl_init("https://api.telegram.org/bot{$APIKEY}/setWebhook");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => ['url' => $webhook_url],
                    CURLOPT_TIMEOUT => 10
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        } else {
            $ssl_msg = '⚠ SSL setup failed: ' . implode("\n", array_slice($ssl_out, -3));
        }
        
        log_activity($_SESSION['admin_id'], 'set_domain', "domain={$domain}, ssl=" . ($ssl_success ? 'installed' : 'failed'));
        
        $message = $code1 === 0 ? 'دامنه و Nginx بروزرسانی شد' : 'خطا در بروزرسانی Nginx';
        if ($ssl_success) {
            $message .= ' | SSL نصب شد';
        }
        
        ob_clean();
        echo json_encode([
            'success' => $code1 === 0,
            'message' => $message,
            'details' => $out1,
            'ssl' => $ssl_msg,
            'ssl_success' => $ssl_success
        ]);
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
            require_once __DIR__ . '/../../config.php';
            if (!empty($APIKEY)) {
                $webhook_url = "https://{$domain}/index.php";
                $ch = curl_init("https://api.telegram.org/bot{$APIKEY}/setWebhook");
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
        
        echo json_encode([
            'success' => $code === 0,
            'message' => $code === 0 ? 'SSL صادر شد' : 'خطا در صدور SSL',
            'details' => implode("\n", array_slice($out, -10))
        ]);
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
