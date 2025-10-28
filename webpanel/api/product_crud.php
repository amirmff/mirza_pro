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
        $stmt = $pdo->query("SELECT * FROM product ORDER BY Location, name_product");
        echo json_encode(['success' => true, 'products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'create') {
        $data = [
            'code_product' => $_POST['code_product'] ?? bin2hex(random_bytes(4)),
            'name_product' => $_POST['name_product'] ?? '',
            'Location' => $_POST['Location'] ?? '/all',
            'Volume_constraint' => $_POST['Volume_constraint'] ?? 0,
            'Service_time' => $_POST['Service_time'] ?? 0,
            'price_product' => $_POST['price_product'] ?? 0,
        ];
        $sql = "INSERT INTO product (code_product, name_product, Location, Volume_constraint, Service_time, price_product) VALUES (:code_product,:name_product,:Location,:Volume_constraint,:Service_time,:price_product)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'update') {
        $code = $_POST['code_product'] ?? '';
        if ($code === '') throw new Exception('Invalid code_product');
        $fields = ['name_product','Location','Volume_constraint','Service_time','price_product'];
        $sets = [];$params = [':code_product'=>$code];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) { $sets[] = "$f = :$f"; $params[":$f"] = $_POST[$f]; }
        }
        if (!$sets) throw new Exception('No fields');
        $sql = "UPDATE product SET ".implode(',', $sets)." WHERE code_product = :code_product";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $code = $_POST['code_product'] ?? '';
        if ($code === '') throw new Exception('Invalid code_product');
        $stmt = $pdo->prepare("DELETE FROM product WHERE code_product = :code");
        $stmt->execute([':code'=>$code]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
