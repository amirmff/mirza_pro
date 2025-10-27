<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

$admin = $auth->getCurrentAdmin();

// Fetch all products from bot's product table
$products = getAllProducts();

// Get sales count for each product
foreach ($products as &$product) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoice WHERE name_product = ?");
    $stmt->execute([$product['name_product']]);
    $product['sold_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت محصولات - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت محصولات</h1>
            </div>
            
            <div class="content-area">
                <div class="section">
                    <div class="section-header">
                        <h2>لیست محصولات</h2>
                        <span class="badge"><?php echo count($products); ?> محصول</span>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>نام محصول</th>
                                    <th>لوکیشن</th>
                                    <th>حجم/مدت</th>
                                    <th>قیمت</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name_product'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['Location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($product['volume_GB'])): ?>
                                            <?php echo $product['volume_GB']; ?> GB
                                        <?php elseif (!empty($product['Day'])): ?>
                                            <?php echo $product['Day']; ?> روز
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($product['price_product'] ?? 0); ?> تومان</td>
                                    <td>
                                        <span class="badge <?php echo ($product['status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ($product['status'] ?? 'active') === 'active' ? 'فعال' : 'غیرفعال'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-sm" onclick="editProduct(<?php echo $product['id_product'] ?? 0; ?>)">ویرایش</button>
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
        function editProduct(id) {
            alert('Edit product ID: ' + id);
        }
    </script>
</body>
</html>
