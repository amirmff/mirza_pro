<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false]); exit; }
$admin = $auth->getCurrentAdmin();
if (($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); echo json_encode(['success'=>false]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }
if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$id = (int)($_POST['discount_id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false]); exit; }

$code = trim($_POST['code'] ?? '');
$price = (int)($_POST['value'] ?? 0);
$max = (int)($_POST['max_uses'] ?? 0);

$sets = [];$params = [':id'=>$id];
if ($code !== '') { $sets[]='code = :code'; $params[':code']=$code; }
$sets[]='price = :price'; $params[':price']=$price;
$sets[]='limituse = :limituse'; $params[':limituse']=$max;
$sql = 'UPDATE Discount SET '.implode(',', $sets).' WHERE id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success'=>true]);
