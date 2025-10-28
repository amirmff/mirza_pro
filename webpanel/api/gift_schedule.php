<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$admin = $auth->getCurrentAdmin();
if (($admin['rule'] ?? '') !== 'administrator') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }
if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['success'=>false]); exit; }

$type = $_POST['type'] ?? 'volume'; // volume|time
$name_panel = trim($_POST['name_panel'] ?? '');
$value = (int)($_POST['value'] ?? 0);
$text = trim($_POST['text'] ?? '');
$usernames = trim($_POST['usernames'] ?? '');

if ($name_panel === '' || $value <= 0 || $usernames === '') { echo json_encode(['success'=>false,'message'=>'invalid params']); exit; }

$users_arr = array_filter(array_map('trim', preg_split('/[\s,\n]+/', $usernames)));
$payload_users = [];
foreach ($users_arr as $u) { $payload_users[] = ['username'=>$u]; }

$gift_info = [
  'typegift' => $type,
  'name_panel' => $name_panel,
  'value' => $value,
  'text' => $text,
  'id_admin' => $admin['id_admin'] ?? 0,
  'id_message' => 0
];

$dir = realpath(__DIR__ . '/../../cronbot');
if (!$dir) { echo json_encode(['success'=>false,'message'=>'cronbot not found']); exit; }

file_put_contents($dir . '/gift', json_encode($gift_info, JSON_UNESCAPED_UNICODE));
file_put_contents($dir . '/username.json', json_encode($payload_users, JSON_UNESCAPED_UNICODE));

echo json_encode(['success'=>true]);
