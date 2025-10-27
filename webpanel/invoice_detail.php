<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();

$api = new API();
$admin = $auth->getCurrentAdmin();

// Get invoice ID from URL
$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    header('Location: /webpanel/invoices.php');
    exit;
}

// Fetch invoice details
$invoice = $api->getInvoiceDetails($invoice_id);
if (!$invoice) {
    header('Location: /webpanel/invoices.php?error=not_found');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات سرویس - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .invoice-detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn:hover {
            opacity: 0.9;
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
            max-width: 500px;
            width: 90%;
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
        
        .config-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .config-item {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }
        
        @media (max-width: 768px) {
            .invoice-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>جزئیات سرویس #<?php echo htmlspecialchars($invoice_id); ?></h1>
                <div class="topbar-actions">
                    <button class="btn-icon" onclick="window.location.href='/webpanel/invoices.php'">
                        🔙 بازگشت
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="invoice-detail-grid">
                    <!-- Main Details -->
                    <div>
                        <div class="detail-card">
                            <h3>اطلاعات سرویس</h3>
                            <div class="detail-row">
                                <span class="detail-label">شناسه سرویس:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($invoice['Service_id']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">نام کاربری:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($invoice['username_id']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">وضعیت:</span>
                                <span class="detail-value">
                                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                        <?php 
                                        $statusMap = ['active' => 'فعال', 'expired' => 'منقضی', 'pending' => 'در انتظار'];
                                        echo $statusMap[$invoice['status']] ?? $invoice['status'];
                                        ?>
                                    </span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">محصول:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($invoice['Product_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">پنل:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($invoice['panel'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">لوکیشن:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($invoice['Location'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">حجم:</span>
                                <span class="detail-value"><?php echo number_format($invoice['Volume']); ?> GB</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">قیمت:</span>
                                <span class="detail-value"><?php echo number_format($invoice['Price']); ?> تومان</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">تاریخ شروع:</span>
                                <span class="detail-value"><?php echo $invoice['Date_start']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">تاریخ پایان:</span>
                                <span class="detail-value"><?php echo $invoice['Date_end']; ?></span>
                            </div>
                        </div>
                        
                        <!-- Configuration Details -->
                        <div class="detail-card" style="margin-top: 20px;">
                            <h3>اطلاعات فنی</h3>
                            <div class="config-section">
                                <div class="config-item">
                                    <span>UUID:</span>
                                    <code><?php echo htmlspecialchars($invoice['uuid'] ?? 'N/A'); ?></code>
                                </div>
                                <div class="config-item">
                                    <span>Inbound ID:</span>
                                    <code><?php echo htmlspecialchars($invoice['inboundid'] ?? 'N/A'); ?></code>
                                </div>
                                <div class="config-item">
                                    <span>Email:</span>
                                    <code><?php echo htmlspecialchars($invoice['email'] ?? 'N/A'); ?></code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions Panel -->
                    <div>
                        <div class="detail-card">
                            <h3>عملیات</h3>
                            <div class="action-buttons">
                                <button class="btn btn-primary" onclick="showExtendModal()">
                                    ⏰ تمدید سرویس
                                </button>
                                <button class="btn btn-success" onclick="showEditModal()">
                                    ✏️ ویرایش سرویس
                                </button>
                                <button class="btn btn-info" onclick="showLocationModal()">
                                    🌍 تغییر لوکیشن
                                </button>
                                <button class="btn btn-warning" onclick="resetService()">
                                    🔄 ریست سرویس
                                </button>
                                <button class="btn btn-danger" onclick="deleteService()">
                                    🗑️ حذف سرویس
                                </button>
                            </div>
                        </div>
                        
                        <div class="detail-card" style="margin-top: 20px;">
                            <h3>آمار استفاده</h3>
                            <div class="detail-row">
                                <span class="detail-label">حجم مصرفی:</span>
                                <span class="detail-value">
                                    <?php 
                                    $used = $invoice['used_traffic'] ?? 0;
                                    echo number_format($used / 1024 / 1024 / 1024, 2); 
                                    ?> GB
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">حجم باقیمانده:</span>
                                <span class="detail-value">
                                    <?php 
                                    $remaining = $invoice['Volume'] - ($used / 1024 / 1024 / 1024);
                                    echo number_format($remaining, 2); 
                                    ?> GB
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">روزهای باقیمانده:</span>
                                <span class="detail-value">
                                    <?php
                                    $end = strtotime($invoice['Date_end']);
                                    $now = time();
                                    $daysLeft = max(0, floor(($end - $now) / 86400));
                                    echo $daysLeft . ' روز';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Extend Service Modal -->
    <div id="extendModal" class="modal">
        <div class="modal-content">
            <h2>تمدید سرویس</h2>
            <form id="extendForm">
                <div class="form-group">
                    <label>تعداد روز:</label>
                    <input type="number" name="days" min="1" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">تمدید</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('extendModal')">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Service Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>ویرایش سرویس</h2>
            <form id="editForm">
                <div class="form-group">
                    <label>حجم (GB):</label>
                    <input type="number" name="volume" value="<?php echo $invoice['Volume']; ?>" required>
                </div>
                <div class="form-group">
                    <label>تاریخ پایان:</label>
                    <input type="datetime-local" name="date_end" value="<?php echo date('Y-m-d\TH:i', strtotime($invoice['Date_end'])); ?>" required>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-success">ذخیره</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('editModal')">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Location Modal -->
    <div id="locationModal" class="modal">
        <div class="modal-content">
            <h2>تغییر لوکیشن</h2>
            <form id="locationForm">
                <div class="form-group">
                    <label>لوکیشن جدید:</label>
                    <select name="location" required>
                        <option value="">انتخاب کنید...</option>
                        <!-- Locations will be loaded dynamically -->
                    </select>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-info">تغییر</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('locationModal')">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        const invoiceId = '<?php echo $invoice_id; ?>';
        
        function showExtendModal() {
            document.getElementById('extendModal').classList.add('active');
        }
        
        function showEditModal() {
            document.getElementById('editModal').classList.add('active');
        }
        
        function showLocationModal() {
            document.getElementById('locationModal').classList.add('active');
            loadLocations();
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function loadLocations() {
            fetch('/webpanel/api/get_locations.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.querySelector('#locationForm select[name="location"]');
                        select.innerHTML = '<option value="">انتخاب کنید...</option>';
                        data.locations.forEach(loc => {
                            select.innerHTML += `<option value="${loc.id}">${loc.name}</option>`;
                        });
                    }
                });
        }
        
        // Handle extend form
        document.getElementById('extendForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('invoice_id', invoiceId);
            
            fetch('/webpanel/api/extend_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('سرویس با موفقیت تمدید شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
        
        // Handle edit form
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('invoice_id', invoiceId);
            
            fetch('/webpanel/api/update_service.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('سرویس با موفقیت به‌روزرسانی شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
        
        // Handle location form
        document.getElementById('locationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('invoice_id', invoiceId);
            
            fetch('/webpanel/api/change_location.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('لوکیشن با موفقیت تغییر کرد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
        
        function resetService() {
            if (!confirm('آیا از ریست سرویس اطمینان دارید؟')) return;
            
            fetch('/webpanel/api/reset_service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ invoice_id: invoiceId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('سرویس با موفقیت ریست شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        }
        
        function deleteService() {
            if (!confirm('آیا از حذف سرویس اطمینان دارید؟ این عمل غیرقابل بازگشت است!')) return;
            
            fetch('/webpanel/api/delete_service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ invoice_id: invoiceId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('سرویس با موفقیت حذف شد');
                    window.location.href = '/webpanel/invoices.php';
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>
