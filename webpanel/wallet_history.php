<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config.php';

$auth = new Auth();
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }

// Filters
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$page = (int)($_GET['page'] ?? 1); $page = max($page,1);
$limit = 50; $offset = ($page-1)*$limit;

$where = [];$params=[];
if ($q !== '') { $where[] = '(u.username LIKE :q OR p.id_user = :uid)'; $params[':q']="%$q%"; if(ctype_digit($q)) $params[':uid']=(int)$q; else $params[':uid']=-1; }
if ($status !== 'all') { $where[] = 'p.payment_Status = :st'; $params[':st']=$status; }
$whereClause = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$sqlCount = "SELECT COUNT(*) FROM Payment_report p LEFT JOIN user u ON u.id = p.id_user $whereClause";
$stmt = $pdo->prepare($sqlCount);
foreach($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->execute();
$total = (int)$stmt->fetchColumn();
$pages = (int)ceil($total/$limit);

$sql = "SELECT p.*, u.username FROM Payment_report p LEFT JOIN user u ON u.id = p.id_user $whereClause ORDER BY p.id DESC LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$limit,PDO::PARAM_INT);
$stmt->bindValue(':off',$offset,PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>تاریخچه کیف پول - Mirza Pro</title>
  <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
<div class="dashboard-container">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="topbar"><h1>💳 تاریخچه کیف پول</h1></div>
    <div class="content-area">
      <div class="section">
        <form method="GET" class="card" style="padding:20px;display:flex;gap:10px;flex-wrap:wrap;">
          <input type="text" name="q" class="form-control" placeholder="نام کاربری یا ID کاربر" value="<?php echo htmlspecialchars($q); ?>">
          <select name="status" class="form-control">
            <option value="all" <?php echo $status==='all'?'selected':''; ?>>همه</option>
            <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>در انتظار</option>
            <option value="paid" <?php echo $status==='paid'?'selected':''; ?>>پرداخت شده</option>
            <option value="completed" <?php echo $status==='completed'?'selected':''; ?>>تکمیل شده</option>
            <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>رد شده</option>
          </select>
          <button class="btn">اعمال</button>
        </form>
      </div>
      <div class="section">
        <div class="table-container">
          <table class="data-table">
            <thead><tr><th>ID</th><th>کاربر</th><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>زمان</th></tr></thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><?php echo htmlspecialchars($r['username'] ?? ''); ?> (<?php echo (int)$r['id_user']; ?>)</td>
                  <td><?php echo number_format((int)$r['price']); ?> تومان</td>
                  <td><?php echo htmlspecialchars($r['Payment_Method'] ?? ''); ?></td>
                  <td><span class="badge <?php echo in_array($r['payment_Status'],['paid','completed'])?'success':($r['payment_Status']==='rejected'?'danger':'warning'); ?>"><?php echo htmlspecialchars($r['payment_Status']); ?></span></td>
                  <td><?php echo htmlspecialchars($r['time'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages>1): ?>
          <div class="pagination">
            <?php for($i=1;$i<=$pages;$i++): ?>
              <a href="?q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&page=<?php echo $i; ?>" class="<?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
</body>
</html>
