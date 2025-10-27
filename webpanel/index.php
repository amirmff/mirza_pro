<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

// Use bot_core functions
$stats = getStatistics();
$admin = $auth->getCurrentAdmin();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>🚀 Mirza Pro</h2>
                <p>پنل مدیریت</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="/webpanel/index.php" class="nav-item active">
                    <span class="icon">📊</span>
                    <span>داشبورد</span>
                </a>
                <a href="/webpanel/users.php" class="nav-item">
                    <span class="icon">👥</span>
                    <span>مدیریت کاربران</span>
                </a>
                <a href="/webpanel/invoices.php" class="nav-item">
                    <span class="icon">📋</span>
                    <span>فاکتورها و سرویس‌ها</span>
                </a>
                <a href="/webpanel/payments.php" class="nav-item">
                    <span class="icon">💳</span>
                    <span>پرداخت‌ها</span>
                </a>
                <a href="/webpanel/panels.php" class="nav-item">
                    <span class="icon">🖥️</span>
                    <span>پنل‌های VPN</span>
                </a>
                <a href="/webpanel/products.php" class="nav-item">
                    <span class="icon">📦</span>
                    <span>محصولات</span>
                </a>
                <a href="/webpanel/settings.php" class="nav-item">
                    <span class="icon">⚙️</span>
                    <span>تنظیمات</span>
                </a>
                <a href="/webpanel/reports.php" class="nav-item">
                    <span class="icon">📈</span>
                    <span>گزارشات</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="admin-info">
                    <strong><?php echo htmlspecialchars($admin['username_admin']); ?></strong>
                    <small><?php echo htmlspecialchars($admin['rule']); ?></small>
                </div>
                <a href="/webpanel/logout.php" class="btn-logout">خروج</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <h1>داشبورد</h1>
                <div class="topbar-actions">
                    <button class="btn-icon" onclick="location.reload()">
                        🔄
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>کل کاربران</p>
                        </div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['active_users']); ?></h3>
                            <p>کاربران فعال</p>
                        </div>
                    </div>
                    
                    <div class="stat-card purple">
                        <div class="stat-icon">📋</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['active_services']); ?></h3>
                            <p>سرویس‌های فعال</p>
                        </div>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_revenue']); ?></h3>
                            <p>کل درآمد (تومان)</p>
                        </div>
                    </div>
                    
                    <div class="stat-card red">
                        <div class="stat-icon">📆</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['today_revenue']); ?></h3>
                            <p>درآمد امروز (تومان)</p>
                        </div>
                    </div>
                    
                    <div class="stat-card teal">
                        <div class="stat-icon">🆕</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['new_users_today']); ?></h3>
                            <p>کاربران جدید امروز</p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="section">
                    <h2 class="section-title">دسترسی سریع</h2>
                    <div class="quick-actions">
                        <a href="/webpanel/users.php" class="action-card">
                            <span class="action-icon">➕</span>
                            <h3>افزودن کاربر</h3>
                            <p>ثبت کاربر جدید در سیستم</p>
                        </a>
                        <a href="/webpanel/panels.php" class="action-card">
                            <span class="action-icon">🖥️</span>
                            <h3>مدیریت پنل‌ها</h3>
                            <p>تنظیمات پنل‌های VPN</p>
                        </a>
                        <a href="/webpanel/payments.php" class="action-card">
                            <span class="action-icon">💳</span>
                            <h3>تایید پرداخت</h3>
                            <p>بررسی و تایید پرداخت‌ها</p>
                        </a>
                        <a href="/webpanel/reports.php" class="action-card">
                            <span class="action-icon">📊</span>
                            <h3>گزارشات</h3>
                            <p>مشاهده آمار و گزارشات</p>
                        </a>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="section">
                    <h2 class="section-title">فعالیت‌های اخیر</h2>
                    <div class="activity-list" id="activityList">
                        <div class="loading">در حال بارگذاری...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        // Load recent activity
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentActivity();
        });
        
        function loadRecentActivity() {
            fetch('/webpanel/api/recent_activity.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('activityList');
                        if (data.activities.length === 0) {
                            container.innerHTML = '<div class="activity-item"><p>فعالیت اخیری وجود ندارد</p></div>';
                            return;
                        }
                        container.innerHTML = data.activities.map(activity => `
                            <div class="activity-item ${activity.type}">
                                <span class="activity-icon">${activity.icon}</span>
                                <div class="activity-info">
                                    <p>${activity.text}</p>
                                    <small>${activity.time}</small>
                                </div>
                            </div>
                        `).join('');
                    }
                })
                .catch(error => {
                    console.error('Error loading activities:', error);
                    document.getElementById('activityList').innerHTML = '<div class="activity-item"><p>خطا در بارگذاری فعالیت‌ها</p></div>';
                });
        }
    </script>
</body>
</html>
