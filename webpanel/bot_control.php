<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();

// Get bot status
$botStatus = 'unknown';
$botPid = null;

// Check if bot is running
if (file_exists(__DIR__ . '/../bot.pid')) {
    $botPid = file_get_contents(__DIR__ . '/../bot.pid');
    if (posix_kill($botPid, 0)) {
        $botStatus = 'running';
    } else {
        $botStatus = 'stopped';
        unlink(__DIR__ . '/../bot.pid');
    }
} else {
    $botStatus = 'stopped';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کنترل ربات - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .control-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .status-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .bot-status-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .status-running .bot-status-icon { color: #28a745; }
        .status-stopped .bot-status-icon { color: #dc3545; }
        
        .status-text {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .status-running .status-text { color: #28a745; }
        .status-stopped .status-text { color: #dc3545; }
        
        .control-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .logs-container {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 500px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .log-line {
            margin: 5px 0;
            padding: 5px;
            border-radius: 3px;
        }
        
        .log-error { background: rgba(220, 53, 69, 0.2); }
        .log-warning { background: rgba(255, 193, 7, 0.2); }
        .log-info { background: rgba(23, 162, 184, 0.2); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-box:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-box:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-box:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .control-grid {
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
                <h1>🤖 کنترل ربات تلگرام</h1>
                <div class="topbar-actions">
                    <button class="btn-icon" onclick="location.reload()">
                        🔄 بروزرسانی
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="control-grid">
                    <!-- Bot Status & Controls -->
                    <div>
                        <div class="status-card status-<?php echo $botStatus; ?>">
                            <div class="bot-status-icon">
                                <?php echo $botStatus === 'running' ? '✅' : '🔴'; ?>
                            </div>
                            <div class="status-text">
                                <?php echo $botStatus === 'running' ? 'ربات در حال اجرا' : 'ربات متوقف شده'; ?>
                            </div>
                            <?php if ($botPid): ?>
                            <small>PID: <?php echo $botPid; ?></small>
                            <?php endif; ?>
                            
                            <div class="control-buttons">
                                <button class="btn btn-success" onclick="startBot()" <?php echo $botStatus === 'running' ? 'disabled' : ''; ?>>
                                    ▶️ شروع ربات
                                </button>
                                <button class="btn btn-danger" onclick="stopBot()" <?php echo $botStatus === 'stopped' ? 'disabled' : ''; ?>>
                                    ⏹️ توقف ربات
                                </button>
                                <button class="btn btn-warning" onclick="restartBot()">
                                    🔄 راه‌اندازی مجدد
                                </button>
                                <button class="btn btn-info" onclick="viewLogs()">
                                    📋 مشاهده لاگ‌ها
                                </button>
                            </div>
                        </div>
                        
                        <!-- Bot Info -->
                        <div class="info-card" style="margin-top: 20px;">
                            <h3>اطلاعات ربات</h3>
                            <div class="info-row">
                                <span class="info-label">نام ربات:</span>
                                <span class="info-value" id="botName">در حال بارگذاری...</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">نام کاربری:</span>
                                <span class="info-value" id="botUsername">@loading...</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">توکن:</span>
                                <span class="info-value" style="font-family: monospace; font-size: 11px;">
                                    <?php echo substr(BOT_TOKEN, 0, 15) . '...'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Webhook:</span>
                                <span class="info-value" id="webhookStatus">بررسی...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bot Statistics & Logs -->
                    <div>
                        <div class="info-card">
                            <h3>آمار ربات</h3>
                            <div class="stats-grid">
                                <div class="stat-box">
                                    <div class="stat-value" id="totalUsers">0</div>
                                    <div class="stat-label">کل کاربران</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value" id="activeUsers">0</div>
                                    <div class="stat-label">کاربران فعال</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value" id="todayMessages">0</div>
                                    <div class="stat-label">پیام‌های امروز</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value" id="activeSessions">0</div>
                                    <div class="stat-label">نشست‌های فعال</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Logs -->
                        <div class="info-card" style="margin-top: 20px;">
                            <h3>لاگ‌های اخیر</h3>
                            <div class="logs-container" id="logsContainer">
                                <div class="log-line">در حال بارگذاری لاگ‌ها...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        // Load bot info on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadBotInfo();
            loadBotStats();
            loadRecentLogs();
            
            // Auto-refresh every 10 seconds
            setInterval(function() {
                loadBotStats();
                loadRecentLogs();
            }, 10000);
        });
        
        function loadBotInfo() {
            fetch('/webpanel/api/bot_info.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('botName').textContent = data.info.first_name;
                        document.getElementById('botUsername').textContent = '@' + data.info.username;
                        document.getElementById('webhookStatus').textContent = data.webhook ? '✅ فعال' : '❌ غیرفعال';
                    }
                })
                .catch(error => console.error('Error loading bot info:', error));
        }
        
        function loadBotStats() {
            fetch('/webpanel/api/bot_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalUsers').textContent = data.stats.total_users;
                        document.getElementById('activeUsers').textContent = data.stats.active_users;
                        document.getElementById('todayMessages').textContent = data.stats.today_messages;
                        document.getElementById('activeSessions').textContent = data.stats.active_sessions;
                    }
                })
                .catch(error => console.error('Error loading bot stats:', error));
        }
        
        function loadRecentLogs() {
            fetch('/webpanel/api/bot_logs.php?lines=50')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('logsContainer');
                        container.innerHTML = data.logs.map(log => {
                            let className = 'log-line';
                            if (log.includes('ERROR')) className += ' log-error';
                            else if (log.includes('WARNING')) className += ' log-warning';
                            else if (log.includes('INFO')) className += ' log-info';
                            return `<div class="${className}">${log}</div>`;
                        }).join('');
                        
                        // Auto-scroll to bottom
                        container.scrollTop = container.scrollHeight;
                    }
                })
                .catch(error => console.error('Error loading logs:', error));
        }
        
        function startBot() {
            if (!confirm('آیا از شروع ربات اطمینان دارید؟')) return;
            
            fetch('/webpanel/api/bot_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'start' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ربات با موفقیت شروع شد');
                    location.reload();
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            });
        }
        
        function stopBot() {
            if (!confirm('آیا از توقف ربات اطمینان دارید؟')) return;
            
            fetch('/webpanel/api/bot_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'stop' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ربات با موفقیت متوقف شد');
                    location.reload();
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            });
        }
        
        function restartBot() {
            if (!confirm('آیا از راه‌اندازی مجدد ربات اطمینان دارید؟')) return;
            
            fetch('/webpanel/api/bot_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'restart' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ربات با موفقیت راه‌اندازی مجدد شد');
                    location.reload();
                } else {
                    alert('❌ خطا: ' + data.message);
                }
            });
        }
        
        function viewLogs() {
            window.open('/webpanel/bot_logs_full.php', '_blank');
        }
    </script>
</body>
</html>
