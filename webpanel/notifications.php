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
  <title>اعلان‌ها و گزارش‌ها - Mirza Pro</title>
  <link rel="stylesheet" href="/webpanel/assets/css/style.css">
  <style>
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 900px){ .grid{ grid-template-columns: 1fr; } }
    .mono { font-family: monospace; font-size: 12px; white-space: pre-wrap; }
  </style>
</head>
<body>
<div class="dashboard-container">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="topbar"><h1>🔔 اعلان‌ها و گزارش‌ها</h1></div>
    <div class="content-area">
      <div class="grid">
        <div class="card">
          <h3>تنظیم مقاصد اعلان</h3>
          <form id="addDest">
            <div class="form-group">
              <label>دسته</label>
              <select name="category" class="form-control" required>
                <option value="payments">پرداخت‌ها</option>
                <option value="services">سرویس‌ها</option>
                <option value="system">سیستم</option>
                <option value="security">امنیت</option>
              </select>
            </div>
            <div class="form-group">
              <label>Chat ID (کانال/گروه)</label>
              <input name="chat_id" class="form-control" placeholder="مثال: -1001234567890" required>
            </div>
            <div class="form-group">
              <label>Topic ID (اختیاری - برای انجمن‌ها)</label>
              <input name="topic_id" class="form-control" placeholder="شناسه تاپیک (اختیاری)">
            </div>
            <div class="action-buttons">
              <button class="btn btn-success" type="submit">➕ افزودن مقصد</button>
              <button class="btn btn-secondary" type="button" onclick="loadDest()">🔄 بروزرسانی</button>
            </div>
          </form>
          <div style="margin-top:15px">
            <table class="data-table" id="destTable">
              <thead><tr><th>ID</th><th>دسته</th><th>Chat</th><th>Topic</th><th>فعال</th><th>عملیات</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="card">
          <h3>ارسال آزمایشی</h3>
          <form id="testForm">
            <div class="form-group">
              <label>Chat ID</label>
              <input name="chat_id" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Topic ID (اختیاری)</label>
              <input name="topic_id" class="form-control">
            </div>
            <div class="form-group">
              <label>متن پیام</label>
              <textarea name="text" class="form-control" rows="5">پیام تستی از پنل مدیریت Mirza Pro</textarea>
            </div>
            <button class="btn">ارسال تست</button>
          </form>
          <div id="testResult" class="mono" style="margin-top:10px;color:#666;"></div>
        </div>
      </div>

      <div class="section">
        <div class="card">
          <div style="display:flex;gap:10px;align-items:center;">
            <h3 style="margin:0">گزارش ارسال اعلان‌ها</h3>
            <select id="logCat" class="form-control" style="max-width:200px">
              <option value="">همه</option>
              <option value="payments">پرداخت‌ها</option>
              <option value="services">سرویس‌ها</option>
              <option value="system">سیستم</option>
              <option value="security">امنیت</option>
            </select>
            <button class="btn btn-secondary" onclick="loadLogs()">بروزرسانی</button>
          </div>
          <div style="margin-top:10px">
            <table class="data-table" id="logTable">
              <thead><tr><th>ID</th><th>زمان</th><th>دسته</th><th>Chat</th><th>Topic</th><th>وضعیت</th><th>متن</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script>
const CSRF = '<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>';

function loadDest(){
  fetch('/webpanel/api/notifications.php?action=list').then(r=>r.json()).then(d=>{
    const tb = document.querySelector('#destTable tbody');
    tb.innerHTML = '';
    (d.items||[]).forEach(row=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.id}</td>
        <td>${row.category}</td>
        <td>${row.chat_id}</td>
        <td>${row.topic_id ?? ''}</td>
        <td>${row.enabled ? '✅' : '❌'}</td>
        <td>
          <button class="btn-sm" onclick="toggleDest(${row.id}, ${row.enabled?0:1})">${row.enabled?'غیرفعال':'فعال'}</button>
          <button class="btn-sm danger" onclick="delDest(${row.id})">حذف</button>
        </td>`;
      tb.appendChild(tr);
    });
  });
}
function toggleDest(id, enabled){
  const body = new URLSearchParams({action:'update', id, enabled, csrf_token:CSRF});
  fetch('/webpanel/api/notifications.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(()=>loadDest());
}
function delDest(id){
  if(!confirm('حذف مقصد؟')) return;
  const body = new URLSearchParams({action:'delete', id, csrf_token:CSRF});
  fetch('/webpanel/api/notifications.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(()=>loadDest());
}

document.getElementById('addDest').addEventListener('submit', e=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action','create'); fd.append('csrf_token',CSRF);
  fetch('/webpanel/api/notifications.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){ alert('ثبت شد'); e.target.reset(); loadDest(); } else { alert('خطا: '+(d.message||'')); }
  });
});

document.getElementById('testForm').addEventListener('submit', e=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action','test_send'); fd.append('csrf_token',CSRF);
  fetch('/webpanel/api/notifications.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    document.getElementById('testResult').textContent = d.success ? 'ارسال شد' : 'ناموفق';
  });
});

function loadLogs(){
  const cat = document.getElementById('logCat').value;
  const url = '/webpanel/api/notifications.php?action=logs'+(cat?('&category='+encodeURIComponent(cat)):'');
  fetch(url).then(r=>r.json()).then(d=>{
    const tb = document.querySelector('#logTable tbody');
    tb.innerHTML = '';
    (d.items||[]).forEach(row=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.id}</td>
        <td>${row.created_at}</td>
        <td>${row.category}</td>
        <td>${row.chat_id ?? ''}</td>
        <td>${row.topic_id ?? ''}</td>
        <td>${row.status}</td>
        <td class="mono" title="${(row.text||'').replace(/"/g,'&quot;')}">${(row.text||'').slice(0,120)}</td>`;
      tb.appendChild(tr);
    });
  });
}

window.addEventListener('DOMContentLoaded', ()=>{ loadDest(); loadLogs(); });
</script>
</body>
</html>
