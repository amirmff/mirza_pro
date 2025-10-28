<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bot_core.php';

$auth = new Auth();
$auth->requireLogin();

$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); exit('Forbidden'); }

// Filters
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';

// Fetch users
$page = (int)($_GET['page'] ?? 1);
$page = max($page, 1);
$limit = 20;
$offset = ($page - 1) * $limit;

global $pdo;
$where = [];$params = [];
if ($search !== '') { $where[] = '(id LIKE :s OR username LIKE :s OR number LIKE :s)'; $params[':s'] = "%$search%"; }
if ($status !== 'all') { $where[] = 'User_Status = :st'; $params[':st'] = $status; }
$whereClause = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user $whereClause");
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->execute();
$total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

$sql = "SELECT id, username, Balance, User_Status, register FROM user $whereClause ORDER BY register DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pages = (int)ceil($total / $limit);
$csrf = $auth->getCsrfToken();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h1>مدیریت کاربران</h1>
                <div class="topbar-actions">
                    <button class="btn-icon" onclick="location.reload()">🔄</button>
                </div>
            </div>
            
            <div class="content-area">
                <div class="section">
                    <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                        <div style="display:flex;gap:10px;align-items:center;">
                            <input type="text" id="q" placeholder="جستجو" value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                            <select id="st" class="form-control">
                                <option value="all" <?php echo $status==='all'?'selected':''; ?>>همه</option>
                                <option value="Active" <?php echo $status==='Active'?'selected':''; ?>>فعال</option>
                                <option value="block" <?php echo $status==='block'?'selected':''; ?>>مسدود</option>
                            </select>
                            <button class="btn-sm" onclick="applyFilter()">اعمال</button>
                        </div>
                        <span class="badge"><?php echo number_format($total); ?> کاربر</span>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>نام کاربری</th>
                                    <th>موجودی</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ ثبت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($user['Balance'] ?? 0); ?> تومان</td>
                                    <td>
                                        <span class="badge <?php echo (strtolower($user['User_Status'] ?? '') == 'active') ? 'success' : 'warning'; ?>">
                                            <?php echo htmlspecialchars($user['User_Status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['register'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a class="btn-sm" href="/webpanel/user_detail.php?id=<?php echo $user['id']; ?>">مشاهده</a>
                                        <?php if (strtolower($user['User_Status'] ?? '') == 'active'): ?>
                                            <button class="btn-sm danger" onclick="userAction('block', <?php echo $user['id']; ?>)">مسدود</button>
                                        <?php else: ?>
                                            <button class="btn-sm success" onclick="userAction('unblock', <?php echo $user['id']; ?>)">رفع مسدودی</button>
                                        <?php endif; ?>
                                        <button class="btn-sm" onclick="quickMessage(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')">پیام</button>
                                        <?php if (($user['agent'] ?? 'f') === 'f'): ?>
                                            <button class="btn-sm" onclick="setAgent(<?php echo $user['id']; ?>, 's')">نماینده</button>
                                        <?php else: ?>
                                            <button class="btn-sm" onclick="clearAgent(<?php echo $user['id']; ?>)">حذف نمایندگی</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i=1;$i<=$pages;$i++): ?>
                            <a href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="<?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function applyFilter(){
            const q = document.getElementById('q').value;
            const st = document.getElementById('st').value;
            const url = new URL(window.location.href);
            url.searchParams.set('q', q);
            url.searchParams.set('status', st);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        function userAction(action, user_id){
            const params = new URLSearchParams();
            params.append('action', action);
            params.append('user_id', user_id);
            params.append('csrf_token', '<?php echo $csrf; ?>');
            fetch('/webpanel/api/user_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r=>r.json()).then(d=>{ alert(d.success?'انجام شد':('خطا: '+(d.message||''))); if(d.success) location.reload(); });
        }
        function quickMessage(user_id, uname){
            const text = prompt('پیام به '+(uname||user_id));
            if (!text) return;
            const params = new URLSearchParams();
            params.append('action','message');
            params.append('user_id', user_id);
            params.append('text', text);
            params.append('csrf_token', '<?php echo $csrf; ?>');
            fetch('/webpanel/api/user_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r=>r.json()).then(d=>{ alert(d.success?'ارسال شد':('خطا: '+(d.message||''))); });
        }
        function setAgent(user_id, val){
            const params = new URLSearchParams({action:'agent_set', user_id, agent_value: val, csrf_token:'<?php echo $csrf; ?>'});
            fetch('/webpanel/api/user_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r=>r.json()).then(d=>{ alert(d.success?'ثبت شد':('خطا: '+(d.message||''))); if(d.success) location.reload(); });
        }
        function clearAgent(user_id){
            const params = new URLSearchParams({action:'agent_clear', user_id, csrf_token:'<?php echo $csrf; ?>'});
            fetch('/webpanel/api/user_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r=>r.json()).then(d=>{ alert(d.success?'ثبت شد':('خطا: '+(d.message||''))); if(d.success) location.reload(); });
        }
    </script>
</body>
</html>
