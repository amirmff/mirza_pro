<?php
/**
 * Bot Configuration API
 * Allows changing bot token, admin ID, and domain from web panel
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config_updater.php';
require_once __DIR__ . '/../includes/bot_control.php'; // For log_activity function

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

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_config':
        $bot_token = trim($_POST['bot_token'] ?? '');
        $admin_id = trim($_POST['admin_id'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        
        if (empty($bot_token) || empty($admin_id)) {
            echo json_encode(['success' => false, 'message' => 'Bot token and admin ID are required']);
            exit;
        }
        
        try {
            $config_file = __DIR__ . '/../../config.php';
            $updater = new ConfigUpdater($config_file);
            
            // Update bot token
            $updater->set('APIKEY', $bot_token);
            
            // Update admin ID
            $updater->set('adminnumber', $admin_id);
            
            // Update domain if provided
            if (!empty($domain)) {
                $updater->set('domainhosts', $domain);
            }
            
            // Get bot username from Telegram API
            $bot_info = @file_get_contents("https://api.telegram.org/bot{$bot_token}/getMe");
            if ($bot_info) {
                $bot_data = json_decode($bot_info, true);
                if ($bot_data && $bot_data['ok'] && isset($bot_data['result']['username'])) {
                    $updater->set('usernamebot', '@' . $bot_data['result']['username']);
                }
            }
            
            // Perform the update
            $updater->update();
            
            // Update webhook if domain provided
            if (!empty($domain)) {
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
            
            // Restart bot
            $bot_restarted = $updater->restartBot();
            
            // Log activity
            log_activity($currentAdmin['id_admin'], 'bot_config_update', "Updated bot token and admin ID");
            
            echo json_encode([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'bot_restarted' => $bot_restarted
            ]);
            
        } catch (Exception $e) {
            error_log("Bot config update error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'get_config':
        // Return current config (without exposing sensitive data)
        require_once __DIR__ . '/../../config.php';
        
        echo json_encode([
            'success' => true,
            'config' => [
                'bot_token_configured' => ($APIKEY ?? '') !== '{API_KEY}' && !empty($APIKEY),
                'admin_id_configured' => ($adminnumber ?? '') !== '{admin_number}' && !empty($adminnumber),
                'domain_configured' => !empty($domainhosts) && ($domainhosts ?? '') !== '{domain_name}',
                'domain' => $domainhosts ?? '',
                'bot_username' => $usernamebot ?? ''
            ]
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

