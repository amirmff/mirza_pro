<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }
$csrf = $auth->getCsrfToken();

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>مدیریت متن‌ها (textbot) - Mirza Pro</title>
  <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
<div class="dashboard-container">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="topbar"><h1>📝 مدیریت متن‌های ربات</h1></div>
    <div class="content-area">
      <div class="table-container">
        <table class="data-table" id="textsTable">
          <thead>
            <tr><th>کلید</th><th>متن</th><th>عملیات</th></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script>
  const csrf = '<?php echo $csrf; ?>';
  function loadTexts(){
    fetch('/webpanel/api/textbot_crud.php?action=list')
      .then(r=>r.json()).then(d=>{
        if(!d.success) return;
        const tbody = document.querySelector('#textsTable tbody');
        tbody.innerHTML = '';
        d.items.forEach(it=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td><code>${it.id_text}</code></td>
            <td><textarea data-id="${it.id_text}" style="width:100%;min-height:80px;">${it.text.replaceAll('<','&lt;')}</textarea></td>
            <td><button class="btn" onclick="save('${it.id_text}')">ذخیره</button></td>
          `;
          tbody.appendChild(tr);
        });
      });
  }
  function save(id){
    const ta = document.querySelector(`textarea[data-id="${id}"]`);
    const body = new URLSearchParams({action:'update', csrf_token: csrf, id_text:id, text: ta.value});
    fetch('/webpanel/api/textbot_crud.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
      .then(r=>r.json()).then(d=>{ alert(d.success?'ذخیره شد':'خطا'); });
  }
  loadTexts();
</script>
</body>
</html>
