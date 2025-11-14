<?php
/**
 * Bot Configuration API - COMPLETE REWRITE
 * Handles all bot configuration operations
 */

// Prevent any output before JSON
ob_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config_updater.php';
require_once __DIR__ . '/../includes/bot_control.php';

// Clear any output that might have been generated
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_config':
        $bot_token = trim($_POST['bot_token'] ?? '');
        $admin_id = trim($_POST['admin_id'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        
        if (empty($bot_token) || empty($admin_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Bot token and admin ID are required']);
            exit;
        }
        
        try {
            $config_file = __DIR__ . '/../../config.php';
            $updater = new ConfigUpdater($config_file);
            
            // Update all config values
            $updater->set('APIKEY', $bot_token);
            $updater->set('adminnumber', $admin_id);
            
            if (!empty($domain)) {
                $updater->set('domainhosts', $domain);
            }
            
            // Get bot username
            $bot_info = @file_get_contents("https://api.telegram.org/bot{$bot_token}/getMe");
            if ($bot_info) {
                $bot_data = json_decode($bot_info, true);
                if ($bot_data && $bot_data['ok'] && isset($bot_data['result']['username'])) {
                    $updater->set('usernamebot', '@' . $bot_data['result']['username']);
                }
            }
            
            // Update config
            $updater->update();
            
            // Update webhook
            $webhook_url = !empty($domain) ? "https://{$domain}/index.php" : "http://" . ($_SERVER['SERVER_ADDR'] ?? 'localhost') . "/index.php";
            $ch = curl_init("https://api.telegram.org/bot{$bot_token}/setWebhook");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['url' => $webhook_url],
                CURLOPT_TIMEOUT => 10
            ]);
            curl_exec($ch);
            curl_close($ch);
            
            // Webhook-based bot - no need to restart supervisor
            // Just verify webhook was set
            $webhook_check = curl_init("https://api.telegram.org/bot{$bot_token}/getWebhookInfo");
            curl_setopt($webhook_check, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($webhook_check, CURLOPT_TIMEOUT, 5);
            $webhook_info = json_decode(curl_exec($webhook_check), true);
            curl_close($webhook_check);
            
            $webhook_set = ($webhook_info['ok'] ?? false) && !empty($webhook_info['result']['url'] ?? '');
            
            log_activity($currentAdmin['id_admin'], 'bot_config_update', "Updated bot configuration");
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Configuration updated successfully' . ($webhook_set ? ' - Webhook فعال است' : ''),
                'webhook_set' => $webhook_set,
                'webhook_url' => $webhook_url
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Bot config update error: " . $e->getMessage());
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Failed: ' . $e->getMessage()
            ]);
            exit;
        }
        
    case 'get_config':
        require_once __DIR__ . '/../../config.php';
        ob_clean(); // Clear any output from config.php
        echo json_encode([
            'success' => true,
            'config' => [
                'bot_token' => !empty($APIKEY) && $APIKEY !== '{API_KEY}' ? substr($APIKEY, 0, 10) . '...' : '',
                'admin_id' => $adminnumber ?? '',
                'domain' => $domainhosts ?? '',
                'bot_username' => $usernamebot ?? ''
            ]
        ]);
        exit;
        
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}
