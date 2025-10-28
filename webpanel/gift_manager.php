<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }
$csrf = $auth->getCsrfToken();

// Panels for selection
$stmt = $pdo->query("SELECT name_panel FROM marzban_panel ORDER BY name_panel");
$panels = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>هدیه گروهی - Mirza Pro</title>
  <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
<div class="dashboard-container">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="topbar"><h1>🎁 هدیه گروهی (حجم/زمان)</h1></div>
    <div class="content-area">
      <div class="section">
        <form id="giftForm" class="card" style="padding:20px;">
          <div class="form-group">
            <label>نوع هدیه:</label>
            <select id="type" class="form-control">
              <option value="volume">حجم (GB)</option>
              <option value="time">زمان (روز)</option>
            </select>
          </div>
          <div class="form-group">
            <label>پنل:</label>
            <select id="name_panel" class="form-control">
              <?php foreach ($panels as $p): ?>
                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>مقدار:</label>
            <input type="number" id="value" class="form-control" min="1" required>
          </div>
          <div class="form-group">
            <label>متن پیام به کاربر (اختیاری):</label>
            <textarea id="text" class="form-control" rows="3" placeholder="متن پیام برای اطلاع رسانی"></textarea>
          </div>
          <div class="form-group">
            <label>لیست نام کاربری سرویس‌ها (username) - با کاما یا خط جدید جدا کنید:</label>
            <textarea id="usernames" class="form-control" rows="6" placeholder="user1\nuser2\nuser3" required></textarea>
          </div>
          <button type="submit" class="btn btn-success">ثبت هدیه</button>
        </form>
      </div>
    </div>
  </main>
</div>
<script>
  const csrf = '<?php echo $csrf; ?>';
  document.getElementById('giftForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
      csrf_token: csrf,
      type: document.getElementById('type').value,
      name_panel: document.getElementById('name_panel').value,
      value: document.getElementById('value').value,
      text: document.getElementById('text').value,
      usernames: document.getElementById('usernames').value
    });
    fetch('/webpanel/api/gift_schedule.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
      .then(r=>r.json()).then(d=>{ alert(d.success?'زمان‌بندی شد (cronbot/gift)':'خطا: '+(d.message||'')); });
  });
</script>
</body>
</html>
