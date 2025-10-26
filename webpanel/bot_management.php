<?php
/**
 * Bot Management Page
 * Control bot process, view logs, manage webhook
 */

require_once 'includes/auth.php';
require_auth();
check_permission('administrator');

$page_title = 'مدیریت ربات';
$active_page = 'bot_management';

include 'includes/header.php';

// Get bot status
$bot_status = [
    'running' => false,
    'pid' => null,
    'uptime' => null,
    'memory' => null,
    'cpu' => null
];

// Check if bot process is running
exec("supervisorctl status mirza_bot 2>&1", $output, $return_code);
if ($return_code === 0 && !empty($output[0])) {
    if (strpos($output[0], 'RUNNING') !== false) {
        $bot_status['running'] = true;
        preg_match('/pid (\d+)/', $output[0], $matches);
        if (!empty($matches[1])) {
            $bot_status['pid'] = $matches[1];
            
            // Get process details
            exec("ps -p {$matches[1]} -o %mem,%cpu,etimes --no-headers", $ps_output);
            if (!empty($ps_output[0])) {
                $parts = preg_split('/\s+/', trim($ps_output[0]));
                $bot_status['memory'] = $parts[0] ?? 0;
                $bot_status['cpu'] = $parts[1] ?? 0;
                $bot_status['uptime'] = isset($parts[2]) ? gmdate("H:i:s", $parts[2]) : null;
            }
        }
    }
}

// Get webhook info
$webhook_info = [];
if (isset($APIKEY)) {
    $ch = curl_init("https://api.telegram.org/bot{$APIKEY}/getWebhookInfo");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data['ok'] ?? false) {
        $webhook_info = $data['result'];
    }
}
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo $page_title; ?></h1>
    </div>
    
    <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <!-- Bot Status Card -->
        <div class="card">
            <h3>وضعیت ربات</h3>
            <div style="text-align: center; padding: 20px;">
                <?php if ($bot_status['running']): ?>
                    <div style="font-size: 48px; color: #27ae60; margin-bottom: 10px;">✅</div>
                    <div class="badge badge-success" style="font-size: 16px; padding: 8px 16px;">فعال</div>
                    <?php if ($bot_status['pid']): ?>
                        <p style="margin-top: 10px; color: #666;">PID: <?php echo $bot_status['pid']; ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="font-size: 48px; color: #e74c3c; margin-bottom: 10px;">❌</div>
                    <div class="badge badge-danger" style="font-size: 16px; padding: 8px 16px;">غیرفعال</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Process Info -->
        <?php if ($bot_status['running']): ?>
        <div class="card">
            <h3>اطلاعات پردازش</h3>
            <table style="width: 100%;">
                <tr>
                    <td><strong>زمان فعالیت:</strong></td>
                    <td><?php echo $bot_status['uptime'] ?? 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><strong>مصرف حافظه:</strong></td>
                    <td><?php echo number_format($bot_status['memory'], 1); ?>%</td>
                </tr>
                <tr>
                    <td><strong>مصرف CPU:</strong></td>
                    <td><?php echo number_format($bot_status['cpu'], 1); ?>%</td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Webhook Status -->
        <div class="card">
            <h3>وضعیت Webhook</h3>
            <?php if (!empty($webhook_info)): ?>
                <table style="width: 100%;">
                    <tr>
                        <td><strong>URL:</strong></td>
                        <td style="word-break: break-all; font-size: 12px;">
                            <?php echo htmlspecialchars($webhook_info['url'] ?? 'Not Set'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>آخرین خطا:</strong></td>
                        <td><?php echo !empty($webhook_info['last_error_message']) ? '<span style="color: #e74c3c;">' . htmlspecialchars($webhook_info['last_error_message']) . '</span>' : '<span style="color: #27ae60;">بدون خطا</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>پیام‌های در انتظار:</strong></td>
                        <td><?php echo $webhook_info['pending_update_count'] ?? 0; ?></td>
                    </tr>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999;">اطلاعات وب‌هوک در دسترس نیست</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Control Buttons -->
    <div class="card">
        <h3>کنترل ربات</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($bot_status['running']): ?>
                <button onclick="controlBot('stop')" class="btn btn-danger">
                    ⏹️ توقف ربات
                </button>
                <button onclick="controlBot('restart')" class="btn btn-warning">
                    🔄 راه‌اندازی مجدد
                </button>
            <?php else: ?>
                <button onclick="controlBot('start')" class="btn btn-success">
                    ▶️ شروع ربات
                </button>
            <?php endif; ?>
            
            <button onclick="updateWebhook()" class="btn btn-primary">
                🔗 تنظیم مجدد Webhook
            </button>
            
            <button onclick="showLogs()" class="btn btn-secondary">
                📋 نمایش لاگ‌ها
            </button>
            
            <button onclick="clearLogs()" class="btn btn-secondary">
                🗑️ پاک کردن لاگ‌ها
            </button>
        </div>
    </div>
    
    <!-- Logs Viewer -->
    <div class="card" id="logs-section" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3>لاگ‌های ربات</h3>
            <button onclick="refreshLogs()" class="btn btn-sm btn-secondary">🔄 بروزرسانی</button>
        </div>
        <div id="logs-content" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
            در حال بارگذاری...
        </div>
    </div>
</div>

<script>
function controlBot(action) {
    if (!confirm(`آیا از ${action === 'stop' ? 'توقف' : action === 'start' ? 'شروع' : 'راه‌اندازی مجدد'} ربات اطمینان دارید؟`)) {
        return;
    }
    
    showLoading();
    
    fetch('/webpanel/includes/bot_control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('error', 'خطا در برقراری ارتباط با سرور');
    });
}

function updateWebhook() {
    showLoading();
    
    fetch('/webpanel/includes/bot_control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=webhook&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('error', 'خطا در برقراری ارتباط با سرور');
    });
}

function showLogs() {
    document.getElementById('logs-section').style.display = 'block';
    refreshLogs();
}

function refreshLogs() {
    const logsContent = document.getElementById('logs-content');
    logsContent.textContent = 'در حال بارگذاری...';
    
    fetch('/webpanel/includes/bot_control.php?action=logs')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            logsContent.textContent = data.logs || 'لاگی یافت نشد';
            logsContent.scrollTop = logsContent.scrollHeight;
        } else {
            logsContent.textContent = 'خطا در دریافت لاگ‌ها: ' + data.message;
        }
    })
    .catch(error => {
        logsContent.textContent = 'خطا در برقراری ارتباط با سرور';
    });
}

function clearLogs() {
    if (!confirm('آیا از پاک کردن لاگ‌ها اطمینان دارید؟')) {
        return;
    }
    
    showLoading();
    
    fetch('/webpanel/includes/bot_control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=clear_logs&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('success', data.message);
            document.getElementById('logs-content').textContent = 'لاگ‌ها پاک شدند';
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('error', 'خطا در برقراری ارتباط با سرور');
    });
}

function showLoading() {
    if (!document.getElementById('loading-overlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
        overlay.innerHTML = '<div style="background:white;padding:30px;border-radius:10px;text-align:center;"><div style="font-size:24px;margin-bottom:10px;">⏳</div><div>در حال پردازش...</div></div>';
        document.body.appendChild(overlay);
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.style.cssText = `position:fixed;top:20px;right:20px;padding:15px 20px;border-radius:8px;z-index:10000;min-width:250px;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:slideIn 0.3s;`;
    alertDiv.style.background = type === 'success' ? '#27ae60' : '#e74c3c';
    alertDiv.style.color = 'white';
    alertDiv.textContent = message;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}
</script>

<style>
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
}

.badge-success {
    background: #27ae60;
    color: white;
}

.badge-danger {
    background: #e74c3c;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<?php include 'includes/footer.php'; ?>
