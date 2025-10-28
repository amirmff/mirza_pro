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

$code = trim($_POST['code'] ?? '');
$price = (int)($_POST['value'] ?? 0);
$max = (int)($_POST['max_uses'] ?? 0);
if ($code === '') { echo json_encode(['success'=>false,'message'=>'invalid code']); exit; }

$stmt = $pdo->prepare("INSERT INTO Discount (code, price, limituse, limitused) VALUES (:code, :price, :limituse, 0)");
$stmt->execute([':code'=>$code, ':price'=>$price, ':limituse'=>$max]);

echo json_encode(['success'=>true]);
