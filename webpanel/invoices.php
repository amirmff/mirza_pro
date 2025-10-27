<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

$admin = $auth->getCurrentAdmin();

// Fetch invoices from bot's invoice table
$invoices = select("invoice", "*", null, null, "fetchAll");

// Get user and panel info for each invoice
foreach ($invoices as &$invoice) {
    $user = select("user", "username", "id", $invoice['id_user'], "select");
    $invoice['user_telegram'] = $user['username'] ?? 'N/A';
    
    $panel = getPanelByName($invoice['Service_location']);
    $invoice['panel_url'] = $panel['url_panel'] ?? 'N/A';
}
$page = $_GET['page'] ?? 1;
$status_filter = $_GET['status'] ?? 'all';

$where = [];
if ($status_filter !== 'all') {
    $where[] = "Status = '$status_filter'";
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$limit = 50;
$offset = ($page - 1) * $limit;

$invoices_stmt = $pdo->query("SELECT i.*, u.username, u.number FROM invoice i LEFT JOIN user u ON i.id_user = u.id $where_clause ORDER BY i.id_invoice DESC LIMIT $limit OFFSET $offset");
$invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_stmt = $pdo->query("SELECT COUNT(*) as total FROM invoice i $where_clause");
$total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت فاکتورها و سرویس‌ها - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>فاکتورها و سرویس‌ها</h1>
                <div class="topbar-actions">
                    <button class="btn-icon" onclick="location.reload()">🔄</button>
                </div>
            </div>
            
            <div class="content-area">
                <!-- Filters -->
                <div class="section">
                    <div class="filters">
                        <select onchange="window.location.href='?status='+this.value" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>همه سرویس‌ها</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>فعال</option>
                            <option value="deactive" <?php echo $status_filter === 'deactive' ? 'selected' : ''; ?>>غیرفعال</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>منقضی شده</option>
                        </select>
                    </div>
                </div>
                
                <!-- Invoices Table -->
                <div class="section">
                    <div class="section-header">
                        <h2>لیست سرویس‌ها</h2>
                        <span class="badge"><?php echo number_format($total); ?> سرویس</span>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>کاربر</th>
                                    <th>نام سرویس</th>
                                    <th>لوکیشن</th>
                                    <th>حجم/مدت</th>
                                    <th>قیمت</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>انقضا</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo $invoice['id_invoice']; ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($invoice['username'] ?? 'N/A'); ?></div>
                                        <small><?php echo htmlspecialchars($invoice['number'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['name_product'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['Location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($invoice['volume_GB'])): ?>
                                            <?php echo $invoice['volume_GB']; ?> GB
                                        <?php elseif (!empty($invoice['Day'])): ?>
                                            <?php echo $invoice['Day']; ?> روز
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($invoice['price'] ?? 0); ?> تومان</td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $invoice['Status'] === 'active' ? 'success' : 
                                                ($invoice['Status'] === 'expired' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($invoice['Status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['date_invoice'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['date_off'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn-sm" onclick="viewInvoice(<?php echo $invoice['id_invoice']; ?>)">مشاهده</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>" 
                               class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        function viewInvoice(id) {
            // TODO: Implement invoice detail modal
            alert('Invoice ID: ' + id);
        }
    </script>
</body>
</html>
