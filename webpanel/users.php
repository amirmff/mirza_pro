<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

$admin = $auth->getCurrentAdmin();

// Fetch users from bot's user table
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

global $pdo;
$stmt = $pdo->query("SELECT COUNT(*) as total FROM user");
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT * FROM user ORDER BY register DESC LIMIT ? OFFSET ?");
$stmt->execute([$limit, $offset]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users_data = [
    'users' => $users,
    'total' => $total,
    'pages' => ceil($total / $limit)
];
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت کاربران</h1>
                <div class="topbar-actions">
                    <button class="btn-icon" onclick="location.reload()">🔄</button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="section">
                    <div class="section-header">
                        <h2>لیست کاربران</h2>
                        <span class="badge"><?php echo count($users); ?> کاربر</span>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>نام کاربری</th>
                                    <th>موجودی</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ ثبت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($user['Balance'] ?? 0); ?> تومان</td>
                                    <td>
                                        <span class="badge <?php echo ($user['User_Status'] ?? '') == 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo htmlspecialchars($user['User_Status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['register'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn-sm" onclick="viewUser('<?php echo $user['id']; ?>')">مشاهده</button>
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
    
    <script src="/webpanel/assets/js/main.js"></script>
</body>
</html>
