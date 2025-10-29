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

$user_id = (int)($_POST['user_id'] ?? 0);
$limit = (int)($_POST['limit_usertest'] ?? 0);
if ($user_id <= 0) { echo json_encode(['success'=>false]); exit; }

$stmt = $pdo->prepare('UPDATE user SET limit_usertest = :lim WHERE id = :id');
$stmt->execute([':lim'=>$limit, ':id'=>$user_id]);

echo json_encode(['success'=>true]);
