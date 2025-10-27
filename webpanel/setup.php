<?php
/**
 * Initial Setup Wizard
 * First-time configuration interface
 */

session_start();

// Check if already configured
$setup_flag = __DIR__ . '/.needs_setup';
if (!file_exists($setup_flag)) {
    header('Location: /webpanel/login.php');
    exit;
}

$error = '';
$step = $_GET['step'] ?? 1;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Database configuration
        $_SESSION['db_host'] = $_POST['db_host'] ?? 'localhost';
        $_SESSION['db_name'] = $_POST['db_name'] ?? '';
        $_SESSION['db_user'] = $_POST['db_user'] ?? '';
        $_SESSION['db_pass'] = $_POST['db_pass'] ?? '';
        
        // Test connection
        try {
            $pdo = new PDO(
                "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4",
                $_SESSION['db_user'],
                $_SESSION['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            header('Location: ?step=2');
            exit;
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
        
    } elseif ($step == 2) {
        // Bot configuration
        $_SESSION['bot_token'] = $_POST['bot_token'] ?? '';
        $_SESSION['admin_id'] = $_POST['admin_id'] ?? '';
        $_SESSION['admin_username'] = $_POST['admin_username'] ?? 'admin';
        $_SESSION['admin_password'] = $_POST['admin_password'] ?? '';
        $_SESSION['domain'] = $_POST['domain'] ?? '';
        
        if (empty($_SESSION['bot_token']) || empty($_SESSION['admin_id']) || empty($_SESSION['admin_password'])) {
            $error = 'Please fill all required fields';
        } else {
            header('Location: ?step=3');
            exit;
        }
        
    } elseif ($step == 3) {
        // Final step - apply configuration
        try {
            // Update config.php
            $config_content = "<?php\n";
            $config_content .= "\$dbname = '{$_SESSION['db_name']}';\n";
            $config_content .= "\$usernamedb = '{$_SESSION['db_user']}';\n";
            $config_content .= "\$passworddb = '{$_SESSION['db_pass']}';\n\n";
            $config_content .= "\$connect = mysqli_connect(\"localhost\", \$usernamedb, \$passworddb, \$dbname);\n";
            $config_content .= "if (\$connect->connect_error) { die(\"error\" . \$connect->connect_error); }\n";
            $config_content .= "mysqli_set_charset(\$connect, \"utf8mb4\");\n\n";
            $config_content .= "\$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];\n";
            $config_content .= "\$dsn = \"mysql:host=localhost;dbname=\$dbname;charset=utf8mb4\";\n";
            $config_content .= "try { \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options); } catch (\\PDOException \$e) { error_log(\"Database connection failed: \" . \$e->getMessage()); die(\"PDO connection error.\"); }\n\n";
            $config_content .= "\$APIKEY = '{$_SESSION['bot_token']}';\n";
            $config_content .= "\$adminnumber = '{$_SESSION['admin_id']}';\n";
            $config_content .= "\$domainhosts = '{$_SESSION['domain']}';\n";
            $config_content .= "\$usernamebot = '';\n\n";
            $config_content .= "\$new_marzban = true;\n";
            $config_content .= "?>\n";
            
            file_put_contents(__DIR__ . '/../config.php', $config_content);
            
            // Connect to database
            $pdo = new PDO(
                "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4",
                $_SESSION['db_user'],
                $_SESSION['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Import database schema or create admin table
            $sql_file = __DIR__ . '/../database/schema.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                $pdo->exec($sql);
            } else {
                // Fallback: create admin table directly
                $pdo->exec("CREATE TABLE IF NOT EXISTS `admin` (
                    `id_admin` INT(11) NOT NULL AUTO_INCREMENT,
                    `username_admin` VARCHAR(255) NOT NULL,
                    `password_admin` VARCHAR(255) NOT NULL,
                    `rule` VARCHAR(50) NOT NULL DEFAULT 'administrator',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_admin`),
                    UNIQUE KEY `username_admin` (`username_admin`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            
            // Run table.php to create telegram bot tables
            $table_file = __DIR__ . '/../table.php';
            if (file_exists($table_file)) {
                ob_start();
                require_once $table_file;
                ob_end_clean();
            }
            
            // Create admin user
            $hashed_password = password_hash($_SESSION['admin_password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admin (id_admin, username_admin, password_admin, rule) VALUES (:id, :username, :password, 'administrator') ON DUPLICATE KEY UPDATE password_admin = :password");
            $stmt->execute([
                ':id' => $_SESSION['admin_id'],
                ':username' => $_SESSION['admin_username'],
                ':password' => $hashed_password
            ]);
            
            // Set webhook
            $webhook_url = !empty($_SESSION['domain']) ? 
                "https://{$_SESSION['domain']}/webhooks.php" : 
                "http://{$_SERVER['SERVER_ADDR']}/webhooks.php";
            
            $ch = curl_init("https://api.telegram.org/bot{$_SESSION['bot_token']}/setWebhook");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $webhook_url]);
            curl_exec($ch);
            curl_close($ch);
            
            // Remove setup flag
            unlink($setup_flag);
            
            // Clear session
            session_destroy();
            
            // Redirect to login
            header('Location: /webpanel/login.php?setup_complete=1');
            exit;
            
        } catch (Exception $e) {
            $error = 'Configuration failed: ' . $e->getMessage();
        }
    }
}

// Load saved DB credentials if available (created by installer)
$db_creds_file = __DIR__ . '/.db_credentials.json';
if ($step == 1 && file_exists($db_creds_file)) {
    $creds_json = file_get_contents($db_creds_file);
    $creds = json_decode($creds_json, true);
    if ($creds) {
        $_SESSION['db_host'] = $creds['db_host'] ?? 'localhost';
        $_SESSION['db_name'] = $creds['db_name'] ?? '';
        $_SESSION['db_user'] = $creds['db_user'] ?? '';
        $_SESSION['db_pass'] = $creds['db_password'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>راه‌اندازی اولیه - Mirza Pro</title>
    <link rel="stylesheet" href="/webpanel/assets/css/style.css">
    <style>
        .setup-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .setup-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .setup-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-bottom: 3px solid #ecf0f1;
            position: relative;
        }
        .step.active {
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: bold;
        }
        .step.completed {
            border-bottom-color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-box">
            <h1 style="text-align: center; margin-bottom: 10px;">🚀 Mirza Pro</h1>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">راه‌اندازی اولیه سیستم</p>
            
            <div class="setup-steps">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    1. پایگاه داده
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    2. ربات تلگرام
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                    3. تکمیل
                </div>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message" style="background: #fee; border: 1px solid #fcc; color: #c33; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($step == 1): ?>
                    <h2>تنظیمات پایگاه داده</h2>
                    <p style="color: #666; margin-bottom: 20px;">اطلاعات MySQL را وارد کنید</p>
                    
                    <div class="form-group">
                        <label>نام پایگاه داده</label>
                        <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>نام کاربری</label>
                        <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_user'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" name="db_pass" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_pass'] ?? ''); ?>" required>
                    </div>
                    
                    <input type="hidden" name="db_host" value="localhost">
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">مرحله بعد</button>
                    
                <?php elseif ($step == 2): ?>
                    <h2>تنظیمات ربات تلگرام</h2>
                    <p style="color: #666; margin-bottom: 20px;">اطلاعات ربات را از @BotFather دریافت کنید</p>
                    
                    <div class="form-group">
                        <label>توکن ربات *</label>
                        <input type="text" name="bot_token" class="form-control" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" required>
                        <small style="color: #666;">از @BotFather دریافت کنید</small>
                    </div>
                    
                    <div class="form-group">
                        <label>آیدی عددی ادمین *</label>
                        <input type="text" name="admin_id" class="form-control" placeholder="123456789" required>
                        <small style="color: #666;">از @userinfobot دریافت کنید</small>
                    </div>
                    
                    <div class="form-group">
                        <label>نام کاربری ادمین پنل</label>
                        <input type="text" name="admin_username" class="form-control" value="admin" required>
                    </div>
                    
                    <div class="form-group">
                        <label>رمز عبور پنل مدیریت *</label>
                        <input type="password" name="admin_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>دامنه (اختیاری)</label>
                        <input type="text" name="domain" class="form-control" placeholder="bot.example.com">
                        <small style="color: #666;">برای تنظیم بعدی SSL لازم است</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">اتمام راه‌اندازی</button>
                    
                <?php elseif ($step == 3): ?>
                    <div style="text-align: center;">
                        <div style="font-size: 64px; margin-bottom: 20px;">⚙️</div>
                        <h2>در حال تکمیل راه‌اندازی...</h2>
                        <p>لطفا صبر کنید</p>
                    </div>
                    <script>
                        setTimeout(function() {
                            document.querySelector('form').submit();
                        }, 1000);
                    </script>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
