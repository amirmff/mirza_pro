<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }
$csrf = $auth->getCsrfToken();

$user_id = (int)($_GET['user_id'] ?? 0);
$used = 0;$limit = 0;$username = '';
if ($user_id > 0) {
  $stmt = $pdo->prepare("SELECT username, limit_usertest FROM user WHERE id = :id");
  $stmt->execute([':id'=>$user_id]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($u) { $limit = (int)($u['limit_usertest'] ?? 0); $username = $u['username'] ?? ''; }
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = :id AND name_product = 'سرویس تست'");
  $stmt->execute([':id'=>$user_id]);
  $used = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>سهمیه سرویس تست - Mirza Pro</title>
  <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
<div class="dashboard-container">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="topbar"><h1>🧪 سهمیه سرویس تست</h1></div>
    <div class="content-area">
      <div class="section">
        <form method="GET" class="card" style="padding:20px;display:flex;gap:10px;">
          <input type="number" name="user_id" placeholder="ID کاربر" value="<?php echo htmlspecialchars((string)$user_id); ?>" class="form-control">
          <button class="btn">جستجو</button>
        </form>
      </div>
      <?php if ($user_id > 0): ?>
      <div class="section">
        <div class="card" style="padding:20px;">
          <p>کاربر: <strong><?php echo htmlspecialchars($username ?: (string)$user_id); ?></strong></p>
          <p>تعداد استفاده شده: <strong><?php echo $used; ?></strong></p>
          <p>حد مجاز فعلی: <strong><?php echo $limit; ?></strong></p>
          <form id="quotaForm" style="margin-top:10px;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <label>حد مجاز جدید:</label>
            <input type="number" name="limit_usertest" value="<?php echo $limit; ?>" class="form-control" style="max-width:200px;">
            <button type="submit" class="btn btn-success" style="margin-top:10px;">ذخیره</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script>
  document.getElementById('quotaForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('/webpanel/api/test_quota_update.php', {method:'POST', body: fd})
      .then(r=>r.json()).then(d=>{ alert(d.success?'ذخیره شد':'خطا'); if(d.success) location.reload(); });
  });
</script>
</body>
</html>
