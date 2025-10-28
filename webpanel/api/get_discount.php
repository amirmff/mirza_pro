<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
$admin = $auth->getCurrentAdmin();
if (($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false]); exit; }

$stmt = $pdo->prepare("SELECT * FROM Discount WHERE id = :id");
$stmt->execute([':id'=>$id]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$d) { echo json_encode(['success'=>false]); exit; }

echo json_encode(['success'=>true,'discount'=>[
  'id'=>(int)$d['id'],
  'code'=>$d['code'],
  'type'=>'fixed',
  'value'=>(int)$d['price'],
  'max_uses'=>(int)$d['limituse'],
  'used'=>(int)$d['limitused'],
  'expires_at'=>null,
  'description'=>''
]]);
