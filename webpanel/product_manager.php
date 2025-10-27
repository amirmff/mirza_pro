<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();

$api = new API();
$admin = $auth->getCurrentAdmin();

// Fetch all products
$products = $api->getProducts();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت محصولات - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .product-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .product-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-enabled { background: #d4edda; color: #155724; }
        .status-disabled { background: #f8d7da; color: #721c24; }
        
        .product-info {
            margin: 10px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            color: #777;
            font-size: 13px;
        }
        
        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 13px;
        }
        
        .product-price {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            text-align: center;
            margin: 15px 0;
        }
        
        .product-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            flex: 1;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .add-product-btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-product-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #28a745;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت محصولات VPN</h1>
                <div class="topbar-actions">
                    <button class="add-product-btn" onclick="showAddProduct()">
                        ➕ افزودن محصول جدید
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="product-grid" id="productGrid">
                    <?php if (empty($products)): ?>
                    <div class="detail-card" style="grid-column: 1/-1; text-align: center; padding: 40px;">
                        <p style="color: #999;">هیچ محصولی وجود ندارد. محصول اول خود را اضافه کنید.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-header">
                                <div class="product-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                <span class="product-status status-<?php echo $product['enabled'] ? 'enabled' : 'disabled'; ?>">
                                    <?php echo $product['enabled'] ? 'فعال' : 'غیرفعال'; ?>
                                </span>
                            </div>
                            <div class="product-price">
                                <?php echo number_format($product['price']); ?> تومان
                            </div>
                            <div class="product-info">
                                <div class="info-row">
                                    <span class="info-label">حجم:</span>
                                    <span class="info-value"><?php echo $product['volume']; ?> GB</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">مدت:</span>
                                    <span class="info-value"><?php echo $product['days']; ?> روز</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">لوکیشن:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($product['location'] ?? 'همه'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">فروش:</span>
                                    <span class="info-value"><?php echo $product['sold'] ?? 0; ?> عدد</span>
                                </div>
                            </div>
                            <div class="product-actions">
                                <button class="btn btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)">✏️ ویرایش</button>
                                <button class="btn btn-<?php echo $product['enabled'] ? 'warning' : 'success'; ?>" 
                                        onclick="toggleProduct(<?php echo $product['id']; ?>, <?php echo $product['enabled'] ? 'false' : 'true'; ?>)">
                                    <?php echo $product['enabled'] ? '⏸️ غیرفعال' : '▶️ فعال'; ?>
                                </button>
                                <button class="btn btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">🗑️</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">افزودن محصول جدید</h2>
            <form id="productForm">
                <input type="hidden" name="product_id" id="productId">
                <div class="form-group">
                    <label>نام محصول:</label>
                    <input type="text" name="name" id="productName" placeholder="مثال: پلن 50 گیگ" required>
                </div>
                <div class="form-group">
                    <label>توضیحات:</label>
                    <textarea name="description" id="productDescription" rows="3" placeholder="توضیحات محصول..."></textarea>
                </div>
                <div class="form-group">
                    <label>قیمت (تومان):</label>
                    <input type="number" name="price" id="productPrice" min="0" required>
                </div>
                <div class="form-group">
                    <label>حجم (گیگابایت):</label>
                    <input type="number" name="volume" id="productVolume" min="1" required>
                </div>
                <div class="form-group">
                    <label>مدت اعتبار (روز):</label>
                    <input type="number" name="days" id="productDays" min="1" required>
                </div>
                <div class="form-group">
                    <label>لوکیشن (اختیاری):</label>
                    <select name="location" id="productLocation">
                        <option value="">همه لوکیشن‌ها</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>وضعیت:</label>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enabled" id="productEnabled" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span style="margin-right: 10px;">فعال</span>
                </div>
                <div class="product-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">💾 ذخیره</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('productModal')">❌ انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        function showAddProduct() {
            document.getElementById('modalTitle').textContent = 'افزودن محصول جدید';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('productEnabled').checked = true;
            loadLocations();
            document.getElementById('productModal').classList.add('active');
        }
        
        function editProduct(id) {
            fetch(`/webpanel/api/get_product.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        document.getElementById('modalTitle').textContent = 'ویرایش محصول';
                        document.getElementById('productId').value = product.id;
                        document.getElementById('productName').value = product.name;
                        document.getElementById('productDescription').value = product.description || '';
                        document.getElementById('productPrice').value = product.price;
                        document.getElementById('productVolume').value = product.volume;
                        document.getElementById('productDays').value = product.days;
                        document.getElementById('productEnabled').checked = product.enabled;
                        
                        loadLocations(product.location);
                        document.getElementById('productModal').classList.add('active');
                    }
                });
        }
        
        function deleteProduct(id) {
            if (!confirm('آیا از حذف این محصول اطمینان دارید؟')) return;
            
            fetch('/webpanel/api/delete_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('محصول با موفقیت حذف شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        }
        
        function toggleProduct(id, enabled) {
            fetch('/webpanel/api/toggle_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: id, enabled: enabled })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        }
        
        function loadLocations(selectedLocation = '') {
            fetch('/webpanel/api/get_locations.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('productLocation');
                        select.innerHTML = '<option value="">همه لوکیشن‌ها</option>';
                        data.locations.forEach(loc => {
                            const option = document.createElement('option');
                            option.value = loc.id;
                            option.textContent = loc.name;
                            if (loc.id == selectedLocation) option.selected = true;
                            select.appendChild(option);
                        });
                    }
                });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Handle form submission
        document.getElementById('productForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            const endpoint = formData.get('product_id') ? 
                '/webpanel/api/update_product.php' : 
                '/webpanel/api/add_product.php';
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('محصول با موفقیت ذخیره شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>
