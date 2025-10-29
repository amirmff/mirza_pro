<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }

$page = (int)($_GET['page'] ?? 1);
$page = max($page, 1);
$status_filter = $_GET['status'] ?? 'all';

$where = [];
$params = [];
if ($status_filter !== 'all') {
    $where[] = "i.Status = :status";
    $params[':status'] = $status_filter;
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$limit = 50;
$offset = ($page - 1) * $limit;

$sql = "SELECT i.id_invoice, i.id_user, i.username, i.name_product, i.Service_location, i.Volume, i.Service_time, i.price_product, i.Status, i.time_sell, u.username AS telegram_username, u.number 
        FROM invoice i 
        LEFT JOIN user u ON i.id_user = u.id 
        $where_clause 
        ORDER BY i.id_invoice DESC 
        LIMIT :limit OFFSET :offset";
$invoices_stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $invoices_stmt->bindValue($k, $v); }
$invoices_stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$invoices_stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$invoices_stmt->execute();
$invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM invoice i $where_clause");
foreach ($params as $k=>$v) { $total_stmt->bindValue($k, $v); }
$total_stmt->execute();
$total = (int)$total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = (int)ceil($total / $limit);
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
                                    <td><?php echo htmlspecialchars($invoice['Service_location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($invoice['Volume'])): ?>
                                            <?php echo (int)$invoice['Volume']; ?> GB
                                        <?php elseif (!empty($invoice['Service_time'])): ?>
                                            <?php echo (int)$invoice['Service_time']; ?> روز
                                        <?php else: ?>-
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format((int)($invoice['price_product'] ?? 0)); ?> تومان</td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $invoice['Status'] === 'active' ? 'success' : 
                                                ($invoice['Status'] === 'expired' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($invoice['Status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['time_sell'] ?? 'N/A'); ?></td>
                                    <td><?php echo ($invoice['Service_time'] && is_numeric($invoice['Service_time']) && is_numeric($invoice['time_sell'])) ? date('Y-m-d H:i:s', (int)$invoice['time_sell'] + ((int)$invoice['Service_time']*86400)) : '-'; ?></td>
                                    <td>
                                        <a class="btn-sm" href="/webpanel/invoice_detail.php?id=<?php echo $invoice['id_invoice']; ?>">مشاهده</a>
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
    </script>
</body>
</html>
