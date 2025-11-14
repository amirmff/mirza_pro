<?php
// Sidebar component - included in all pages
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>🚀 Mirza Pro</h2>
        <p>پنل مدیریت</p>
    </div>
    
    <nav class="sidebar-nav">
        <a href="/webpanel/index.php" class="nav-item <?php echo $current_page === 'index' ? 'active' : ''; ?>">
            <span class="icon">📊</span>
            <span>داشبورد</span>
        </a>
        <a href="/webpanel/users.php" class="nav-item <?php echo $current_page === 'users' ? 'active' : ''; ?>">
            <span class="icon">👥</span>
            <span>مدیریت کاربران</span>
        </a>
        <a href="/webpanel/invoices.php" class="nav-item <?php echo $current_page === 'invoices' ? 'active' : ''; ?>">
            <span class="icon">📋</span>
            <span>فاکتورها و سرویس‌ها</span>
        </a>
        <a href="/webpanel/payments.php" class="nav-item <?php echo $current_page === 'payments' ? 'active' : ''; ?>">
            <span class="icon">💳</span>
            <span>پرداخت‌ها</span>
        </a>
        <?php if (($admin['rule'] ?? '') === 'administrator'): ?>
        <a href="/webpanel/wallet_history.php" class="nav-item <?php echo $current_page === 'wallet_history' ? 'active' : ''; ?>">
            <span class="icon">🧾</span>
            <span>تاریخچه کیف پول</span>
        </a>
        <?php endif; ?>
        <a href="/webpanel/panels.php" class="nav-item <?php echo $current_page === 'panels' ? 'active' : ''; ?>">
            <span class="icon">🖥️</span>
            <span>پنل‌های VPN</span>
        </a>
        <a href="/webpanel/products.php" class="nav-item <?php echo $current_page === 'products' ? 'active' : ''; ?>">
            <span class="icon">📦</span>
            <span>محصولات</span>
        </a>
        <a href="/webpanel/discount_manager.php" class="nav-item <?php echo $current_page === 'discount_manager' ? 'active' : ''; ?>">
            <span class="icon">🏷️</span>
            <span>تخفیف‌ها</span>
        </a>
        <a href="/webpanel/gift_manager.php" class="nav-item <?php echo $current_page === 'gift_manager' ? 'active' : ''; ?>">
            <span class="icon">🎁</span>
            <span>هدیه گروهی</span>
        </a>
        <a href="/webpanel/textbot_manager.php" class="nav-item <?php echo $current_page === 'textbot_manager' ? 'active' : ''; ?>">
            <span class="icon">📝</span>
            <span>مدیریت متن ربات</span>
        </a>
        <a href="/webpanel/test_quota.php" class="nav-item <?php echo $current_page === 'test_quota' ? 'active' : ''; ?>">
            <span class="icon">🧪</span>
            <span>سهمیه تست</span>
        </a>
        <a href="/webpanel/settings.php" class="nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
            <span class="icon">⚙️</span>
            <span>تنظیمات</span>
        </a>
        <a href="/webpanel/channel_manager.php" class="nav-item <?php echo $current_page === 'channel_manager' ? 'active' : ''; ?>">
            <span class="icon">🔗</span>
            <span>کانال‌ها</span>
        </a>
        <a href="/webpanel/reports.php" class="nav-item <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
            <span class="icon">📈</span>
            <span>گزارشات</span>
        </a>
        <?php if (($admin['rule'] ?? '') === 'administrator'): ?>
        <a href="/webpanel/notifications.php" class="nav-item <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
            <span class="icon">🔔</span>
            <span>اعلان‌ها</span>
        </a>
        <?php endif; ?>
        <a href="/webpanel/bot_management.php" class="nav-item <?php echo $current_page === 'bot_management' ? 'active' : ''; ?>">
            <span class="icon">🤖</span>
            <span>مدیریت ربات</span>
        </a>
        <a href="/webpanel/system.php" class="nav-item <?php echo $current_page === 'system' ? 'active' : ''; ?>">
            <span class="icon">🔧</span>
            <span>سیستم</span>
        </a>
        <?php if (($admin['rule'] ?? '') === 'administrator'): ?>
        <a href="/webpanel/admin_logs.php" class="nav-item <?php echo $current_page === 'admin_logs' ? 'active' : ''; ?>">
            <span class="icon">📜</span>
            <span>فعالیت ادمین‌ها</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="admin-info">
            <strong><?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?></strong>
            <small><?php echo htmlspecialchars($admin['rule'] ?? 'Administrator'); ?></small>
        </div>
        <button class="theme-toggle" style="width: 100%; margin-bottom: 10px;">
            <span>🌙</span><span>حالت تاریک</span>
        </button>
        <a href="/webpanel/logout.php" class="btn-logout">خروج</a>
    </div>
</aside>
