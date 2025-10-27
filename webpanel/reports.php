<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
$api = new API();

$stats = $api->getStatistics();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>گزارشات - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>گزارشات و آمار</h1>
            </div>
            
            <div class="content-area">
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['stats']['total_users']); ?></h3>
                            <p>کل کاربران</p>
                        </div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['stats']['active_users']); ?></h3>
                            <p>کاربران فعال</p>
                        </div>
                    </div>
                    
                    <div class="stat-card purple">
                        <div class="stat-icon">📋</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['stats']['active_services']); ?></h3>
                            <p>سرویس‌های فعال</p>
                        </div>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="stat-icon">💰</div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['stats']['total_revenue']); ?></h3>
                            <p>کل درآمد</p>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>گزارش فروش ماهانه</h2>
                    <div class="chart-placeholder">
                        <p>نمودار فروش ماهانه اینجا قرار می‌گیرد</p>
                    </div>
                </div>
                
                <div class="section">
                    <h2>محبوب‌ترین محصولات</h2>
                    <div class="chart-placeholder">
                        <p>نمودار محبوب‌ترین محصولات اینجا قرار می‌گیرد</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
