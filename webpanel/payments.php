<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();

// Handle payment approval/rejection using bot_core functions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = $_POST['payment_id'] ?? 0;
    $action = $_POST['action'];
    $note = $_POST['note'] ?? '';
    
    if ($action === 'approve') {
        // Use bot's approvePayment function which handles balance update and notifications
        if (approvePayment($payment_id, "Approved by admin: {$admin['username']}")) {
            $message = 'پرداخت با موفقیت تایید شد';
        } else {
            $message = 'خطا در تایید پرداخت';
        }
    } elseif ($action === 'reject') {
        // Use bot's rejectPayment function which sends notification to user
        $reason = $_POST['reason'] ?? 'رد شده توسط ادمین';
        if (rejectPayment($payment_id, $reason)) {
            $message = 'پرداخت رد شد';
        } else {
            $message = 'خطا در رد پرداخت';
        }
    }
}

$status_filter = $_GET['status'] ?? 'pending';
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$where = $status_filter !== 'all' ? "WHERE payment_Status = '$status_filter'" : '';

$payments_stmt = $pdo->query("SELECT p.*, u.username, u.number FROM Payment_report p LEFT JOIN user u ON p.id_user = u.id $where ORDER BY p.id_payment DESC LIMIT $limit OFFSET $offset");
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_stmt = $pdo->query("SELECT COUNT(*) as total FROM Payment_report $where");
$total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت پرداخت‌ها - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت پرداخت‌ها</h1>
            </div>
            
            <div class="content-area">
                <?php if (isset($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <div class="section">
                    <div class="filters">
                        <select onchange="window.location.href='?status='+this.value" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>همه</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>در انتظار تایید</option>
                            <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>پرداخت شده</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                        </select>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h2>لیست پرداخت‌ها</h2>
                        <span class="badge"><?php echo number_format($total); ?> پرداخت</span>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>کاربر</th>
                                    <th>مبلغ</th>
                                    <th>روش پرداخت</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['id_payment']; ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($payment['username'] ?? 'N/A'); ?></div>
                                        <small><?php echo htmlspecialchars($payment['number'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo number_format($payment['price'] ?? 0); ?> تومان</td>
                                    <td><?php echo htmlspecialchars($payment['payment_Method'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $payment['payment_Status'] === 'paid' ? 'success' : 
                                                ($payment['payment_Status'] === 'rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($payment['payment_Status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['date'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($payment['payment_Status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="payment_id" value="<?php echo $payment['id_payment']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn-sm success">تایید</button>
                                                <button type="submit" name="action" value="reject" class="btn-sm danger">رد</button>
                                            </form>
                                        <?php endif; ?>
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
</body>
</html>
