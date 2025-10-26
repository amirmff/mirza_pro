<?php
/**
 * System Management Page
 * SSL setup, backups, system monitoring, and server configuration
 */

require_once 'includes/auth.php';
require_auth();
check_permission('administrator');

$page_title = 'مدیریت سیستم';
$active_page = 'system';

include 'includes/header.php';

// Get system information
$sys_info = [
    'os' => php_uname('s') . ' ' . php_uname('r'),
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'load_average' => sys_getloadavg(),
    'disk_free' => disk_free_space('/'),
    'disk_total' => disk_total_space('/'),
];

// Get memory info
$free_output = shell_exec('free -m');
preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $free_output, $mem_matches);
$sys_info['mem_total'] = isset($mem_matches[1]) ? $mem_matches[1] : 0;
$sys_info['mem_used'] = isset($mem_matches[2]) ? $mem_matches[2] : 0;
$sys_info['mem_free'] = isset($mem_matches[3]) ? $mem_matches[3] : 0;

// Get SSL certificate info
$ssl_info = null;
if (!empty($domainhosts)) {
    exec("echo | openssl s_client -servername {$domainhosts} -connect {$domainhosts}:443 2>/dev/null | openssl x509 -noout -dates 2>/dev/null", $ssl_output);
    if (!empty($ssl_output)) {
        $ssl_info = [
            'installed' => true,
            'details' => implode("\n", $ssl_output)
        ];
    }
}
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo $page_title; ?></h1>
    </div>
    
    <!-- System Status Cards -->
    <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card">
            <h3>💾 دیسک</h3>
            <div style="text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #3498db;">
                    <?php echo round($sys_info['disk_free'] / 1024 / 1024 / 1024, 1); ?> GB
                </div>
                <div style="color: #666;">از <?php echo round($sys_info['disk_total'] / 1024 / 1024 / 1024, 1); ?> GB آزاد</div>
                <div style="width: 100%; background: #ecf0f1; border-radius: 10px; height: 10px; margin-top: 10px;">
                    <div style="width: <?php echo round(($sys_info['disk_total'] - $sys_info['disk_free']) / $sys_info['disk_total'] * 100); ?>%; background: #3498db; border-radius: 10px; height: 10px;"></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>🧠 رم</h3>
            <div style="text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #9b59b6;">
                    <?php echo $sys_info['mem_used']; ?> MB
                </div>
                <div style="color: #666;">از <?php echo $sys_info['mem_total']; ?> MB</div>
                <div style="width: 100%; background: #ecf0f1; border-radius: 10px; height: 10px; margin-top: 10px;">
                    <div style="width: <?php echo $sys_info['mem_total'] > 0 ? round($sys_info['mem_used'] / $sys_info['mem_total'] * 100) : 0; ?>%; background: #9b59b6; border-radius: 10px; height: 10px;"></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>⚡ بار سیستم</h3>
            <div style="text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #e67e22;">
                    <?php echo round($sys_info['load_average'][0], 2); ?>
                </div>
                <div style="color: #666;">میانگین یک دقیقه</div>
                <div style="font-size: 12px; color: #999; margin-top: 5px;">
                    5 min: <?php echo round($sys_info['load_average'][1], 2); ?> | 
                    15 min: <?php echo round($sys_info['load_average'][2], 2); ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>🐘 PHP</h3>
            <div style="text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #27ae60;">
                    <?php echo $sys_info['php_version']; ?>
                </div>
                <div style="color: #666;"><?php echo $sys_info['server_software']; ?></div>
            </div>
        </div>
    </div>
    
    <!-- SSL Certificate Management -->
    <div class="card">
        <h3>🔒 مدیریت SSL</h3>
        <?php if (!empty($domainhosts)): ?>
            <div style="margin-bottom: 15px;">
                <strong>دامنه:</strong> <?php echo htmlspecialchars($domainhosts); ?>
            </div>
            
            <?php if ($ssl_info): ?>
                <div class="alert alert-success">
                    ✅ گواهی SSL نصب شده است
                </div>
                <pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px;"><?php echo htmlspecialchars($ssl_info['details']); ?></pre>
                <button onclick="renewSSL()" class="btn btn-warning">🔄 تمدید گواهی</button>
            <?php else: ?>
                <div class="alert alert-warning">
                    ⚠️ گواهی SSL نصب نشده است
                </div>
                <button onclick="installSSL()" class="btn btn-success">📥 نصب SSL با Let's Encrypt</button>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                ℹ️ برای نصب SSL ابتدا باید دامنه را در تنظیمات مشخص کنید
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Backup Management -->
    <div class="card">
        <h3>💾 پشتیبان‌گیری</h3>
        
        <div style="margin-bottom: 20px;">
            <h4>ایجاد پشتیبان جدید</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="createBackup('database')" class="btn btn-primary">💿 پایگاه داده</button>
                <button onclick="createBackup('files')" class="btn btn-primary">📁 فایل‌ها</button>
                <button onclick="createBackup('full')" class="btn btn-success">📦 کامل (دیتابیس + فایل‌ها)</button>
            </div>
        </div>
        
        <div>
            <h4>پشتیبان‌های موجود</h4>
            <div id="backups-list">
                <p style="color: #999; text-align: center;">در حال بارگذاری...</p>
            </div>
        </div>
    </div>
    
    <!-- Cron Jobs -->
    <div class="card">
        <h3>⏰ وظایف زمان‌بندی شده (Cron Jobs)</h3>
        
        <div style="margin-bottom: 15px;">
            <button onclick="addCronJob()" class="btn btn-primary">➕ افزودن وظیفه جدید</button>
            <button onclick="loadCronJobs()" class="btn btn-secondary">🔄 بروزرسانی</button>
        </div>
        
        <div id="cron-jobs-list">
            <p style="color: #999; text-align: center;">در حال بارگذاری...</p>
        </div>
    </div>
    
    <!-- System Information -->
    <div class="card">
        <h3>ℹ️ اطلاعات سیستم</h3>
        <table class="table">
            <tr>
                <td><strong>سیستم عامل</strong></td>
                <td><?php echo htmlspecialchars($sys_info['os']); ?></td>
            </tr>
            <tr>
                <td><strong>نسخه PHP</strong></td>
                <td><?php echo htmlspecialchars($sys_info['php_version']); ?></td>
            </tr>
            <tr>
                <td><strong>وب سرور</strong></td>
                <td><?php echo htmlspecialchars($sys_info['server_software']); ?></td>
            </tr>
            <tr>
                <td><strong>آدرس IP سرور</strong></td>
                <td><?php echo $_SERVER['SERVER_ADDR'] ?? 'N/A'; ?></td>
            </tr>
        </table>
        
        <div style="margin-top: 20px;">
            <button onclick="downloadPHPInfo()" class="btn btn-secondary">📄 دانلود اطلاعات کامل PHP</button>
            <button onclick="clearCache()" class="btn btn-warning">🗑️ پاک کردن کش</button>
            <button onclick="restartServices()" class="btn btn-danger">🔄 راه‌اندازی مجدد سرویس‌ها</button>
        </div>
    </div>
</div>

<script>
// Load backups list on page load
window.addEventListener('DOMContentLoaded', function() {
    loadBackups();
    loadCronJobs();
});

function installSSL() {
    if (!confirm('آیا از نصب گواهی SSL با Let\'s Encrypt اطمینان دارید؟\nاین عملیات ممکن است چند دقیقه طول بکشد.')) {
        return;
    }
    
    showLoading('در حال نصب SSL...');
    
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=install_ssl&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('error', 'خطا در برقراری ارتباط');
    });
}

function renewSSL() {
    if (!confirm('آیا از تمدید گواهی SSL اطمینان دارید؟')) {
        return;
    }
    
    showLoading('در حال تمدید SSL...');
    
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=renew_ssl&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.success ? 'success' : 'error', data.message);
        if (data.success) setTimeout(() => location.reload(), 2000);
    })
    .catch(error => {
        hideLoading();
        showAlert('error', 'خطا در برقراری ارتباط');
    });
}

function createBackup(type) {
    if (!confirm(`آیا از ایجاد پشتیبان ${type === 'database' ? 'پایگاه داده' : type === 'files' ? 'فایل‌ها' : 'کامل'} اطمینان دارید؟`)) {
        return;
    }
    
    showLoading('در حال ایجاد پشتیبان...');
    
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=create_backup&type=${type}&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.success ? 'success' : 'error', data.message);
        if (data.success) loadBackups();
    })
    .catch(error => {
        hideLoading();
        showAlert('error', 'خطا در برقراری ارتباط');
    });
}

function loadBackups() {
    fetch('/webpanel/includes/system_control.php?action=list_backups')
    .then(response => response.json())
    .then(data => {
        const listDiv = document.getElementById('backups-list');
        if (data.success && data.backups.length > 0) {
            let html = '<table class="table"><thead><tr><th>نام فایل</th><th>نوع</th><th>حجم</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody>';
            data.backups.forEach(backup => {
                html += `<tr>
                    <td>${backup.name}</td>
                    <td><span class="badge">${backup.type}</span></td>
                    <td>${backup.size}</td>
                    <td>${backup.date}</td>
                    <td>
                        <button onclick="downloadBackup('${backup.name}')" class="btn btn-sm btn-primary">دانلود</button>
                        <button onclick="deleteBackup('${backup.name}')" class="btn btn-sm btn-danger">حذف</button>
                    </td>
                </tr>`;
            });
            html += '</tbody></table>';
            listDiv.innerHTML = html;
        } else {
            listDiv.innerHTML = '<p style="color: #999; text-align: center;">پشتیبانی یافت نشد</p>';
        }
    })
    .catch(() => {
        document.getElementById('backups-list').innerHTML = '<p style="color: #e74c3c;">خطا در بارگذاری</p>';
    });
}

function downloadBackup(filename) {
    window.location.href = `/webpanel/includes/system_control.php?action=download_backup&file=${encodeURIComponent(filename)}`;
}

function deleteBackup(filename) {
    if (!confirm(`آیا از حذف پشتیبان "${filename}" اطمینان دارید؟`)) {
        return;
    }
    
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_backup&file=${encodeURIComponent(filename)}&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.success ? 'success' : 'error', data.message);
        if (data.success) loadBackups();
    });
}

function loadCronJobs() {
    fetch('/webpanel/includes/system_control.php?action=list_crons')
    .then(response => response.json())
    .then(data => {
        const listDiv = document.getElementById('cron-jobs-list');
        if (data.success && data.crons.length > 0) {
            let html = '<table class="table"><thead><tr><th>زمان‌بندی</th><th>دستور</th><th>عملیات</th></tr></thead><tbody>';
            data.crons.forEach((cron, index) => {
                html += `<tr>
                    <td><code>${cron.schedule}</code></td>
                    <td><code style="font-size: 11px;">${cron.command}</code></td>
                    <td><button onclick="deleteCron(${index})" class="btn btn-sm btn-danger">حذف</button></td>
                </tr>`;
            });
            html += '</tbody></table>';
            listDiv.innerHTML = html;
        } else {
            listDiv.innerHTML = '<p style="color: #999; text-align: center;">وظیفه‌ای تعریف نشده</p>';
        }
    })
    .catch(() => {
        document.getElementById('cron-jobs-list').innerHTML = '<p style="color: #e74c3c;">خطا در بارگذاری</p>';
    });
}

function addCronJob() {
    const schedule = prompt('زمان‌بندی را وارد کنید (فرمت cron):\nمثال: 0 2 * * * (هر شب ساعت 2)');
    if (!schedule) return;
    
    const command = prompt('دستور را وارد کنید:');
    if (!command) return;
    
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add_cron&schedule=${encodeURIComponent(schedule)}&command=${encodeURIComponent(command)}&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.success ? 'success' : 'error', data.message);
        if (data.success) loadCronJobs();
    });
}

function deleteCron(index) {
    if (!confirm('آیا از حذف این وظیفه اطمینان دارید؟')) return;
    
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_cron&index=${index}&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.success ? 'success' : 'error', data.message);
        if (data.success) loadCronJobs();
    });
}

function clearCache() {
    if (!confirm('آیا از پاک کردن کش اطمینان دارید؟')) return;
    
    showLoading();
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=clear_cache&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.success ? 'success' : 'error', data.message);
    });
}

function restartServices() {
    if (!confirm('آیا از راه‌اندازی مجدد سرویس‌ها اطمینان دارید؟\nاین عملیات موقتاً سرویس‌ها را قطع می‌کند.')) return;
    
    showLoading('در حال راه‌اندازی مجدد...');
    fetch('/webpanel/includes/system_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=restart_services&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        showAlert(data.success ? 'success' : 'error', data.message);
    });
}

function downloadPHPInfo() {
    window.open('/webpanel/includes/system_control.php?action=phpinfo', '_blank');
}

function showLoading(message = 'در حال پردازش...') {
    if (!document.getElementById('loading-overlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;';
        overlay.innerHTML = `<div style="background:white;padding:30px;border-radius:10px;text-align:center;"><div style="font-size:24px;margin-bottom:10px;">⏳</div><div>${message}</div></div>`;
        document.body.appendChild(overlay);
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.remove();
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
.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}
.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
.alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
.alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    background: #3498db;
    color: white;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
</style>

<?php include 'includes/footer.php'; ?>
