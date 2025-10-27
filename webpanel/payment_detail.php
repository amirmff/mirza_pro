<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();

$api = new API();
$admin = $auth->getCurrentAdmin();

// Get payment ID from URL
$payment_id = $_GET['id'] ?? null;
if (!$payment_id) {
    header('Location: /webpanel/payments.php');
    exit;
}

// Fetch payment details
$payment = $api->getPaymentDetails($payment_id);
if (!$payment) {
    header('Location: /webpanel/payments.php?error=not_found');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات پرداخت - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .payment-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #d1ecf1; color: #0c5460; }
        
        .receipt-preview {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-top: 10px;
            cursor: pointer;
        }
        
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
        
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }
        
        .receipt-full-preview {
            width: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        
        .admin-note {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid #007bff;
        }
        
        .admin-note-header {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }
        
        .admin-note-text {
            color: #666;
        }
        
        @media (max-width: 768px) {
            .payment-detail-grid {
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
                <h1>جزئیات پرداخت #<?php echo htmlspecialchars($payment_id); ?></h1>
                <div class="topbar-actions">
                    <button class="btn-icon" onclick="window.location.href='/webpanel/payments.php'">
                        🔙 بازگشت
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="payment-detail-grid">
                    <!-- Payment Information -->
                    <div class="detail-card">
                        <h3>اطلاعات پرداخت</h3>
                        <div class="detail-row">
                            <span class="detail-label">شناسه تراکنش:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">نام کاربری:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['username_id']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">وضعیت:</span>
                            <span class="detail-value">
                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                    <?php 
                                    $statusMap = [
                                        'completed' => 'تکمیل شده',
                                        'pending' => 'در انتظار',
                                        'rejected' => 'رد شده',
                                        'refunded' => 'بازگشت داده شده'
                                    ];
                                    echo $statusMap[$payment['status']] ?? $payment['status'];
                                    ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">مبلغ:</span>
                            <span class="detail-value"><?php echo number_format($payment['Price']); ?> تومان</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">روش پرداخت:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['method'] ?? 'کارت به کارت'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">تاریخ:</span>
                            <span class="detail-value"><?php echo $payment['timestamp']; ?></span>
                        </div>
                        
                        <?php if (!empty($payment['admin_note'])): ?>
                        <div class="admin-note">
                            <div class="admin-note-header">یادداشت مدیر:</div>
                            <div class="admin-note-text"><?php echo nl2br(htmlspecialchars($payment['admin_note'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Receipt Preview -->
                    <div class="detail-card">
                        <h3>رسید پرداخت</h3>
                        <?php if (!empty($payment['receipt_path'])): ?>
                        <img src="<?php echo htmlspecialchars($payment['receipt_path']); ?>" 
                             alt="رسید پرداخت" 
                             class="receipt-preview"
                             onclick="viewFullReceipt()">
                        <p style="text-align: center; margin-top: 10px; color: #777; font-size: 13px;">
                            کلیک کنید برای مشاهده در اندازه کامل
                        </p>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <p>رسید پرداختی آپلود نشده است</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions Section -->
                <div class="detail-card" style="margin-top: 20px;">
                    <h3>عملیات</h3>
                    <div class="action-buttons">
                        <?php if ($payment['status'] === 'pending'): ?>
                        <button class="btn btn-success" onclick="approvePayment()">
                            ✅ تایید پرداخت
                        </button>
                        <button class="btn btn-danger" onclick="rejectPayment()">
                            ❌ رد پرداخت
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($payment['status'] === 'completed'): ?>
                        <button class="btn btn-warning" onclick="refundPayment()">
                            💰 بازگشت وجه
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-info" onclick="addNote()">
                            📝 افزودن یادداشت
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- View Full Receipt Modal -->
    <div id="receiptModal" class="modal">
        <div class="modal-content">
            <h2>رسید پرداخت</h2>
            <?php if (!empty($payment['receipt_path'])): ?>
            <img src="<?php echo htmlspecialchars($payment['receipt_path']); ?>" 
                 alt="رسید پرداخت" 
                 class="receipt-full-preview">
            <?php endif; ?>
            <div class="action-buttons">
                <button class="btn btn-danger" onclick="closeModal('receiptModal')">بستن</button>
            </div>
        </div>
    </div>
    
    <!-- Approve Payment Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <h2>تایید پرداخت</h2>
            <form id="approveForm">
                <div class="form-group">
                    <label>یادداشت (اختیاری):</label>
                    <textarea name="note" placeholder="یادداشت خود را وارد کنید..."></textarea>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-success">تایید نهایی</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('approveModal')">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reject Payment Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h2>رد پرداخت</h2>
            <form id="rejectForm">
                <div class="form-group">
                    <label>دلیل رد (الزامی):</label>
                    <textarea name="reason" placeholder="دلیل رد پرداخت را وارد کنید..." required></textarea>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-danger">رد پرداخت</button>
                    <button type="button" class="btn btn-info" onclick="closeModal('rejectModal')">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Refund Payment Modal -->
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <h2>بازگشت وجه</h2>
            <form id="refundForm">
                <div class="form-group">
                    <label>دلیل بازگشت وجه:</label>
                    <textarea name="reason" placeholder="دلیل بازگشت وجه را وارد کنید..." required></textarea>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-warning">بازگشت وجه</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('refundModal')">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Note Modal -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <h2>افزودن یادداشت</h2>
            <form id="noteForm">
                <div class="form-group">
                    <label>یادداشت:</label>
                    <textarea name="note" placeholder="یادداشت خود را وارد کنید..." required><?php echo htmlspecialchars($payment['admin_note'] ?? ''); ?></textarea>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-info">ذخیره</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('noteModal')">انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        const paymentId = '<?php echo $payment_id; ?>';
        
        function viewFullReceipt() {
            document.getElementById('receiptModal').classList.add('active');
        }
        
        function approvePayment() {
            document.getElementById('approveModal').classList.add('active');
        }
        
        function rejectPayment() {
            document.getElementById('rejectModal').classList.add('active');
        }
        
        function refundPayment() {
            document.getElementById('refundModal').classList.add('active');
        }
        
        function addNote() {
            document.getElementById('noteModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Handle approve form
        document.getElementById('approveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('payment_id', paymentId);
            
            fetch('/webpanel/api/approve_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('پرداخت با موفقیت تایید شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
        
        // Handle reject form
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('payment_id', paymentId);
            
            fetch('/webpanel/api/reject_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('پرداخت رد شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
        
        // Handle refund form
        document.getElementById('refundForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!confirm('آیا از بازگشت وجه اطمینان دارید؟')) return;
            
            const formData = new FormData(this);
            formData.append('payment_id', paymentId);
            
            fetch('/webpanel/api/refund_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('وجه با موفقیت بازگشت داده شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
        
        // Handle note form
        document.getElementById('noteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('payment_id', paymentId);
            
            fetch('/webpanel/api/add_payment_note.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('یادداشت با موفقیت ذخیره شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>
