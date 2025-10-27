<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();

$api = new API();
$admin = $auth->getCurrentAdmin();

// Fetch all discount codes
$discounts = $api->getDiscounts();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کدهای تخفیف - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 20px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #f0f0f0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-used { background: #fff3cd; color: #856404; }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            margin: 0 2px;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .add-discount-btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-discount-btn:hover {
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
        
        .code-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            font-family: monospace;
            color: #28a745;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت کدهای تخفیف</h1>
                <div class="topbar-actions">
                    <button class="add-discount-btn" onclick="showAddDiscount()">
                        ➕ افزودن کد تخفیف
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="table-container">
                    <?php if (empty($discounts)): ?>
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <p>هیچ کد تخفیفی وجود ندارد. کد تخفیف اول خود را ایجاد کنید.</p>
                    </div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>کد</th>
                                <th>نوع</th>
                                <th>مقدار</th>
                                <th>استفاده</th>
                                <th>تاریخ انقضا</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($discounts as $discount): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($discount['code']); ?></code></td>
                                <td><?php echo $discount['type'] === 'percent' ? 'درصدی' : 'مبلغ ثابت'; ?></td>
                                <td>
                                    <?php 
                                    if ($discount['type'] === 'percent') {
                                        echo $discount['value'] . '%';
                                    } else {
                                        echo number_format($discount['value']) . ' تومان';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $discount['used']; ?> / <?php echo $discount['max_uses'] > 0 ? $discount['max_uses'] : '∞'; ?></td>
                                <td><?php echo $discount['expires_at'] ? date('Y/m/d', strtotime($discount['expires_at'])) : 'نامحدود'; ?></td>
                                <td>
                                    <?php
                                    $status = 'active';
                                    $statusText = 'فعال';
                                    
                                    if ($discount['max_uses'] > 0 && $discount['used'] >= $discount['max_uses']) {
                                        $status = 'used';
                                        $statusText = 'استفاده شده';
                                    } elseif ($discount['expires_at'] && strtotime($discount['expires_at']) < time()) {
                                        $status = 'expired';
                                        $statusText = 'منقضی';
                                    }
                                    ?>
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary" onclick="editDiscount(<?php echo $discount['id']; ?>)">✏️</button>
                                    <button class="btn btn-danger" onclick="deleteDiscount(<?php echo $discount['id']; ?>)">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Discount Modal -->
    <div id="discountModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">افزودن کد تخفیف جدید</h2>
            <form id="discountForm">
                <input type="hidden" name="discount_id" id="discountId">
                <div class="form-group">
                    <label>کد تخفیف:</label>
                    <input type="text" name="code" id="discountCode" placeholder="مثال: SUMMER2024" required>
                    <button type="button" class="btn btn-success" onclick="generateCode()" style="margin-top: 5px;">
                        🎲 تولید خودکار
                    </button>
                </div>
                <div class="form-group">
                    <label>نوع تخفیف:</label>
                    <select name="type" id="discountType" required onchange="updateValueLabel()">
                        <option value="percent">درصدی</option>
                        <option value="fixed">مبلغ ثابت</option>
                    </select>
                </div>
                <div class="form-group">
                    <label id="valueLabel">مقدار تخفیف (درصد):</label>
                    <input type="number" name="value" id="discountValue" min="1" required>
                </div>
                <div class="form-group">
                    <label>حداکثر تعداد استفاده (0 = نامحدود):</label>
                    <input type="number" name="max_uses" id="discountMaxUses" value="0" min="0" required>
                </div>
                <div class="form-group">
                    <label>تاریخ انقضا (اختیاری):</label>
                    <input type="datetime-local" name="expires_at" id="discountExpiresAt">
                </div>
                <div class="form-group">
                    <label>توضیحات (اختیاری):</label>
                    <textarea name="description" id="discountDescription" rows="3" placeholder="توضیحات درباره این کد تخفیف..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1; padding: 12px;">💾 ذخیره</button>
                    <button type="button" class="btn btn-danger" style="flex: 1; padding: 12px;" onclick="closeModal('discountModal')">❌ انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        function showAddDiscount() {
            document.getElementById('modalTitle').textContent = 'افزودن کد تخفیف جدید';
            document.getElementById('discountForm').reset();
            document.getElementById('discountId').value = '';
            updateValueLabel();
            document.getElementById('discountModal').classList.add('active');
        }
        
        function editDiscount(id) {
            fetch(`/webpanel/api/get_discount.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const discount = data.discount;
                        document.getElementById('modalTitle').textContent = 'ویرایش کد تخفیف';
                        document.getElementById('discountId').value = discount.id;
                        document.getElementById('discountCode').value = discount.code;
                        document.getElementById('discountType').value = discount.type;
                        document.getElementById('discountValue').value = discount.value;
                        document.getElementById('discountMaxUses').value = discount.max_uses;
                        document.getElementById('discountDescription').value = discount.description || '';
                        
                        if (discount.expires_at) {
                            const date = new Date(discount.expires_at);
                            document.getElementById('discountExpiresAt').value = date.toISOString().slice(0, 16);
                        }
                        
                        updateValueLabel();
                        document.getElementById('discountModal').classList.add('active');
                    }
                });
        }
        
        function deleteDiscount(id) {
            if (!confirm('آیا از حذف این کد تخفیف اطمینان دارید؟')) return;
            
            fetch('/webpanel/api/delete_discount.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ discount_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('کد تخفیف با موفقیت حذف شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        }
        
        function generateCode() {
            const length = 8;
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let code = '';
            for (let i = 0; i < length; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('discountCode').value = code;
        }
        
        function updateValueLabel() {
            const type = document.getElementById('discountType').value;
            const label = document.getElementById('valueLabel');
            label.textContent = type === 'percent' ? 'مقدار تخفیف (درصد):' : 'مقدار تخفیف (تومان):';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Handle form submission
        document.getElementById('discountForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            const endpoint = formData.get('discount_id') ? 
                '/webpanel/api/update_discount.php' : 
                '/webpanel/api/add_discount.php';
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('کد تخفیف با موفقیت ذخیره شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>
