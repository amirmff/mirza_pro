<?php
session_start();
require_once __DIR__ . '/../../config.php';

class Auth {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function login($username, $password) {
        // Bot's admin table uses: id_admin, username, password, rule
        $stmt = $this->pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Bot uses plain text passwords (as seen in table.php)
        if ($admin && $admin['password'] === $password) {
            $_SESSION['admin_id'] = $admin['id_admin'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_rule'] = $admin['rule'];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Log login
            $this->logActivity($admin['id_admin'], 'login', 'Admin logged in');
            
            return [
                'success' => true,
                'admin' => [
                    'id' => $admin['id_admin'],
                    'username' => $admin['username'],
                    'rule' => $admin['rule']
                ]
            ];
        }
        
        return ['success' => false, 'error' => 'نام کاربری یا رمز عبور اشتباه است'];
    }
    
    public function logout() {
        if (isset($_SESSION['admin_id'])) {
            $this->logActivity($_SESSION['admin_id'], 'logout', 'Admin logged out');
        }
        session_destroy();
        return ['success' => true];
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id'])) {
            return false;
        }
        
        // Check session timeout (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /webpanel/login.php');
            exit;
        }
    }
    
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $rule = $_SESSION['admin_rule'] ?? '';
        
        // Administrator has all permissions
        if ($rule === 'administrator') {
            return true;
        }
        
        // Define permissions per role
        $permissions = [
            'Seller' => ['view_users', 'manage_users', 'view_invoices'],
            'support' => ['view_users', 'search_users', 'send_messages']
        ];
        
        return isset($permissions[$rule]) && in_array($permission, $permissions[$rule]);
    }
    
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function getCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function getCurrentAdmin() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        // Use bot's admin table structure: id_admin, username, password, rule
        $stmt = $this->pdo->prepare("SELECT id_admin, username, rule FROM admin WHERE id_admin = :id");
        $stmt->bindParam(':id', $_SESSION['admin_id'], PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function logActivity($admin_id, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, ip_address, created_at) VALUES (:admin_id, :action, :description, :ip, NOW())");
            $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    public function updatePassword($admin_id, $new_password) {
        // Bot uses plain text passwords in admin.password field
        $stmt = $this->pdo->prepare("UPDATE admin SET password = :password WHERE id_admin = :id");
        $stmt->bindParam(':password', $new_password, PDO::PARAM_STR);
        $stmt->bindParam(':id', $admin_id, PDO::PARAM_STR);
        return $stmt->execute();
    }
}

// Create admin_logs table if not exists
function createAdminLogsTable() {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at DATETIME NOT NULL,
            INDEX idx_admin_id (admin_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log("Failed to create admin_logs table: " . $e->getMessage());
    }
}

createAdminLogsTable();
