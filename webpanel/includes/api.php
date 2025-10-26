<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../function.php';
require_once __DIR__ . '/auth.php';

class API {
    private $pdo;
    private $auth;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->auth = new Auth();
    }
    
    // User Management
    public function getUsers($page = 1, $limit = 20, $search = '', $filter = 'all') {
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "(id LIKE :search OR username LIKE :search OR number LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if ($filter === 'active') {
            $where[] = "User_Status = 'Active'";
        } elseif ($filter === 'blocked') {
            $where[] = "User_Status = 'block'";
        } elseif ($filter === 'agent') {
            $where[] = "agent != 'f'";
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM user $whereClause";
        $stmt = $this->pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get users
        $sql = "SELECT id, username, number, Balance, agent, User_Status, register, verify, affiliates FROM user $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ];
    }
    
    public function getUserDetails($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'کاربر یافت نشد'];
        }
        
        // Get user invoices
        $stmt = $this->pdo->prepare("SELECT * FROM invoice WHERE id_user = :id ORDER BY id_invoice DESC LIMIT 10");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payments
        $stmt = $this->pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :id ORDER BY id_payment DESC LIMIT 10");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'user' => $user,
            'invoices' => $invoices,
            'payments' => $payments
        ];
    }
    
    public function updateUser($user_id, $data) {
        $allowed = ['Balance', 'User_Status', 'agent', 'verify', 'limit_usertest'];
        $updates = [];
        $params = [':id' => $user_id];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'error' => 'هیچ فیلدی برای بروزرسانی یافت نشد'];
        }
        
        $sql = "UPDATE user SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'کاربر با موفقیت بروزرسانی شد'];
        }
        
        return ['success' => false, 'error' => 'خطا در بروزرسانی کاربر'];
    }
    
    public function deleteUser($user_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Delete related records
            $tables = ['invoice', 'Payment_report', 'service_other', 'Giftcodeconsumed'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->prepare("DELETE FROM $table WHERE id_user = :id");
                $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Delete user
            $stmt = $this->pdo->prepare("DELETE FROM user WHERE id = :id");
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'کاربر با موفقیت حذف شد'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => 'خطا در حذف کاربر: ' . $e->getMessage()];
        }
    }
    
    // Panel Management
    public function getPanels() {
        $stmt = $this->pdo->query("SELECT * FROM marzban_panel ORDER BY name_panel");
        $panels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'panels' => $panels];
    }
    
    public function getPanelDetails($panel_name) {
        $stmt = $this->pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = :name");
        $stmt->bindParam(':name', $panel_name, PDO::PARAM_STR);
        $stmt->execute();
        $panel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panel) {
            return ['success' => false, 'error' => 'پنل یافت نشد'];
        }
        
        return ['success' => true, 'panel' => $panel];
    }
    
    // Products
    public function getProducts() {
        $stmt = $this->pdo->query("SELECT * FROM product ORDER BY Location, name_product");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'products' => $products];
    }
    
    // Statistics
    public function getStatistics() {
        $stats = [];
        
        // Total users
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM user");
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Active users
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM user WHERE User_Status = 'Active'");
        $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total invoices
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM invoice");
        $stats['total_invoices'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Active services
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM invoice WHERE Status = 'active'");
        $stats['active_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total revenue
        $stmt = $this->pdo->query("SELECT SUM(price) as total FROM Payment_report WHERE payment_Status = 'paid'");
        $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Today's revenue
        $stmt = $this->pdo->query("SELECT SUM(price) as total FROM Payment_report WHERE payment_Status = 'paid' AND DATE(time) = CURDATE()");
        $stats['today_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // New users today
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM user WHERE DATE(FROM_UNIXTIME(register)) = CURDATE()");
        $stats['new_users_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return ['success' => true, 'stats' => $stats];
    }
    
    // Payments
    public function getPayments($page = 1, $limit = 20, $status = 'all') {
        $offset = ($page - 1) * $limit;
        
        $where = '';
        if ($status !== 'all') {
            $where = "WHERE payment_Status = :status";
        }
        
        // Get total
        $countSql = "SELECT COUNT(*) as total FROM Payment_report $where";
        $stmt = $this->pdo->prepare($countSql);
        if ($status !== 'all') {
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get payments
        $sql = "SELECT * FROM Payment_report $where ORDER BY id_payment DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        if ($status !== 'all') {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'payments' => $payments,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ];
    }
    
    // Settings
    public function getSettings() {
        $stmt = $this->pdo->query("SELECT * FROM setting LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['success' => true, 'settings' => $settings];
    }
    
    public function updateSettings($data) {
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $updates[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        
        if (empty($updates)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }
        
        $sql = "UPDATE setting SET " . implode(', ', $updates);
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'تنظیمات با موفقیت بروزرسانی شد'];
        }
        
        return ['success' => false, 'error' => 'خطا در بروزرسانی تنظیمات'];
    }
    
    // Send message to user
    public function sendMessageToUser($user_id, $message) {
        require_once __DIR__ . '/../../botapi.php';
        $result = sendmessage($user_id, $message, null, 'HTML');
        
        if ($result && isset($result['ok']) && $result['ok']) {
            return ['success' => true, 'message' => 'پیام با موفقیت ارسال شد'];
        }
        
        return ['success' => false, 'error' => 'خطا در ارسال پیام'];
    }
}
