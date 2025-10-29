<?php
// Check if setup wizard is needed
$needs_setup_file = __DIR__ . '/webpanel/.needs_setup';
$is_setup_wizard = (basename($_SERVER['PHP_SELF']) === 'setup.php');

// Allow setup wizard to run without credentials
if (file_exists($needs_setup_file) && $is_setup_wizard) {
    // Setup wizard will handle DB connection
    $APIKEY = '{API_KEY}';
    $adminnumber = '{admin_number}';
    $domainhosts = '{domain_name}';
    $usernamebot = '{username_bot}';
    $new_marzban = true;
    return;
}

// Regular config loading
$dbname = '{database_name}';
$usernamedb = '{username_db}';
$passworddb = '{password_db}';

// Attempt to load credentials from installer files if placeholders
if ($dbname === '{database_name}' || $usernamedb === '{username_db}' || $passworddb === '{password_db}') {
    // 1) From installer creds file
    $cred_file = '/root/.mirza_db_credentials';
    if (is_readable($cred_file)) {
        $lines = @file($cred_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $ln) {
                if (strpos($ln, '=') !== false) {
                    list($k, $v) = array_map('trim', explode('=', $ln, 2));
                    if ($k === 'DB_NAME') $dbname = $v;
                    if ($k === 'DB_USER') $usernamedb = $v;
                    if ($k === 'DB_PASSWORD') $passworddb = $v;
                }
            }
        }
    }
    // 2) From webpanel JSON (created by installer)
    if (($dbname === '{database_name}' || $usernamedb === '{username_db}' || $passworddb === '{password_db}')) {
        $json_file = __DIR__ . '/webpanel/.db_credentials.json';
        if (is_readable($json_file)) {
            $j = json_decode(@file_get_contents($json_file), true);
            if (is_array($j)) {
                if (!empty($j['db_name'])) $dbname = $j['db_name'];
                if (!empty($j['db_user'])) $usernamedb = $j['db_user'];
                if (!empty($j['db_password'])) $passworddb = $j['db_password'];
            }
        }
    }
}

// If still placeholders and setup flag exists, redirect to setup. Otherwise, show error.
if ($dbname === '{database_name}' || $usernamedb === '{username_db}' || $passworddb === '{password_db}') {
    if (file_exists($needs_setup_file)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        header("Location: {$protocol}://{$host}/webpanel/setup.php");
        exit;
    }
    die("ERROR: Database credentials not configured. Please run the setup wizard first.");
}

$connect = mysqli_connect("localhost", $usernamedb, $passworddb, $dbname);
if (!$connect || $connect->connect_error) { 
    error_log("MySQL connection error: " . ($connect ? $connect->connect_error : "Connection failed"));
    die("Database connection error. Please check your credentials."); 
}
mysqli_set_charset($connect, "utf8mb4");

$options = [ 
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
    PDO::ATTR_EMULATE_PREPARES => false, 
];
$dsn = "mysql:host=localhost;dbname=$dbname;charset=utf8mb4";
try { 
    $pdo = new PDO($dsn, $usernamedb, $passworddb, $options); 
} catch (\PDOException $e) { 
    error_log("Database connection failed: " . $e->getMessage()); 
    die("PDO connection error. Please check error_log for details.");
}

$APIKEY = '{API_KEY}';
$adminnumber = '{admin_number}';
$domainhosts = '{domain_name}';
$usernamebot = '{username_bot}';

// Check if bot token is configured
if ($APIKEY === '{API_KEY}') {
    if (file_exists($needs_setup_file)) {
        // Redirect to setup wizard
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        header("Location: {$protocol}://{$host}/webpanel/setup.php");
        exit;
    }
    die("ERROR: Telegram bot API key not configured. Please run the setup wizard first.");
}

$new_marzban = true;
?>
