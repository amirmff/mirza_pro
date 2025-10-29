<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
$admin = $auth->getCurrentAdmin();
if (($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false]); exit; }
}

try {
  if ($action === 'list') {
    $stmt = $pdo->query("SELECT id_text, text FROM textbot ORDER BY id_text");
    echo json_encode(['success'=>true, 'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
  }
  if ($action === 'update') {
    $id = $_POST['id_text'] ?? '';
    $text = $_POST['text'] ?? '';
    if ($id === '') { echo json_encode(['success'=>false]); exit; }
    $stmt = $pdo->prepare("UPDATE textbot SET text = :text WHERE id_text = :id");
    $stmt->execute([':text'=>$text, ':id'=>$id]);
    echo json_encode(['success'=>true]);
    exit;
  }
  echo json_encode(['success'=>false]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
