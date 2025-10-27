# Web Panel - Bot Complete Integration Guide

## ✅ What Has Been Completed

### 1. **Bot Core Integration File** (`webpanel/includes/bot_core.php`)
Created a bridge file that:
- Includes all bot's core functions (config.php, function.php, botapi.php, panels.php)
- Initializes ManagePanel class for VPN operations
- Provides helper functions for web panel to use bot's database

### 2. **Key Features Implemented in bot_core.php**:
- `getBotSettings()` - Get all bot settings from `setting` table
- `getPaymentSettings()` - Get payment gateway settings from `PaySetting` table  
- `getTextTemplates()` - Get bot text templates from `textbot` table
- `sendTelegramMessage()` - Send messages via bot's Telegram API
- `getUserInfo()` - Get complete user data with invoices and payments
- `getAllPanels()` - Get all VPN panels from `marzban_panel` table
- `getAllProducts()` - Get all products from `product` table
- `getAllDiscounts()` - Get discount codes from `DiscountSell` table
- `createService()` - Create VPN service via ManagePanel class
- `extendService()` - Extend service duration
- `deleteService()` - Delete service from panel and database
- `approvePayment()` - Approve payment, add balance, send notifications
- `rejectPayment()` - Reject payment with reason, notify user
- `getStatistics()` - Get dashboard statistics from real data

## 📊 Your Bot's Database Structure

### Core Tables:
1. **user** - Bot users (id, username, Balance, agent, step, etc.)
2. **invoice** - Services/subscriptions (id_invoice, id_user, username, Service_location, name_product, Volume, Service_time, Status)
3. **Payment_report** - Payment transactions (id, id_user, price, Payment_Method, payment_Status, time)
4. **marzban_panel** - VPN panels (name_panel, url_panel, type, status, inboundid, etc.)
5. **product** - VPN products (code_product, name_product, price_product, Volume_constraint, Service_time, Location)
6. **DiscountSell** - Discount codes (codeDiscount, price, limitDiscount, usedDiscount, type)
7. **admin** - Admin users (id_admin, username, password, rule)
8. **setting** - Bot settings (Bot_Status, Channel_Report, affiliatesstatus, etc.)
9. **PaySetting** - Payment gateway settings (NamePay, ValuePay)
10. **textbot** - Text templates (id_text, text)

## 🔧 How To Complete Full Integration

### Step 1: Update Authentication System
Modify `webpanel/includes/auth.php` to use bot's admin table:

```php
<?php
require_once __DIR__ . '/bot_core.php';

class Auth {
    public function login($username, $password) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && $admin['password'] === $password) {
            $_SESSION['admin_id'] = $admin['id_admin'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_rule'] = $admin['rule'];
            return true;
        }
        return false;
    }
    
    public function getCurrentAdmin() {
        if (!$this->isLoggedIn()) return null;
        return select("admin", "*", "id_admin", $_SESSION['admin_id'], "select");
    }
}
?>
```

### Step 2: Update All Web Panel Pages

Replace all pages to use `bot_core.php` functions:

**Example: users.php**
```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

// Get all users using bot's select() function
$users = select("user", "*", null, null, "fetchAll");
$stats = getStatistics();
?>
```

**Example: invoices.php**
```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

// Get all invoices
$invoices = select("invoice", "*", null, null, "fetchAll");

// Get panel info for each invoice
foreach ($invoices as &$invoice) {
    $panel = getPanelByName($invoice['Service_location']);
    $invoice['panel_info'] = $panel;
}
?>
```

**Example: payments.php**
```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

// Get all payments
$payments = select("Payment_report", "*", null, null, "fetchAll");

// Approve payment action
if ($_POST['action'] == 'approve') {
    approvePayment($_POST['payment_id'], $_POST['note']);
}

// Reject payment action
if ($_POST['action'] == 'reject') {
    rejectPayment($_POST['payment_id'], $_POST['reason']);
}
?>
```

### Step 3: Create API Endpoints Using Bot Functions

**webpanel/api/approve_payment.php**
```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bot_core.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$payment_id = $_POST['payment_id'] ?? null;
$note = $_POST['note'] ?? '';

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit;
}

$result = approvePayment($payment_id, $note);

echo json_encode([
    'success' => $result,
    'message' => $result ? 'Payment approved successfully' : 'Failed to approve payment'
]);
?>
```

**webpanel/api/create_service.php**
```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bot_core.php';

$panel_name = $_POST['panel_name'];
$product_code = $_POST['product_code'];
$user_id = $_POST['user_id'];

// Generate username
$username = $user_id . '_' . bin2hex(random_bytes(4));

// Get product details
$product = getProductByCode($product_code);

// Prepare config data
$config_data = [
    'expire' => time() + ($product['Service_time'] * 86400),
    'data_limit' => $product['Volume_constraint'] * 1024 * 1024 * 1024,
    'from_id' => $user_id,
    'username' => $username,
    'type' => 'manual'
];

// Create service via ManagePanel
$result = createService($panel_name, $product_code, $username, $config_data);

echo json_encode($result);
?>
```

### Step 4: Bot Control Integration

**webpanel/bot_control.php** - Already uses bot's config:
```php
<?php
require_once __DIR__ . '/includes/bot_core.php';

// Bot status from bot files
$botStatus = 'running'; // Check via process or webhook

// Get bot info
$bot_info = telegram('getMe');

// Bot stats from real database
$stats = getStatistics();
?>
```

### Step 5: Panel Management Integration

Use `ManagePanel` class from bot:
```php
<?php
require_once __DIR__ . '/includes/bot_core.php';

global $ManagePanel;

// Get all panels
$panels = getAllPanels();

// Test panel connection
$test_result = $ManagePanel->testConnection($panel_name);

// Create user on panel
$result = $ManagePanel->createUser($panel_name, $product_code, $username, $config_data);

// Delete user from panel
$result = $ManagePanel->deleteUser($panel_name, $username);
?>
```

## 🎯 Integration Checklist

- [x] Create bot_core.php bridge file
- [ ] Update auth.php to use bot's admin table
- [ ] Update index.php to use getStatistics()
- [ ] Update users.php to use select("user") 
- [ ] Update invoices.php to use select("invoice")
- [ ] Update payments.php to use Payment_report table
- [ ] Update panels.php to use marzban_panel table
- [ ] Update products.php to use product table
- [ ] Update settings.php to use setting/PaySetting tables
- [ ] Create API endpoints using bot_core functions
- [ ] Test payment approval/rejection flow
- [ ] Test service creation via ManagePanel
- [ ] Test Telegram message sending
- [ ] Verify all database operations use bot's select/update functions

## 🔐 Key Points

1. **Always use bot's functions**: `select()`, `update()`, `telegram()`, etc.
2. **Never create separate database connections** - use existing `$pdo` and `$connect` from config.php
3. **Use ManagePanel for VPN operations** - Don't bypass the bot's panel management
4. **Send notifications via bot** - Use `sendTelegramMessage()` or `telegram()` functions
5. **Respect bot's table structure** - Don't modify tables without updating table.php
6. **Use bot's status tracking** - Invoice Status, payment_Status fields

## 📝 Example: Complete Payment Approval Flow

```php
// 1. Admin clicks approve in web panel
// 2. AJAX calls /webpanel/api/approve_payment.php
// 3. approvePayment() function:
//    - Updates Payment_report.payment_Status = 'completed'
//    - Adds amount to user.Balance
//    - Sends Telegram notification to user via sendmessage()
//    - Sends notification to admin channel via telegram()
// 4. User receives message in Telegram
// 5. Admin sees notification in report channel
```

## 🚀 Next Steps

The web panel is now **fully structured** to integrate with your bot. To complete:

1. Replace existing webpanel pages with bot_core.php integration
2. Test each feature (payment approval, service creation, user management)
3. Verify Telegram notifications work from web panel
4. Ensure all operations use bot's database structure
5. Test ManagePanel VPN operations from web panel

Your bot's logic, database structure, and Telegram integration are now **fully accessible** from the web panel!
