<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }

$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50; $offset = ($page-1)*$limit;

$where = [];$params=[];
if ($q !== '') { $where[] = '(a.username LIKE :q OR l.ip_address LIKE :q2)'; $params[':q']="%$q%"; $params[':q2']="%$q%"; }
if ($action !== '') { $where[] = 'l.action = :act'; $params[':act']=$action; }
$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$sqlCount = "SELECT COUNT(*) FROM admin_logs l LEFT JOIN admin a ON a.id_admin = l.admin_id $whereSql";
$stmt = $pdo->prepare($sqlCount); foreach($params as $k=>$v) $stmt->bindValue($k,$v); $stmt->execute();
$total = (int)$stmt->fetchColumn(); $pages = (int)ceil($total/$limit);

$sql = "SELECT l.*, a.username FROM admin_logs l LEFT JOIN admin a ON a.id_admin = l.admin_id $whereSql ORDER BY l.id DESC LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql); foreach($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$limit,PDO::PARAM_INT); $stmt->bindValue(':off',$offset,PDO::PARAM_INT); $stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>گزارش فعالیت ادمین‌ها - Mirza Pro</title>
  <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
<div class="dashboard-container">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="topbar"><h1>📜 گزارش فعالیت ادمین‌ها</h1></div>
    <div class="content-area">
      <div class="section">
        <form method="GET" class="card" style="padding:20px;display:flex;gap:10px;flex-wrap:wrap;">
          <input type="text" name="q" class="form-control" placeholder="نام ادمین یا IP" value="<?php echo htmlspecialchars($q); ?>">
          <input type="text" name="action" class="form-control" placeholder="اکشن (مثلاً service_delete)" value="<?php echo htmlspecialchars($action); ?>">
          <button class="btn">اعمال</button>
        </form>
      </div>
      <div class="section">
        <div class="table-container">
          <table class="data-table">
            <thead><tr><th>ID</th><th>ادمین</th><th>اکشن</th><th>توضیح</th><th>IP</th><th>زمان</th></tr></thead>
            <tbody>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><?php echo htmlspecialchars($r['username'] ?? ''); ?> (#<?php echo (int)$r['admin_id']; ?>)</td>
                  <td><code><?php echo htmlspecialchars($r['action']); ?></code></td>
                  <td style="max-width:500px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($r['description'] ?? ''); ?>"><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($r['ip_address'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($r['created_at'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages>1): ?>
          <div class="pagination">
            <?php for($i=1;$i<=$pages;$i++): ?>
              <a href="?q=<?php echo urlencode($q); ?>&action=<?php echo urlencode($action); ?>&page=<?php echo $i; ?>" class="<?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
</body>
</html>
