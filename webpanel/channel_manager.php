<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }
$csrf = $auth->getCsrfToken();

// Fetch channels
$stmt = $pdo->query("SELECT * FROM channels ORDER BY remark");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>مدیریت کانال‌های اجباری - Mirza Pro</title>
  <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
<div class="dashboard-container">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="topbar">
      <h1>مدیریت کانال‌های اجباری</h1>
    </div>
    <div class="content-area">
      <div class="section">
        <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
          <h2>کانال‌ها</h2>
          <button class="btn" onclick="addChannel()">➕ افزودن کانال</button>
        </div>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>نام</th>
                <th>لینک جوین</th>
                <th>آدرس</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($channels as $ch): ?>
              <tr>
                <td><?php echo htmlspecialchars($ch['remark']); ?></td>
                <td><?php echo htmlspecialchars($ch['linkjoin']); ?></td>
                <td><?php echo htmlspecialchars($ch['link']); ?></td>
                <td>
                  <button class="btn-sm danger" onclick="deleteChannel('<?php echo htmlspecialchars($ch['remark']); ?>')">حذف</button>
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
  const csrf = '<?php echo $csrf; ?>';
  function addChannel(){
    const remark = prompt('نام کانال (نمایشی)'); if(!remark) return;
    const linkjoin = prompt('لینک جوین (t.me/...)'); if(!linkjoin) return;
    const link = prompt('آدرس کانال یا یکتا');
    const body = new URLSearchParams({action:'create', csrf_token: csrf, remark, linkjoin, link});
    fetch('/webpanel/api/channel_crud.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
      .then(r=>r.json()).then(d=>{ alert(d.success?'ثبت شد':('خطا: '+(d.message||''))); if(d.success) location.reload(); });
  }
  function deleteChannel(remark){
    if(!confirm('حذف شود؟')) return;
    const body = new URLSearchParams({action:'delete', csrf_token: csrf, remark});
    fetch('/webpanel/api/channel_crud.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
      .then(r=>r.json()).then(d=>{ alert(d.success?'حذف شد':('خطا: '+(d.message||''))); if(d.success) location.reload(); });
  }
</script>
</body>
</html>
