<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
$api = new API();

$user_id = $_GET['id'] ?? 0;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_balance') {
        $result = $api->updateUser($user_id, ['Balance' => $_POST['balance']]);
        $message = $result['success'] ? $result['message'] : $result['error'];
    } elseif ($action === 'update_status') {
        $result = $api->updateUser($user_id, ['User_Status' => $_POST['status']]);
        $message = $result['success'] ? $result['message'] : $result['error'];
    } elseif ($action === 'send_message') {
        $result = $api->sendMessageToUser($user_id, $_POST['message']);
        $message = $result['success'] ? $result['message'] : $result['error'];
    }
}

$user_result = $api->getUserDetails($user_id);
if (!$user_result['success']) {
    header('Location: /webpanel/users.php');
    exit;
}
$user = $user_result['user'];
$invoices = $user_result['invoices'];
$payments = $user_result['payments'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>جزئیات کاربر - <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>جزئیات کاربر</h1>
                <a href="/webpanel/users.php" class="btn">بازگشت</a>
            </div>
            
            <div class="content-area">
                <?php if (isset($message)): ?>
                    <div class="alert"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <!-- User Info Cards -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <h3>شناسه</h3>
                        <p><?php echo $user['id']; ?></p>
                    </div>
                    <div class="stat-card green">
                        <h3>موجودی</h3>
                        <p><?php echo number_format($user['Balance']); ?> تومان</p>
                    </div>
                    <div class="stat-card purple">
                        <h3>وضعیت</h3>
                        <p><?php echo $user['User_Status']; ?></p>
                    </div>
                    <div class="stat-card orange">
                        <h3>نوع کاربر</h3>
                        <p><?php echo $user['agent'] != 'f' ? 'نماینده' : 'عادی'; ?></p>
                    </div>
                </div>
                
                <!-- Edit Forms -->
                <div class="grid-2">
                    <div class="section">
                        <h2>ویرایش موجودی</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_balance">
                            <input type="number" name="balance" value="<?php echo $user['Balance']; ?>" class="form-control">
                            <button type="submit" class="btn">بروزرسانی</button>
                        </form>
                    </div>
                    
                    <div class="section">
                        <h2>ویرایش وضعیت</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <select name="status" class="form-control">
                                <option value="Active" <?php echo $user['User_Status'] === 'Active' ? 'selected' : ''; ?>>فعال</option>
                                <option value="block" <?php echo $user['User_Status'] === 'block' ? 'selected' : ''; ?>>مسدود</option>
                            </select>
                            <button type="submit" class="btn">بروزرسانی</button>
                        </form>
                    </div>
                </div>
                
                <!-- Send Message -->
                <div class="section">
                    <h2>ارسال پیام به کاربر</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_message">
                        <textarea name="message" class="form-control" rows="4" placeholder="پیام خود را بنویسید..."></textarea>
                        <button type="submit" class="btn">ارسال</button>
                    </form>
                </div>
                
                <!-- Services -->
                <div class="section">
                    <h2>سرویس‌های کاربر</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>سرویس</th>
                                <th>وضعیت</th>
                                <th>تاریخ خرید</th>
                                <th>انقضا</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?php echo $invoice['id_invoice']; ?></td>
                                <td><?php echo htmlspecialchars($invoice['name_product']); ?></td>
                                <td><span class="badge"><?php echo $invoice['Status']; ?></span></td>
                                <td><?php echo $invoice['date_invoice']; ?></td>
                                <td><?php echo $invoice['date_off']; ?></td>
                                <td>
                                    <a href="/webpanel/invoice_detail.php?id=<?php echo $invoice['id_invoice']; ?>" class="btn-sm">مشاهده</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Payment History -->
                <div class="section">
                    <h2>تاریخچه پرداخت‌ها</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>مبلغ</th>
                                <th>روش</th>
                                <th>وضعیت</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['id_payment']; ?></td>
                                <td><?php echo number_format($payment['price']); ?></td>
                                <td><?php echo htmlspecialchars($payment['Payment_Method']); ?></td>
                                <td><span class="badge"><?php echo $payment['payment_Status']; ?></span></td>
                                <td><?php echo $payment['date']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
