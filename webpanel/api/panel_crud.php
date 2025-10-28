<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin = $auth->getCurrentAdmin();
if (($admin['rule'] ?? '') !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($method === 'POST') {
    if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT * FROM marzban_panel ORDER BY name_panel");
        echo json_encode(['success' => true, 'panels' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'create') {
        $data = [
            'name_panel' => $_POST['name_panel'] ?? '',
            'type' => $_POST['type'] ?? 'marzban',
            'url_panel' => $_POST['url_panel'] ?? '',
            'username_panel' => $_POST['username_panel'] ?? null,
            'password_panel' => $_POST['password_panel'] ?? null,
            'inboundid' => $_POST['inboundid'] ?? null,
            'linksubx' => $_POST['linksubx'] ?? null,
        ];
        $sql = "INSERT INTO marzban_panel (name_panel, type, url_panel, username_panel, password_panel, inboundid, linksubx) VALUES (:name_panel,:type,:url_panel,:username_panel,:password_panel,:inboundid,:linksubx)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid id');
        $fields = ['name_panel','type','url_panel','username_panel','password_panel','inboundid','linksubx'];
        $sets = [];$params = [':id'=>$id];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) { $sets[] = "$f = :$f"; $params[":$f"] = $_POST[$f]; }
        }
        if (!$sets) throw new Exception('No fields');
        $sql = "UPDATE marzban_panel SET ".implode(',', $sets)." WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid id');
        $stmt = $pdo->prepare("DELETE FROM marzban_panel WHERE id = :id");
        $stmt->execute([':id'=>$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
