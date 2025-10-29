<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/bot_core.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }
}

try {
  switch ($action) {
    case 'list':
      $stmt = $pdo->query("SELECT * FROM notifications_channels ORDER BY category, created_at DESC");
      echo json_encode(['success'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
      break;

    case 'logs':
      $cat = $_GET['category'] ?? '';
      $where = '';$params=[];
      if ($cat !== '') { $where = 'WHERE category = :c'; $params[':c']=$cat; }
      $stmt = $pdo->prepare("SELECT * FROM notifications_log $where ORDER BY id DESC LIMIT 200");
      $stmt->execute($params);
      echo json_encode(['success'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
      break;

    case 'create':
      $category = trim($_POST['category'] ?? '');
      $chat_id = (int)($_POST['chat_id'] ?? 0);
      $topic_id = isset($_POST['topic_id']) && $_POST['topic_id'] !== '' ? (int)$_POST['topic_id'] : null;
      if ($category === '' || $chat_id === 0) { echo json_encode(['success'=>false,'message'=>'Invalid params']); break; }
      $stmt = $pdo->prepare("INSERT INTO notifications_channels (category, chat_id, topic_id, enabled) VALUES (:c,:ch,:t,1)");
      $stmt->execute([':c'=>$category,':ch'=>$chat_id,':t'=>$topic_id]);
      echo json_encode(['success'=>true]);
      break;

    case 'update':
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) { echo json_encode(['success'=>false]); break; }
      $fields = ['category','chat_id','topic_id','enabled'];
      $sets=[];$params=[':id'=>$id];
      foreach ($fields as $f) {
        if (isset($_POST[$f])) { $sets[]="$f = :$f"; $params[":$f"] = $_POST[$f]; }
      }
      if (!$sets) { echo json_encode(['success'=>false]); break; }
      $sql = 'UPDATE notifications_channels SET '.implode(',', $sets).' WHERE id = :id';
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      echo json_encode(['success'=>true]);
      break;

    case 'delete':
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) { echo json_encode(['success'=>false]); break; }
      $stmt = $pdo->prepare('DELETE FROM notifications_channels WHERE id = :id');
      $stmt->execute([':id'=>$id]);
      echo json_encode(['success'=>true]);
      break;

    case 'test_send':
      $chat_id = (int)($_POST['chat_id'] ?? 0);
      $topic_id = isset($_POST['topic_id']) && $_POST['topic_id'] !== '' ? (int)$_POST['topic_id'] : null;
      $text = $_POST['text'] ?? 'Test notification from Mirza Pro Panel';
      if ($chat_id === 0) { echo json_encode(['success'=>false]); break; }
      $ok = sendTelegramToChat($chat_id, $text, $topic_id);
      echo json_encode(['success'=>$ok]);
      break;

    default:
      echo json_encode(['success'=>false,'message'=>'Invalid action']);
  }
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
