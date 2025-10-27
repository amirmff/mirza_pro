<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

$auth = new Auth();
$auth->requireLogin();

$api = new API();
$admin = $auth->getCurrentAdmin();

// Fetch all panels
$panels = $api->getPanels();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پنل‌ها - Mirza Pro Admin Panel</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .panel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .panel-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .panel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .panel-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .panel-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-online { background: #d4edda; color: #155724; }
        .status-offline { background: #f8d7da; color: #721c24; }
        
        .panel-info {
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
        
        .panel-actions {
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
        .btn-info { background: #17a2b8; color: white; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .add-panel-btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-panel-btn:hover {
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
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
        }
        
        .protocol-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .protocol-option {
            position: relative;
        }
        
        .protocol-option input[type="checkbox"] {
            width: auto;
            margin-left: 5px;
        }
        
        .protocol-option label {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .protocol-option input[type="checkbox"]:checked + label {
            background: #007bff;
            color: white;
        }
        
        .test-result {
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .test-result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .test-result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            border-radius: 12px;
        }
        
        .loading-overlay.active {
            display: flex;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت پنل‌های VPN</h1>
                <div class="topbar-actions">
                    <button class="add-panel-btn" onclick="showAddPanel()">
                        ➕ افزودن پنل جدید
                    </button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="panel-grid" id="panelGrid">
                    <?php if (empty($panels)): ?>
                    <div class="detail-card" style="grid-column: 1/-1; text-align: center; padding: 40px;">
                        <p style="color: #999;">هیچ پنلی وجود ندارد. پنل اول خود را اضافه کنید.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($panels as $panel): ?>
                        <div class="panel-card">
                            <div class="loading-overlay" id="loading-<?php echo $panel['id']; ?>">
                                <span>در حال پردازش...</span>
                            </div>
                            <div class="panel-header">
                                <div class="panel-title"><?php echo htmlspecialchars($panel['name']); ?></div>
                                <span class="panel-status status-<?php echo $panel['status'] ?? 'offline'; ?>">
                                    <?php echo $panel['status'] === 'online' ? 'آنلاین' : 'آفلاین'; ?>
                                </span>
                            </div>
                            <div class="panel-info">
                                <div class="info-row">
                                    <span class="info-label">نوع:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($panel['type']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">آدرس:</span>
                                    <span class="info-value" style="font-size: 11px;"><?php echo htmlspecialchars($panel['url']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">لوکیشن:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($panel['location'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">کاربران فعال:</span>
                                    <span class="info-value"><?php echo $panel['active_users'] ?? 0; ?></span>
                                </div>
                            </div>
                            <div class="panel-actions">
                                <button class="btn btn-info" onclick="testConnection(<?php echo $panel['id']; ?>)">🔗 تست</button>
                                <button class="btn btn-primary" onclick="editPanel(<?php echo $panel['id']; ?>)">✏️ ویرایش</button>
                                <button class="btn btn-danger" onclick="deletePanel(<?php echo $panel['id']; ?>)">🗑️ حذف</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Panel Modal -->
    <div id="panelModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">افزودن پنل جدید</h2>
            <form id="panelForm">
                <input type="hidden" name="panel_id" id="panelId">
                <div class="form-group">
                    <label>نام پنل:</label>
                    <input type="text" name="name" id="panelName" required>
                </div>
                <div class="form-group">
                    <label>نوع پنل:</label>
                    <select name="type" id="panelType" required>
                        <option value="">انتخاب کنید...</option>
                        <option value="marzban">Marzban</option>
                        <option value="x-ui">X-UI</option>
                        <option value="hiddify">Hiddify</option>
                        <option value="v2board">V2board</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>آدرس URL:</label>
                    <input type="url" name="url" id="panelUrl" placeholder="https://example.com" required>
                </div>
                <div class="form-group">
                    <label>نام کاربری:</label>
                    <input type="text" name="username" id="panelUsername" required>
                </div>
                <div class="form-group">
                    <label>رمز عبور:</label>
                    <input type="password" name="password" id="panelPassword" required>
                </div>
                <div class="form-group">
                    <label>لوکیشن:</label>
                    <input type="text" name="location" id="panelLocation" placeholder="مثال: آلمان، فرانکفورت">
                </div>
                <div class="form-group">
                    <label>پروتکل‌های پشتیبانی شده:</label>
                    <div class="protocol-selector">
                        <div class="protocol-option">
                            <input type="checkbox" name="protocols[]" value="vmess" id="proto_vmess">
                            <label for="proto_vmess">VMess</label>
                        </div>
                        <div class="protocol-option">
                            <input type="checkbox" name="protocols[]" value="vless" id="proto_vless">
                            <label for="proto_vless">VLESS</label>
                        </div>
                        <div class="protocol-option">
                            <input type="checkbox" name="protocols[]" value="trojan" id="proto_trojan">
                            <label for="proto_trojan">Trojan</label>
                        </div>
                        <div class="protocol-option">
                            <input type="checkbox" name="protocols[]" value="shadowsocks" id="proto_ss">
                            <label for="proto_ss">Shadowsocks</label>
                        </div>
                    </div>
                </div>
                <div id="testResult" class="test-result"></div>
                <div class="panel-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-info" onclick="testConnectionFromForm()">🔗 تست اتصال</button>
                    <button type="submit" class="btn btn-success">💾 ذخیره</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('panelModal')">❌ انصراف</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="/webpanel/assets/js/main.js"></script>
    <script>
        function showAddPanel() {
            document.getElementById('modalTitle').textContent = 'افزودن پنل جدید';
            document.getElementById('panelForm').reset();
            document.getElementById('panelId').value = '';
            document.getElementById('testResult').style.display = 'none';
            document.getElementById('panelModal').classList.add('active');
        }
        
        function editPanel(id) {
            fetch(`/webpanel/api/get_panel.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const panel = data.panel;
                        document.getElementById('modalTitle').textContent = 'ویرایش پنل';
                        document.getElementById('panelId').value = panel.id;
                        document.getElementById('panelName').value = panel.name;
                        document.getElementById('panelType').value = panel.type;
                        document.getElementById('panelUrl').value = panel.url;
                        document.getElementById('panelUsername').value = panel.username;
                        document.getElementById('panelLocation').value = panel.location || '';
                        
                        // Set protocols
                        const protocols = panel.protocols ? panel.protocols.split(',') : [];
                        document.querySelectorAll('input[name="protocols[]"]').forEach(cb => {
                            cb.checked = protocols.includes(cb.value);
                        });
                        
                        document.getElementById('panelModal').classList.add('active');
                    }
                });
        }
        
        function deletePanel(id) {
            if (!confirm('آیا از حذف این پنل اطمینان دارید؟ این عمل غیرقابل بازگشت است!')) return;
            
            document.getElementById(`loading-${id}`).classList.add('active');
            
            fetch('/webpanel/api/delete_panel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ panel_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('پنل با موفقیت حذف شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                    document.getElementById(`loading-${id}`).classList.remove('active');
                }
            });
        }
        
        function testConnection(id) {
            document.getElementById(`loading-${id}`).classList.add('active');
            
            fetch('/webpanel/api/test_panel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ panel_id: id })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById(`loading-${id}`).classList.remove('active');
                if (data.success) {
                    alert('✅ اتصال موفقیت‌آمیز بود!');
                } else {
                    alert('❌ اتصال ناموفق: ' + data.message);
                }
            });
        }
        
        function testConnectionFromForm() {
            const formData = new FormData(document.getElementById('panelForm'));
            const testResult = document.getElementById('testResult');
            testResult.style.display = 'none';
            
            fetch('/webpanel/api/test_panel_connection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                testResult.className = 'test-result ' + (data.success ? 'success' : 'error');
                testResult.textContent = data.message;
                testResult.style.display = 'block';
            });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Handle form submission
        document.getElementById('panelForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            const endpoint = formData.get('panel_id') ? 
                '/webpanel/api/update_panel.php' : 
                '/webpanel/api/add_panel.php';
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('پنل با موفقیت ذخیره شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>
