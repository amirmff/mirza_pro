<?php
/**
 * Bot Integration Core for Web Panel
 * This file bridges the web panel with the bot's existing functions and database
 */

// Include bot's core files
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../function.php';
require_once __DIR__ . '/../../botapi.php';
require_once __DIR__ . '/../../panels.php';
require_once __DIR__ . '/../../keyboard.php';

// Initialize ManagePanel for VPN operations
$ManagePanel = new ManagePanel();

/** Ensure notifications tables exist */
call_user_func(function () {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications_channels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(64) NOT NULL,
            chat_id BIGINT NOT NULL,
            topic_id BIGINT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cat_chat (category, chat_id, COALESCE(topic_id,0))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(64) NOT NULL,
            chat_id BIGINT NULL,
            topic_id BIGINT NULL,
            status VARCHAR(16) NOT NULL,
            error TEXT NULL,
            text TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cat_created (category, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { error_log('ensureNotificationsTables: '.$e->getMessage()); }
});

/**
 * Get bot settings
 */
function getBotSettings() {
    return select("setting", "*");
}

/**
 * Get all payment settings
 */
function getPaymentSettings() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM PaySetting");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['NamePay']] = $row['ValuePay'];
    }
    return $settings;
}

/**
 * Get text templates
 */
function getTextTemplates() {
    return select("textbot", "*", null, null, "fetchAll");
}

/**
 * Send Telegram message from web panel
 */
function sendTelegramMessage($user_id, $message, $keyboard = null, $parse_mode = 'HTML') {
    if ($keyboard) {
        $keyboard = json_encode($keyboard);
    }
    return sendmessage($user_id, $message, $keyboard, $parse_mode);
}

/** Send to arbitrary chat (channel/group) optionally forum topic */
function sendTelegramToChat($chat_id, $text, $topic_id = null, $parse_mode = 'HTML') {
    $params = [ 'chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse_mode ];
    if (!empty($topic_id)) { $params['message_thread_id'] = (int)$topic_id; }
    $res = telegram('sendmessage', $params);
    return is_array($res) ? ($res['ok'] ?? false) : (bool)$res;
}

/** Category-based notifier; logs each attempt */
function sendNotification($category, $text, $parse_mode = 'HTML') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT chat_id, topic_id FROM notifications_channels WHERE category = :cat AND enabled = 1");
        $stmt->execute([':cat' => $category]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return false;
        $okAny = false;
        foreach ($rows as $r) {
            $ok = sendTelegramToChat($r['chat_id'], $text, $r['topic_id'] ?? null, $parse_mode);
            $log = $pdo->prepare("INSERT INTO notifications_log (category, chat_id, topic_id, status, error, text) VALUES (:cat,:chat,:topic,:st,:er,:tx)");
            $log->execute([
                ':cat'=>$category,
                ':chat'=>$r['chat_id'],
                ':topic'=>$r['topic_id'] ?? null,
                ':st'=>$ok ? 'sent' : 'failed',
                ':er'=>$ok ? null : 'send failed',
                ':tx'=>$text,
            ]);
            if ($ok) $okAny = true;
        }
        return $okAny;
    } catch (Exception $e) {
        try { $pdo->prepare("INSERT INTO notifications_log (category, status, error, text) VALUES (:cat,'error',:er,:tx)")->execute([':cat'=>$category, ':er'=>$e->getMessage(), ':tx'=>$text]); } catch (Exception $e2) {}
        return false;
    }
}

/**
 * Get user complete info
 */
function getUserInfo($user_id) {
    $user = select("user", "*", "id", $user_id, "select");
    if (!$user) return null;
    
    // Get user's invoices
    $invoices = [];
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM invoice WHERE id_user = ? ORDER BY time_sell DESC");
    $stmt->execute([$user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's payments
    $payments = [];
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM Payment_report WHERE id_user = ? ORDER BY time DESC");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'user' => $user,
        'invoices' => $invoices,
        'payments' => $payments
    ];
}

/**
 * Get all panels
 */
function getAllPanels() {
    return select("marzban_panel", "*", null, null, "fetchAll");
}

/**
 * Get panel by name
 */
function getPanelByName($name) {
    return select("marzban_panel", "*", "name_panel", $name, "select");
}

/**
 * Get all products
 */
function getAllProducts() {
    return select("product", "*", null, null, "fetchAll");
}

/**
 * Get product by code
 */
function getProductByCode($code) {
    return select("product", "*", "code_product", $code, "select");
}

/**
 * Get all discount codes
 */
function getAllDiscounts() {
    return select("DiscountSell", "*", null, null, "fetchAll");
}

/**
 * Get invoice by ID
 */
function getInvoice($invoice_id) {
    return select("invoice", "*", "id_invoice", $invoice_id, "select");
}

/**
 * Get payment by ID
 */
function getPayment($payment_id) {
    return select("Payment_report", "*", "id", $payment_id, "select");
}

/**
 * Create service via ManagePanel
 */
function createService($panel_name, $product_code, $username, $config_data) {
    global $ManagePanel;
    return $ManagePanel->createUser($panel_name, $product_code, $username, $config_data);
}

/**
 * Extend service
 */
function extendService($invoice_id, $days) {
    $invoice = getInvoice($invoice_id);
    if (!$invoice) return false;
    
    global $pdo;
    $panel = getPanelByName($invoice['Service_location']);
    if (!$panel) return false;
    
    // Calculate new expiry
    $current_time = select("invoice", "Service_time", "id_invoice", $invoice_id, "select")['Service_time'];
    $new_time = $current_time + ($days * 86400);
    
    update("invoice", "Service_time", $new_time, "id_invoice", $invoice_id);
    
    return true;
}

/**
 * Delete service
 */
function deleteService($invoice_id) {
    $invoice = getInvoice($invoice_id);
    if (!$invoice) return false;
    
    global $pdo, $ManagePanel;
    
    $panel = getPanelByName($invoice['Service_location']);
    if ($panel) {
        // Delete from panel
        $ManagePanel->deleteUser($panel['name_panel'], $invoice['username']);
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = ?");
    return $stmt->execute([$invoice_id]);
}

/**
 * Format bytes to human readable
 * Guard against redeclaration when function.php is already included.
 */
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

/**
 * Persian date formatter
 */
function toPersianDate($timestamp) {
    require_once __DIR__ . '/../../jdf.php';
    return jdate('Y/m/d H:i', $timestamp);
}

/**
 * Get statistics
 */
function getStatistics() {
    global $pdo;
    
    $stats = [];
    
    // Total users
    $stats['total_users'] = select("user", "COUNT(*)", null, null, "count");
    
    // Active users (have at least one active service)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT id_user) as count FROM invoice WHERE Status = 'active'");
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active services
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM invoice WHERE Status = 'active'");
    $stats['active_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total revenue (treat both 'paid' and 'completed' as paid)
    $stmt = $pdo->query("SELECT SUM(CAST(price AS UNSIGNED)) as total FROM Payment_report WHERE payment_Status IN ('paid','completed')");
    $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Today revenue
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT SUM(CAST(price AS UNSIGNED)) as total FROM Payment_report WHERE payment_Status IN ('paid','completed') AND DATE(time) = ?");
    $stmt->execute([$today]);
    $stats['today_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // New users today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user WHERE DATE(FROM_UNIXTIME(register)) = ?");
    $stmt->execute([$today]);
    $stats['new_users_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending payments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Payment_report WHERE payment_Status = 'pending'");
    $stats['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}

/**
 * Update balance
 */
function updateUserBalance($user_id, $amount) {
    $current = select("user", "Balance", "id", $user_id, "select")['Balance'];
    $new_balance = $current + $amount;
    update("user", "Balance", $new_balance, "id", $user_id);
    return $new_balance;
}

/**
 * Approve payment
 */
function approvePayment($payment_id, $admin_note = '') {
    global $pdo;
    
    $payment = getPayment($payment_id);
    if (!$payment || $payment['payment_Status'] !== 'pending') {
        return false;
    }
    
    // Update payment status (normalize to 'paid' to match existing data)
    update("Payment_report", "payment_Status", "paid", "id", $payment_id);
    if ($admin_note) {
        update("Payment_report", "dec_not_confirmed", $admin_note, "id", $payment_id);
    }
    
    // Add balance to user
    updateUserBalance($payment['id_user'], $payment['price']);
    
    // Send notification to user
    $setting = getBotSettings();
    $message = "✅ پرداخت شما با موفقیت تایید شد\n\n💰 مبلغ: " . number_format($payment['price']) . " تومان\n🆔 شناسه تراکنش: {$payment['id_order']}";
    sendTelegramMessage($payment['id_user'], $message);
    
    // Send to admin channel (legacy)
    if (!empty($setting['Channel_Report'])) {
        $topicid = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $topicid,
            'text' => "✅ پرداخت تایید شد\n\nمبلغ: " . number_format($payment['price']) . " تومان\nکاربر: {$payment['id_user']}\nادمین: $admin_note",
            'parse_mode' => "HTML"
        ]);
    }
    // New: category notification and log
    sendNotification('payments', "✅ تایید پرداخت | مبلغ: " . number_format($payment['price']) . " | کاربر: {$payment['id_user']}");
    
    return true;
}

/**
 * Reject payment
 */
function rejectPayment($payment_id, $reason) {
    $payment = getPayment($payment_id);
    if (!$payment || $payment['payment_Status'] !== 'pending') {
        return false;
    }
    
    update("Payment_report", "payment_Status", "rejected", "id", $payment_id);
    update("Payment_report", "dec_not_confirmed", $reason, "id", $payment_id);
    
    // Send notification to user
    $message = "❌ پرداخت شما رد شد\n\n💰 مبلغ: " . number_format($payment['price']) . " تومان\n📝 دلیل: $reason";
    sendTelegramMessage($payment['id_user'], $message);
    // Category channel notify and log
    sendNotification('payments', "❌ رد پرداخت | مبلغ: " . number_format($payment['price']) . " | کاربر: {$payment['id_user']} | دلیل: $reason");
    
    return true;
}
?>
