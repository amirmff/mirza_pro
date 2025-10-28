<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$admin = $auth->getCurrentAdmin();
if (!$admin || ($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit; }
}

try {
  if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM channels ORDER BY remark");
    echo json_encode(['success'=>true,'channels'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
  }
  if ($action === 'create') {
    $data = [
      ':remark' => $_POST['remark'] ?? '',
      ':linkjoin' => $_POST['linkjoin'] ?? '',
      ':link' => $_POST['link'] ?? ''
    ];
    $stmt = $pdo->prepare("INSERT INTO channels (remark, linkjoin, link) VALUES (:remark,:linkjoin,:link)");
    $stmt->execute($data);
    echo json_encode(['success'=>true]);
    exit;
  }
  if ($action === 'delete') {
    $remark = $_POST['remark'] ?? '';
    if ($remark === '') throw new Exception('Invalid remark');
    $stmt = $pdo->prepare("DELETE FROM channels WHERE remark = :remark");
    $stmt->execute([':remark'=>$remark]);
    echo json_encode(['success'=>true]);
    exit;
  }
  echo json_encode(['success'=>false,'message'=>'Invalid action']);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
