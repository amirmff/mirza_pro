<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();

require_once __DIR__ . '/../config.php';

// Get bot settings
$settings_stmt = $pdo->query("SELECT * FROM setting LIMIT 1");
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تنظیمات - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>تنظیمات سیستم</h1>
            </div>
            
            <div class="content-area">
                <div class="section">
                    <h2>تنظیمات ربات تلگرام</h2>
                    <div class="settings-grid">
                        <div class="setting-item">
                            <label>توکن ربات:</label>
                            <input type="text" value="<?php echo htmlspecialchars($APIKEY ?? 'N/A'); ?>" readonly class="form-control">
                        </div>
                        <div class="setting-item">
                            <label>شناسه ادمین:</label>
                            <input type="text" value="<?php echo htmlspecialchars($adminnumber ?? 'N/A'); ?>" readonly class="form-control">
                        </div>
                        <div class="setting-item">
                            <label>دامنه:</label>
                            <input type="text" value="<?php echo htmlspecialchars($domainhosts ?? 'N/A'); ?>" readonly class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>تنظیمات عمومی</h2>
                    <div class="settings-grid">
                        <div class="setting-item">
                            <label>حداکثر تست رایگان:</label>
                            <input type="number" value="<?php echo htmlspecialchars($settings['limit_usertest'] ?? '0'); ?>" class="form-control">
                        </div>
                        <div class="setting-item">
                            <label>وضعیت ربات:</label>
                            <select class="form-control">
                                <option value="1">فعال</option>
                                <option value="0">غیرفعال</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>درگاه‌های پرداخت</h2>
                    <div class="payment-gateways">
                        <div class="gateway-card">
                            <h3>💳 کارت به کارت</h3>
                            <p>فعال</p>
                        </div>
                        <div class="gateway-card">
                            <h3>🌐 درگاه آنلاین</h3>
                            <p>فعال</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
