<?php
/**
 * Initial Setup Wizard
 * First-time configuration interface
 */

session_start();
// Buffer all output to keep headers safe
if (ob_get_level() === 0) { ob_start(); }

// Check if already configured
$setup_flag = __DIR__ . '/.needs_setup';
if (!file_exists($setup_flag)) {
    while (ob_get_level() > 0) { ob_end_clean(); }
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
            // Persist DB credentials for the panel to consume via config.php fallback
            $dbJson = [
                'db_host' => $_SESSION['db_host'] ?? 'localhost',
                'db_name' => $_SESSION['db_name'] ?? '',
                'db_user' => $_SESSION['db_user'] ?? '',
                'db_password' => $_SESSION['db_pass'] ?? ''
            ];
            file_put_contents(__DIR__ . '/.db_credentials.json', json_encode($dbJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            // Persist bot token for future updates fallback
            @file_put_contents(__DIR__ . '/.bot_token', $_SESSION['bot_token']);
            
            // Connect to database
            $pdo = new PDO(
                "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4",
                $_SESSION['db_user'],
                $_SESSION['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Import database schema if exists
            $sql_file = __DIR__ . '/../database/schema.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                if ($sql) { $pdo->exec($sql); }
            }
            
            // Ensure admin table (normalized columns)
            $pdo->exec("CREATE TABLE IF NOT EXISTS `admin` (
                `id_admin` INT(11) NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(255) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `rule` VARCHAR(50) NOT NULL DEFAULT 'administrator',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_admin`),
                UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Attempt to run table.php silently to create any missing tables
            $table_file = __DIR__ . '/../table.php';
            if (file_exists($table_file)) {
                // Prefer CLI to fully isolate output
                $phpbin = trim(shell_exec('command -v php 2>/dev/null') ?? '');
                if ($phpbin !== '') {
                    @shell_exec(escapeshellcmd($phpbin) . ' ' . escapeshellarg($table_file) . ' >/dev/null 2>&1');
                } else {
                    $lvl = ob_get_level();
                    ob_start();
                    @require_once $table_file;
                    while (ob_get_level() > $lvl) { ob_end_clean(); }
                }
            }
            
            // Create/Update admin user (support legacy columns)
            $hashed_password = password_hash($_SESSION['admin_password'], PASSWORD_BCRYPT);
            $cols = $pdo->query("SHOW COLUMNS FROM admin")->fetchAll(PDO::FETCH_COLUMN, 0);
            $hasNormalized = in_array('username', $cols) && in_array('password', $cols);
            $hasLegacy    = in_array('username_admin', $cols) && in_array('password_admin', $cols);
            // Build dynamic insert covering both normalized and legacy columns
            $fields = ['id_admin' => ':id', 'rule' => "'administrator'"];
            $params = [':id' => $_SESSION['admin_id']];
            $updates = [];
            if (in_array('username', $cols)) {
                $fields['username'] = ':u_norm';
                $params[':u_norm'] = $_SESSION['admin_username'];
                $updates[] = "username = VALUES(username)";
            }
            if (in_array('password', $cols)) {
                $fields['password'] = ':p_norm';
                $params[':p_norm'] = $hashed_password;
                $updates[] = "password = VALUES(password)";
            }
            if (in_array('username_admin', $cols)) {
                $fields['username_admin'] = ':u_legacy';
                $params[':u_legacy'] = $_SESSION['admin_username'];
                $updates[] = "username_admin = VALUES(username_admin)";
            }
            if (in_array('password_admin', $cols)) {
                // Keep legacy plain text to match existing logic
                $fields['password_admin'] = ':p_legacy';
                $params[':p_legacy'] = $_SESSION['admin_password'];
                $updates[] = "password_admin = VALUES(password_admin)";
            }
            if (empty($updates)) {
                throw new Exception('Admin table schema is missing expected columns.');
            }
            // Always include rule in updates if present
            if (in_array('rule', $cols)) { $updates[] = "rule = VALUES(rule)"; }
            $columnsSql = implode(', ', array_keys($fields));
            $valuesSql = implode(', ', array_values($fields));
            $updateSql = implode(', ', $updates);
            $sql = "INSERT INTO admin ($columnsSql) VALUES ($valuesSql) ON DUPLICATE KEY UPDATE $updateSql";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Update config.php with bot token and admin ID
            $config_file = __DIR__ . '/../config.php';
            if (file_exists($config_file) && is_writable($config_file)) {
                $config_content = file_get_contents($config_file);
                
                // Escape values for safe replacement
                $bot_token_escaped = addslashes($_SESSION['bot_token']);
                $admin_id_escaped = addslashes($_SESSION['admin_id']);
                $db_name_escaped = addslashes($_SESSION['db_name']);
                $db_user_escaped = addslashes($_SESSION['db_user']);
                $db_pass_escaped = addslashes($_SESSION['db_pass']);
                
                // Update bot token - replace ALL instances (handle both placeholder {API_KEY} and any existing value)
                $config_content = preg_replace(
                    "/\\\$APIKEY\s*=\s*['\"][^'\"]*['\"];/",
                    "\$APIKEY = '{$bot_token_escaped}';",
                    $config_content
                );
                
                // Also replace placeholder format specifically
                $config_content = str_replace(
                    "\$APIKEY = '{API_KEY}';",
                    "\$APIKEY = '{$bot_token_escaped}';",
                    $config_content
                );
                
                // Update admin ID - replace ALL instances
                $config_content = preg_replace(
                    "/\\\$adminnumber\s*=\s*['\"][^'\"]*['\"];/",
                    "\$adminnumber = '{$admin_id_escaped}';",
                    $config_content
                );
                
                // Also replace placeholder format specifically
                $config_content = str_replace(
                    "\$adminnumber = '{admin_number}';",
                    "\$adminnumber = '{$admin_id_escaped}';",
                    $config_content
                );
                
                // Update domain if provided - replace ALL instances
                if (!empty($_SESSION['domain'])) {
                    $domain_escaped = addslashes($_SESSION['domain']);
                    $config_content = preg_replace(
                        "/\\\$domainhosts\s*=\s*['\"][^'\"]*['\"];/",
                        "\$domainhosts = '{$domain_escaped}';",
                        $config_content
                    );
                    // Also replace placeholder format specifically
                    $config_content = str_replace(
                        "\$domainhosts = '{domain_name}';",
                        "\$domainhosts = '{$domain_escaped}';",
                        $config_content
                    );
                }
                
                // Update bot username (get from Telegram API)
                $bot_info = @file_get_contents("https://api.telegram.org/bot{$_SESSION['bot_token']}/getMe");
                if ($bot_info) {
                    $bot_data = json_decode($bot_info, true);
                    if ($bot_data && $bot_data['ok'] && isset($bot_data['result']['username'])) {
                        $bot_username = $bot_data['result']['username'];
                        $bot_username_escaped = addslashes('@' . $bot_username);
                        $config_content = preg_replace(
                            "/\\\$usernamebot\s*=\s*['\"][^'\"]*['\"];/",
                            "\$usernamebot = '{$bot_username_escaped}';",
                            $config_content
                        );
                        // Also replace placeholder format
                        $config_content = str_replace(
                            "\$usernamebot = '{username_bot}';",
                            "\$usernamebot = '{$bot_username_escaped}';",
                            $config_content
                        );
                    }
                }
                
                // Update database credentials - replace ALL instances
                $config_content = preg_replace(
                    "/\\\$dbname\s*=\s*['\"][^'\"]*['\"];/",
                    "\$dbname = '{$db_name_escaped}';",
                    $config_content
                );
                $config_content = str_replace(
                    "\$dbname = '{database_name}';",
                    "\$dbname = '{$db_name_escaped}';",
                    $config_content
                );
                
                $config_content = preg_replace(
                    "/\\\$usernamedb\s*=\s*['\"][^'\"]*['\"];/",
                    "\$usernamedb = '{$db_user_escaped}';",
                    $config_content
                );
                $config_content = str_replace(
                    "\$usernamedb = '{username_db}';",
                    "\$usernamedb = '{$db_user_escaped}';",
                    $config_content
                );
                
                $config_content = preg_replace(
                    "/\\\$passworddb\s*=\s*['\"][^'\"]*['\"];/",
                    "\$passworddb = '{$db_pass_escaped}';",
                    $config_content
                );
                $config_content = str_replace(
                    "\$passworddb = '{password_db}';",
                    "\$passworddb = '{$db_pass_escaped}';",
                    $config_content
                );
                
                // Write the updated content
                $write_result = file_put_contents($config_file, $config_content);
                
                if ($write_result === false) {
                    error_log("Error: Failed to write config.php");
                    throw new Exception("Failed to update config.php - check file permissions");
                }
                
                // Verify the update worked by checking the main config section (line 84+)
                $verify_content = file_get_contents($config_file);
                // Check if token appears in the main config section (after line 80)
                $lines = explode("\n", $verify_content);
                $found_token = false;
                foreach ($lines as $line) {
                    if (strpos($line, '$APIKEY') !== false && strpos($line, $_SESSION['bot_token']) !== false) {
                        $found_token = true;
                        break;
                    }
                }
                
                if (!$found_token) {
                    error_log("Warning: config.php update verification failed - bot token not found in main config section");
                    // Try one more time with a more direct approach
                    $config_content = file_get_contents($config_file);
                    // Direct string replacement for the main section (after database connection)
                    $main_section_pattern = "/(\\\$APIKEY\s*=\s*['\"])[^'\"]*(['\"];)/";
                    $config_content = preg_replace($main_section_pattern, "\$1{$bot_token_escaped}\$2", $config_content);
                    $main_section_pattern = "/(\\\$adminnumber\s*=\s*['\"])[^'\"]*(['\"];)/";
                    $config_content = preg_replace($main_section_pattern, "\$1{$admin_id_escaped}\$2", $config_content);
                    file_put_contents($config_file, $config_content);
                }
            } else {
                $error_msg = "Error: config.php not writable or not found: " . $config_file;
                error_log($error_msg);
                throw new Exception($error_msg);
            }
            
            // Update setting table with admin ID and bot token
            try {
                // Check if setting table exists and has adminnumber column
                $setting_check = $pdo->query("SHOW COLUMNS FROM setting LIKE 'adminnumber'")->fetch();
                if ($setting_check) {
                    $pdo->exec("UPDATE setting SET adminnumber = '{$_SESSION['admin_id']}' WHERE id = 1");
                }
            } catch (Exception $e) {
                // Table might not exist yet, that's okay
            }
            
            // Set webhook to index.php (Telegram updates handler)
            $webhook_url = '';
            if (!empty($_SESSION['domain'])) {
                // If domain provided, use HTTPS
                $webhook_url = "https://{$_SESSION['domain']}/index.php";
            } else {
                // Fallback to HTTP with server IP
                $server_ip = $_SERVER['SERVER_ADDR'] ?? (gethostbyname(gethostname()) ?: 'localhost');
                $http_port = 80; // Default, could be detected from nginx config
                $webhook_url = "http://{$server_ip}/index.php";
            }
            
            if (!empty($webhook_url) && !empty($_SESSION['bot_token'])) {
                $ch = curl_init("https://api.telegram.org/bot{$_SESSION['bot_token']}/setWebhook");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => ['url' => $webhook_url],
                    CURLOPT_TIMEOUT => 10
                ]);
                $webhook_response = curl_exec($ch);
                $webhook_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                // Log webhook result
                if ($webhook_http_code === 200) {
                    $webhook_data = json_decode($webhook_response, true);
                    if (!($webhook_data['ok'] ?? false)) {
                        error_log("Webhook setup warning: " . ($webhook_data['description'] ?? 'Unknown error'));
                    }
                }
            }
            
            // Reload nginx if domain provided (before SSL)
            if (!empty($_SESSION['domain'])) {
                // Update nginx server_name
                $nginx_config = '/etc/nginx/sites-available/mirza_pro';
                if (file_exists($nginx_config) && is_writable($nginx_config)) {
                    $nginx_content = file_get_contents($nginx_config);
                    $nginx_content = preg_replace(
                        "/server_name\s+[^;]+;/",
                        "server_name {$_SESSION['domain']};",
                        $nginx_content
                    );
                    file_put_contents($nginx_config, $nginx_content);
                    @exec('nginx -t && systemctl reload nginx 2>&1');
                }
            }
            
            // Start bot via supervisor (must happen after config.php is updated)
            if (function_exists('exec')) {
                @exec('supervisorctl reread 2>&1', $supervisor_out, $supervisor_code);
                @exec('supervisorctl update 2>&1', $supervisor_out, $supervisor_code);
                @exec('supervisorctl stop mirza_bot 2>&1'); // Stop if running
                sleep(1);
                @exec('supervisorctl start mirza_bot 2>&1', $supervisor_out, $supervisor_code);
                sleep(3); // Give bot time to start
                
                // Verify bot started
                @exec('supervisorctl status mirza_bot 2>&1', $status_out, $status_code);
                if (!empty($status_out[0]) && strpos($status_out[0], 'RUNNING') === false) {
                    error_log("Bot failed to start: " . implode("\n", $status_out));
                }
            }
            
            // If domain provided, attempt SSL setup (after nginx reload)
            if (!empty($_SESSION['domain'])) {
                // Ensure certbot is installed
                $email = "admin@{$_SESSION['domain']}";
                @exec("which certbot || (apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq certbot python3-certbot-nginx 2>&1)", $certbot_check);
                
                // Issue SSL certificate (run in background but log output)
                $ssl_log = '/tmp/ssl_install_' . time() . '.log';
                @exec("certbot --nginx -d {$_SESSION['domain']} --redirect --non-interactive --agree-tos -m {$email} > {$ssl_log} 2>&1 &");
                
                // Wait a moment then check if SSL was successful
                sleep(2);
                if (file_exists("/etc/letsencrypt/live/{$_SESSION['domain']}/cert.pem")) {
                    // SSL installed successfully, update webhook to HTTPS
                    $webhook_url = "https://{$_SESSION['domain']}/index.php";
                    $ch = curl_init("https://api.telegram.org/bot{$_SESSION['bot_token']}/setWebhook");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => ['url' => $webhook_url],
                        CURLOPT_TIMEOUT => 10
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
            
            // Remove setup flag
            @unlink($setup_flag);
            
            // Clear session and buffers, then redirect
            session_destroy();
            while (ob_get_level() > 0) { ob_end_clean(); }
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
