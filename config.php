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

// Check if credentials are still placeholders
if ($dbname === '{database_name}' || $usernamedb === '{username_db}' || $passworddb === '{password_db}') {
    if (file_exists($needs_setup_file)) {
        // Redirect to setup wizard
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
