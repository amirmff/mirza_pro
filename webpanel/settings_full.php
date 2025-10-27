<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات سیستم - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .tab {
            padding: 12px 25px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            color: #777;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .setting-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .setting-group {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .setting-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .setting-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .setting-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-save:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>⚙️ تنظیمات سیستم</h1>
            </div>
            
            <div class="content-area">
                <div class="tabs">
                    <button class="tab active" onclick="showTab('bot')">تنظیمات ربات</button>
                    <button class="tab" onclick="showTab('payment')">درگاه پرداخت</button>
                    <button class="tab" onclick="showTab('general')">تنظیمات عمومی</button>
                    <button class="tab" onclick="showTab('notifications')">اعلان‌ها</button>
                </div>
                
                <!-- Bot Settings Tab -->
                <div id="bot-tab" class="tab-content active">
                    <div class="settings-grid">
                        <div class="setting-card">
                            <h3>🤖 اطلاعات ربات</h3>
                            <div class="setting-group">
                                <label class="setting-label">توکن ربات:</label>
                                <input type="text" class="setting-input" id="bot_token" value="<?php echo htmlspecialchars(BOT_TOKEN); ?>">
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Webhook URL:</label>
                                <input type="text" class="setting-input" id="webhook_url" placeholder="https://yourdomain.com/webhook.php">
                            </div>
                            <button class="btn-save" onclick="saveSettings('bot')">💾 ذخیره</button>
                        </div>
                        
                        <div class="setting-card">
                            <h3>📝 متن‌های ربات</h3>
                            <div class="setting-group">
                                <label class="setting-label">پیام خوش‌آمدگویی:</label>
                                <textarea class="setting-input" id="welcome_message" rows="4">سلام! به ربات ما خوش آمدید 👋</textarea>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">پیام راهنما:</label>
                                <textarea class="setting-input" id="help_message" rows="4">برای مشاهده دستورات /help را ارسال کنید</textarea>
                            </div>
                            <button class="btn-save" onclick="saveSettings('texts')">💾 ذخیره</button>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Settings Tab -->
                <div id="payment-tab" class="tab-content">
                    <div class="settings-grid">
                        <div class="setting-card">
                            <h3>💳 درگاه پرداخت</h3>
                            <div class="setting-group">
                                <label class="setting-label">نوع درگاه:</label>
                                <select class="setting-input" id="payment_gateway">
                                    <option value="zarinpal">زرین‌پال</option>
                                    <option value="idpay">آیدی‌پی</option>
                                    <option value="nextpay">نکست‌پی</option>
                                    <option value="card">کارت به کارت</option>
                                </select>
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">Merchant ID:</label>
                                <input type="text" class="setting-input" id="merchant_id" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">شماره کارت (کارت به کارت):</label>
                                <input type="text" class="setting-input" id="card_number" placeholder="6037-9976-xxxx-xxxx">
                            </div>
                            <button class="btn-save" onclick="saveSettings('payment')">💾 ذخیره</button>
                        </div>
                    </div>
                </div>
                
                <!-- General Settings Tab -->
                <div id="general-tab" class="tab-content">
                    <div class="settings-grid">
                        <div class="setting-card">
                            <h3>🌐 تنظیمات عمومی</h3>
                            <div class="setting-group">
                                <label class="setting-label">نام سایت:</label>
                                <input type="text" class="setting-input" id="site_name" value="Mirza Pro VPN">
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">آدرس وب سایت:</label>
                                <input type="url" class="setting-input" id="site_url" placeholder="https://yourdomain.com">
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">ایمیل پشتیبانی:</label>
                                <input type="email" class="setting-input" id="support_email" placeholder="support@yourdomain.com">
                            </div>
                            <button class="btn-save" onclick="saveSettings('general')">💾 ذخیره</button>
                        </div>
                        
                        <div class="setting-card">
                            <h3>📊 تنظیمات پیشرفته</h3>
                            <div class="setting-group">
                                <label class="setting-label">تعداد روز هشدار انقضا:</label>
                                <input type="number" class="setting-input" id="expiry_warning_days" value="3" min="1">
                            </div>
                            <div class="setting-group">
                                <label class="setting-label">حداکثر سرویس همزمان هر کاربر:</label>
                                <input type="number" class="setting-input" id="max_services_per_user" value="5" min="1">
                            </div>
                            <button class="btn-save" onclick="saveSettings('advanced')">💾 ذخیره</button>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications Settings Tab -->
                <div id="notifications-tab" class="tab-content">
                    <div class="settings-grid">
                        <div class="setting-card">
                            <h3>🔔 اعلان‌ها</h3>
                            <div class="setting-group">
                                <label>
                                    <input type="checkbox" id="notify_new_user" checked> اعلان کاربر جدید
                                </label>
                            </div>
                            <div class="setting-group">
                                <label>
                                    <input type="checkbox" id="notify_new_payment" checked> اعلان پرداخت جدید
                                </label>
                            </div>
                            <div class="setting-group">
                                <label>
                                    <input type="checkbox" id="notify_service_expiry" checked> اعلان انقضای سرویس
                                </label>
                            </div>
                            <button class="btn-save" onclick="saveSettings('notifications')">💾 ذخیره</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function saveSettings(section) {
            const data = {};
            
            // Collect all inputs in current tab
            const activeTab = document.querySelector('.tab-content.active');
            activeTab.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.type === 'checkbox') {
                    data[input.id] = input.checked;
                } else {
                    data[input.id] = input.value;
                }
            });
            
            fetch('/webpanel/api/save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ section: section, data: data })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ تنظیمات با موفقیت ذخیره شد');
                } else {
                    alert('❌ خطا: ' + result.message);
                }
            })
            .catch(error => {
                alert('❌ خطا در ذخیره تنظیمات');
            });
        }
        
        // Load settings on page load
        window.addEventListener('DOMContentLoaded', function() {
            fetch('/webpanel/api/get_settings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Object.keys(data.settings).forEach(key => {
                            const element = document.getElementById(key);
                            if (element) {
                                if (element.type === 'checkbox') {
                                    element.checked = data.settings[key];
                                } else {
                                    element.value = data.settings[key];
                                }
                            }
                        });
                    }
                });
        });
    </script>
</body>
</html>
