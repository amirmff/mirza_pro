<?php
/**
 * Bot Management Page - COMPLETE REWRITE
 * Full bot management system with all features
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config.php';

$auth = new Auth();
$auth->requireLogin();
$currentAdmin = $auth->getCurrentAdmin();
if (!$currentAdmin || ($currentAdmin['rule'] ?? '') !== 'administrator') {
    http_response_code(403);
    exit('Forbidden');
}

$page_title = 'مدیریت ربات';
$active_page = 'bot_management';

// Get bot status
$bot_status = ['running' => false, 'pid' => null, 'uptime' => null, 'memory' => null, 'cpu' => null];
exec("supervisorctl status mirza_bot 2>&1", $output, $return_code);
if ($return_code === 0 && !empty($output[0]) && strpos($output[0], 'RUNNING') !== false) {
    $bot_status['running'] = true;
    preg_match('/pid (\d+)/', $output[0], $matches);
    if (!empty($matches[1])) {
        $bot_status['pid'] = $matches[1];
        exec("ps -p {$matches[1]} -o %mem,%cpu,etimes --no-headers 2>&1", $ps_output);
        if (!empty($ps_output[0])) {
            $parts = preg_split('/\s+/', trim($ps_output[0]));
            $bot_status['memory'] = $parts[0] ?? 0;
            $bot_status['cpu'] = $parts[1] ?? 0;
            $bot_status['uptime'] = isset($parts[2]) ? gmdate("H:i:s", $parts[2]) : null;
        }
    }
}

// Get webhook info
$webhook_info = [];
if (!empty($APIKEY) && $APIKEY !== '{API_KEY}')) {
    $ch = curl_init("https://api.telegram.org/bot{$APIKEY}/getWebhookInfo");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if ($data['ok'] ?? false) {
        $webhook_info = $data['result'];
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php 
        $admin = $currentAdmin;
        include __DIR__ . '/includes/sidebar.php'; 
        ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1><?php echo $page_title; ?></h1>
            </div>
            
            <div class="container">
                <!-- Status Cards -->
                <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="card">
                        <h3>وضعیت ربات</h3>
                        <div style="text-align: center; padding: 20px;">
                            <?php if ($bot_status['running']): ?>
                                <div style="font-size: 48px; color: #27ae60; margin-bottom: 10px;">✅</div>
                                <div class="badge badge-success" style="font-size: 16px; padding: 8px 16px;">فعال</div>
                                <?php if ($bot_status['pid']): ?>
                                    <p style="margin-top: 10px; color: #666; font-size: 12px;">PID: <?php echo $bot_status['pid']; ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="font-size: 48px; color: #e74c3c; margin-bottom: 10px;">❌</div>
                                <div class="badge badge-danger" style="font-size: 16px; padding: 8px 16px;">غیرفعال</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($bot_status['running']): ?>
                    <div class="card">
                        <h3>اطلاعات پردازش</h3>
                        <table style="width: 100%;">
                            <tr><td><strong>زمان فعالیت:</strong></td><td><?php echo $bot_status['uptime'] ?? 'N/A'; ?></td></tr>
                            <tr><td><strong>مصرف حافظه:</strong></td><td><?php echo number_format($bot_status['memory'], 1); ?>%</td></tr>
                            <tr><td><strong>مصرف CPU:</strong></td><td><?php echo number_format($bot_status['cpu'], 1); ?>%</td></tr>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <h3>وضعیت Webhook</h3>
                        <?php if (!empty($webhook_info)): ?>
                            <table style="width: 100%; font-size: 12px;">
                                <tr><td><strong>URL:</strong></td><td style="word-break: break-all;"><?php echo htmlspecialchars($webhook_info['url'] ?? 'Not Set'); ?></td></tr>
                                <tr><td><strong>آخرین خطا:</strong></td><td><?php echo !empty($webhook_info['last_error_message']) ? '<span style="color: #e74c3c;">' . htmlspecialchars($webhook_info['last_error_message']) . '</span>' : '<span style="color: #27ae60;">بدون خطا</span>'; ?></td></tr>
                                <tr><td><strong>پیام‌های در انتظار:</strong></td><td><?php echo $webhook_info['pending_update_count'] ?? 0; ?></td></tr>
                            </table>
                        <?php else: ?>
                            <p style="text-align: center; color: #999;">اطلاعات وب‌هوک در دسترس نیست</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bot Configuration -->
                <div class="card" style="margin-bottom: 20px;">
                    <h3>⚙️ تنظیمات ربات</h3>
                    <div style="display:flex;flex-direction:column;gap:15px;">
                        <div>
                            <label style="display:block;margin-bottom:5px;font-weight:500;">توکن ربات تلگرام *</label>
                            <input id="bot_token" type="text" class="form-control" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" value="<?php echo htmlspecialchars($APIKEY ?? ''); ?>">
                            <small style="color:#666;font-size:12px;">از @BotFather دریافت کنید</small>
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:5px;font-weight:500;">آیدی عددی ادمین *</label>
                            <input id="admin_id" type="text" class="form-control" placeholder="123456789" value="<?php echo htmlspecialchars($adminnumber ?? ''); ?>">
                            <small style="color:#666;font-size:12px;">از @userinfobot دریافت کنید</small>
                        </div>
                        <button class="btn btn-primary" onclick="updateBotConfig()" style="width: 100%;">💾 ذخیره تنظیمات و راه‌اندازی مجدد ربات</button>
                        <div id="config-update-result"></div>
                    </div>
                </div>

                <!-- Bot Control -->
                <div class="card" style="margin-bottom: 20px;">
                    <h3>🎮 کنترل ربات</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php if ($bot_status['running']): ?>
                            <button onclick="controlBot('stop')" class="btn btn-danger">⏹️ توقف ربات</button>
                            <button onclick="controlBot('restart')" class="btn btn-warning">🔄 راه‌اندازی مجدد</button>
                        <?php else: ?>
                            <button onclick="controlBot('start')" class="btn btn-success">▶️ شروع ربات</button>
                        <?php endif; ?>
                        <button onclick="updateWebhook()" class="btn btn-primary">🔗 تنظیم Webhook</button>
                        <button onclick="refreshWebhook()" class="btn btn-secondary">ℹ️ وضعیت Webhook</button>
                        <button onclick="showLogs()" class="btn btn-secondary">📋 نمایش لاگ‌ها</button>
                        <button onclick="clearLogs()" class="btn btn-secondary">🗑️ پاک کردن لاگ‌ها</button>
                    </div>
                </div>

                <!-- Domain & SSL -->
                <div class="card" style="margin-bottom: 20px;">
                    <h3>🌐 دامنه و SSL</h3>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                        <input id="domain" class="form-control" style="min-width:260px" placeholder="example.com" value="<?php echo htmlspecialchars($domainhosts ?? ''); ?>">
                        <input id="ssl_email" class="form-control" style="min-width:260px" placeholder="admin@example.com">
                        <button class="btn btn-primary" onclick="applyDomain()">✅ اعمال دامنه + SSL خودکار</button>
                        <button class="btn btn-secondary" onclick="renewSSL()">♻️ تمدید SSL</button>
                    </div>
                    <div id="ssl-info" style="margin-top:10px;color:#666;font-size:13px"></div>
                </div>
                
                <!-- Logs Viewer -->
                <div class="card" id="logs-section" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3>📋 لاگ‌های ربات</h3>
                        <button onclick="refreshLogs()" class="btn btn-sm btn-secondary">🔄 بروزرسانی</button>
                    </div>
                    <div id="logs-content" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
                        در حال بارگذاری...
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
    const csrfToken = '<?php echo $auth->getCsrfToken(); ?>';
    
    function showLoading() {
        if (!document.getElementById('loading-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;';
            overlay.innerHTML = '<div style="background:#1a1a1a;padding:30px;border-radius:10px;text-align:center;color:#fff;"><div style="font-size:24px;margin-bottom:10px;">⏳</div><div>در حال پردازش...</div></div>';
            document.body.appendChild(overlay);
        }
    }
    
    function hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.remove();
    }
    
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.style.cssText = `position:fixed;top:20px;right:20px;padding:15px 20px;border-radius:8px;z-index:10000;min-width:250px;box-shadow:0 4px 12px rgba(0,0,0,0.3);background:${type==='success'?'#27ae60':'#e74c3c'};color:white;`;
        alertDiv.textContent = message;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 5000);
    }
    
    function controlBot(action) {
        if (!confirm(`آیا از ${action==='stop'?'توقف':action==='start'?'شروع':'راه‌اندازی مجدد'} ربات اطمینان دارید؟`)) return;
        showLoading();
        fetch('/webpanel/includes/bot_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=${action}&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            showAlert(data.success ? 'success' : 'error', data.message);
            if (data.success) setTimeout(() => location.reload(), 1500);
        })
        .catch(e => {
            hideLoading();
            showAlert('error', 'خطا در برقراری ارتباط');
        });
    }
    
    function updateBotConfig() {
        const bot_token = document.getElementById('bot_token').value.trim();
        const admin_id = document.getElementById('admin_id').value.trim();
        const domain = document.getElementById('domain')?.value.trim() || '';
        
        if (!bot_token || !admin_id) {
            showAlert('error', 'لطفا توکن ربات و آیدی ادمین را وارد کنید');
            return;
        }
        
        if (!confirm('آیا از تغییر تنظیمات ربات و راه‌اندازی مجدد آن اطمینان دارید؟')) return;
        
        showLoading();
        const resultDiv = document.getElementById('config-update-result');
        resultDiv.innerHTML = '<div style="color:#666;padding:10px;">در حال بروزرسانی...</div>';
        
        fetch('/webpanel/api/bot_config.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update_config&bot_token=${encodeURIComponent(bot_token)}&admin_id=${encodeURIComponent(admin_id)}&domain=${encodeURIComponent(domain)}&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('success', data.message + (data.bot_restarted ? ' - ربات راه‌اندازی مجدد شد' : ''));
                resultDiv.innerHTML = '<div style="color:#27ae60;padding:10px;background:#d4edda;border-radius:5px;">✓ تنظیمات با موفقیت بروزرسانی شد</div>';
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert('error', data.message);
                resultDiv.innerHTML = '<div style="color:#e74c3c;padding:10px;background:#f8d7da;border-radius:5px;">✗ ' + data.message + '</div>';
            }
        })
        .catch(e => {
            hideLoading();
            showAlert('error', 'خطا در برقراری ارتباط');
            resultDiv.innerHTML = '<div style="color:#e74c3c;padding:10px;background:#f8d7da;border-radius:5px;">✗ خطا در ارتباط</div>';
        });
    }
    
    function updateWebhook() {
        showLoading();
        fetch('/webpanel/includes/bot_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=set_webhook&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            showAlert(data.success ? 'success' : 'error', data.message);
        })
        .catch(e => {
            hideLoading();
            showAlert('error', 'خطا در برقراری ارتباط');
        });
    }
    
    function refreshWebhook() {
        showLoading();
        fetch('/webpanel/includes/bot_control.php?action=get_webhook')
        .then(r => r.json())
        .then(data => {
            hideLoading();
            if (data.ok) {
                alert('Webhook URL: ' + (data.result.url || 'Not Set') + '\nPending Updates: ' + (data.result.pending_update_count || 0));
            } else {
                showAlert('error', 'خطا در دریافت اطلاعات');
            }
        })
        .catch(e => {
            hideLoading();
            showAlert('error', 'خطا در برقراری ارتباط');
        });
    }
    
    function applyDomain() {
        const domain = document.getElementById('domain').value.trim();
        const email = document.getElementById('ssl_email').value.trim();
        
        if (!domain) {
            showAlert('error', 'لطفا دامنه را وارد کنید');
            return;
        }
        
        showLoading();
        const sslInfo = document.getElementById('ssl-info');
        sslInfo.innerHTML = 'در حال اعمال دامنه و نصب SSL...';
        
        fetch('/webpanel/includes/bot_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=set_domain&domain=${encodeURIComponent(domain)}&email=${encodeURIComponent(email)}&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('success', data.message + (data.ssl_success ? ' - SSL نصب شد' : ''));
                sslInfo.innerHTML = data.ssl || '';
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert('error', data.message);
                sslInfo.innerHTML = data.ssl || '';
            }
        })
        .catch(e => {
            hideLoading();
            showAlert('error', 'خطا در برقراری ارتباط');
        });
    }
    
    function renewSSL() {
        if (!confirm('آیا از تمدید SSL اطمینان دارید؟')) return;
        showLoading();
        fetch('/webpanel/includes/bot_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=renew_ssl&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            showAlert(data.success ? 'success' : 'error', data.message);
        })
        .catch(e => {
            hideLoading();
            showAlert('error', 'خطا در برقراری ارتباط');
        });
    }
    
    function showLogs() {
        const section = document.getElementById('logs-section');
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
        if (section.style.display === 'block') refreshLogs();
    }
    
    function refreshLogs() {
        const logsContent = document.getElementById('logs-content');
        logsContent.textContent = 'در حال بارگذاری...';
        fetch('/webpanel/includes/bot_control.php?action=logs')
        .then(r => r.json())
        .then(data => {
            logsContent.textContent = data.logs || 'لاگی یافت نشد';
        })
        .catch(e => {
            logsContent.textContent = 'خطا در دریافت لاگ‌ها';
        });
    }
    
    function clearLogs() {
        if (!confirm('آیا از پاک کردن لاگ‌ها اطمینان دارید؟')) return;
        showLoading();
        fetch('/webpanel/includes/bot_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=clear_logs&csrf_token=${csrfToken}`
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            showAlert(data.success ? 'success' : 'error', data.message);
            if (data.success) refreshLogs();
        })
        .catch(e => {
            hideLoading();
            showAlert('error', 'خطا در برقراری ارتباط');
        });
    }
    </script>
</body>
</html>
