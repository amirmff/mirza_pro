<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

$admin = $auth->getCurrentAdmin();

// Fetch all panels from bot's marzban_panel table
$panels = getAllPanels();

// Get stats for each panel
foreach ($panels as &$panel) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoice WHERE Service_location = ?");
    $stmt->execute([$panel['name_panel']]);
    $panel['services_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
$panels = $panels_result['panels'] ?? [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت پنل‌های VPN - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت پنل‌های VPN</h1>
            </div>
            
            <div class="content-area">
                <div class="section">
                    <div class="section-header">
                        <h2>لیست پنل‌ها</h2>
                        <span class="badge"><?php echo count($panels); ?> پنل</span>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>نام پنل</th>
                                    <th>نوع</th>
                                    <th>URL</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($panels as $panel): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($panel['name_panel'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($panel['type_panel'] ?? 'Marzban'); ?></td>
                                    <td><small><?php echo htmlspecialchars($panel['url_panel'] ?? 'N/A'); ?></small></td>
                                    <td>
                                        <span class="badge success">فعال</span>
                                    </td>
                                    <td>
                                        <button class="btn-sm" onclick="testPanel('<?php echo $panel['name_panel']; ?>')">تست اتصال</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function testPanel(name) {
            alert('Testing panel: ' + name);
        }
    </script>
</body>
</html>
