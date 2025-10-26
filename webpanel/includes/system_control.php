<?php
/**
 * System Control API
 * Handles SSL, backups, cron jobs, and system operations
 */

require_once __DIR__ . '/auth.php';

// Require authentication
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check admin permission
check_permission('administrator');

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

// Backup directory
$backup_dir = __DIR__ . '/../../backups';
if (!file_exists($backup_dir)) {
    @mkdir($backup_dir, 0755, true);
}

switch ($action) {
    case 'install_ssl':
        require_once __DIR__ . '/../../config.php';
        
        if (empty($domainhosts)) {
            echo json_encode(['success' => false, 'message' => 'دامنه تنظیم نشده است']);
            break;
        }
        
        // Check if certbot is installed
        exec('which certbot', $output, $return_code);
        if ($return_code !== 0) {
            // Try to install certbot
            exec('apt-get update && apt-get install -y certbot python3-certbot-nginx 2>&1', $install_output, $install_code);
            if ($install_code !== 0) {
                echo json_encode(['success' => false, 'message' => 'نصب certbot ناموفق بود']);
                break;
            }
        }
        
        // Get SSL certificate
        exec("certbot --nginx -d {$domainhosts} --non-interactive --agree-tos --email admin@{$domainhosts} 2>&1", $cert_output, $cert_code);
        
        if ($cert_code === 0) {
            log_activity($_SESSION['admin_id'], 'ssl_install', "Installed SSL for: {$domainhosts}");
            echo json_encode(['success' => true, 'message' => 'گواهی SSL با موفقیت نصب شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در نصب SSL: ' . implode("\n", $cert_output)]);
        }
        break;
        
    case 'renew_ssl':
        exec('certbot renew --quiet 2>&1', $output, $return_code);
        
        if ($return_code === 0) {
            log_activity($_SESSION['admin_id'], 'ssl_renew', 'Renewed SSL certificate');
            echo json_encode(['success' => true, 'message' => 'گواهی SSL با موفقیت تمدید شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در تمدید SSL: ' . implode("\n", $output)]);
        }
        break;
        
    case 'create_backup':
        require_once __DIR__ . '/../../config.php';
        
        $type = $_POST['type'] ?? 'full';
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = '';
        
        try {
            if ($type === 'database' || $type === 'full') {
                // Database backup
                $db_backup_file = "{$backup_dir}/db_backup_{$timestamp}.sql";
                $command = "mysqldump -u{$usernamedb} -p{$passworddb} {$dbname} > {$db_backup_file} 2>&1";
                exec($command, $output, $return_code);
                
                if ($return_code !== 0) {
                    throw new Exception('Database backup failed');
                }
                
                // Compress
                exec("gzip {$db_backup_file}", $output, $return_code);
                $backup_file = "{$db_backup_file}.gz";
            }
            
            if ($type === 'files' || $type === 'full') {
                // Files backup
                $files_backup_file = "{$backup_dir}/files_backup_{$timestamp}.tar.gz";
                $project_root = realpath(__DIR__ . '/../..');
                exec("tar -czf {$files_backup_file} -C {$project_root} --exclude='backups' --exclude='logs' . 2>&1", $output, $return_code);
                
                if ($return_code !== 0) {
                    throw new Exception('Files backup failed');
                }
                
                $backup_file = $files_backup_file;
            }
            
            if ($type === 'full') {
                // Combine both backups
                $full_backup_file = "{$backup_dir}/full_backup_{$timestamp}.tar.gz";
                exec("tar -czf {$full_backup_file} -C {$backup_dir} db_backup_{$timestamp}.sql.gz files_backup_{$timestamp}.tar.gz 2>&1", $output, $return_code);
                
                // Clean up individual backups
                @unlink("{$backup_dir}/db_backup_{$timestamp}.sql.gz");
                @unlink("{$backup_dir}/files_backup_{$timestamp}.tar.gz");
                
                $backup_file = $full_backup_file;
            }
            
            log_activity($_SESSION['admin_id'], 'backup_create', "Created {$type} backup");
            echo json_encode(['success' => true, 'message' => 'پشتیبان با موفقیت ایجاد شد', 'file' => basename($backup_file)]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'خطا در ایجاد پشتیبان: ' . $e->getMessage()]);
        }
        break;
        
    case 'list_backups':
        $backups = [];
        $files = glob("{$backup_dir}/*.{sql.gz,tar.gz}", GLOB_BRACE);
        
        foreach ($files as $file) {
            $name = basename($file);
            $type = 'unknown';
            
            if (strpos($name, 'db_backup') !== false) {
                $type = 'database';
            } elseif (strpos($name, 'files_backup') !== false) {
                $type = 'files';
            } elseif (strpos($name, 'full_backup') !== false) {
                $type = 'full';
            }
            
            $backups[] = [
                'name' => $name,
                'type' => $type,
                'size' => format_bytes(filesize($file)),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by date descending
        usort($backups, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        echo json_encode(['success' => true, 'backups' => $backups]);
        break;
        
    case 'download_backup':
        $file = $_GET['file'] ?? '';
        $file_path = "{$backup_dir}/" . basename($file);
        
        if (!file_exists($file_path)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
        
    case 'delete_backup':
        $file = $_POST['file'] ?? '';
        $file_path = "{$backup_dir}/" . basename($file);
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                log_activity($_SESSION['admin_id'], 'backup_delete', "Deleted backup: {$file}");
                echo json_encode(['success' => true, 'message' => 'پشتیبان با موفقیت حذف شد']);
            } else {
                echo json_encode(['success' => false, 'message' => 'خطا در حذف پشتیبان']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'فایل یافت نشد']);
        }
        break;
        
    case 'list_crons':
        exec('crontab -l 2>&1', $output, $return_code);
        
        $crons = [];
        if ($return_code === 0 && !empty($output)) {
            foreach ($output as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') {
                    continue;
                }
                
                // Parse cron line
                $parts = preg_split('/\s+/', $line, 6);
                if (count($parts) >= 6) {
                    $schedule = implode(' ', array_slice($parts, 0, 5));
                    $command = $parts[5];
                    
                    $crons[] = [
                        'schedule' => $schedule,
                        'command' => $command
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'crons' => $crons]);
        break;
        
    case 'add_cron':
        $schedule = $_POST['schedule'] ?? '';
        $command = $_POST['command'] ?? '';
        
        if (empty($schedule) || empty($command)) {
            echo json_encode(['success' => false, 'message' => 'زمان‌بندی و دستور الزامی است']);
            break;
        }
        
        // Get current crontab
        exec('crontab -l 2>&1', $current_crons, $return_code);
        
        // Add new cron
        $new_cron = "{$schedule} {$command}";
        $current_crons[] = $new_cron;
        
        // Write back
        $temp_file = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($temp_file, implode("\n", $current_crons) . "\n");
        exec("crontab {$temp_file} 2>&1", $output, $return_code);
        unlink($temp_file);
        
        if ($return_code === 0) {
            log_activity($_SESSION['admin_id'], 'cron_add', "Added cron: {$new_cron}");
            echo json_encode(['success' => true, 'message' => 'وظیفه با موفقیت اضافه شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در افزودن وظیفه']);
        }
        break;
        
    case 'delete_cron':
        $index = intval($_POST['index'] ?? -1);
        
        if ($index < 0) {
            echo json_encode(['success' => false, 'message' => 'ایندکس نامعتبر']);
            break;
        }
        
        // Get current crontab
        exec('crontab -l 2>&1', $current_crons, $return_code);
        
        // Filter out comments and empty lines, then remove target
        $valid_crons = [];
        $real_index = 0;
        foreach ($current_crons as $line) {
            $line = trim($line);
            if (!empty($line) && $line[0] !== '#') {
                if ($real_index !== $index) {
                    $valid_crons[] = $line;
                }
                $real_index++;
            }
        }
        
        // Write back
        $temp_file = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($temp_file, implode("\n", $valid_crons) . "\n");
        exec("crontab {$temp_file} 2>&1", $output, $return_code);
        unlink($temp_file);
        
        if ($return_code === 0) {
            log_activity($_SESSION['admin_id'], 'cron_delete', "Deleted cron at index: {$index}");
            echo json_encode(['success' => true, 'message' => 'وظیفه با موفقیت حذف شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در حذف وظیفه']);
        }
        break;
        
    case 'clear_cache':
        // Clear PHP opcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Clear session files older than 24 hours
        $session_path = session_save_path();
        if (!empty($session_path)) {
            exec("find {$session_path} -name 'sess_*' -mtime +1 -delete 2>&1");
        }
        
        // Clear any temp files
        $temp_dir = __DIR__ . '/../../temp';
        if (file_exists($temp_dir)) {
            exec("find {$temp_dir} -type f -mtime +1 -delete 2>&1");
        }
        
        log_activity($_SESSION['admin_id'], 'cache_clear', 'Cleared system cache');
        echo json_encode(['success' => true, 'message' => 'کش با موفقیت پاک شد']);
        break;
        
    case 'restart_services':
        $results = [];
        
        // Restart PHP-FPM
        exec('systemctl restart php8.1-fpm 2>&1', $output, $return_code);
        $results[] = "PHP-FPM: " . ($return_code === 0 ? 'OK' : 'Failed');
        
        // Restart Nginx
        exec('systemctl restart nginx 2>&1', $output, $return_code);
        $results[] = "Nginx: " . ($return_code === 0 ? 'OK' : 'Failed');
        
        // Restart bot
        exec('supervisorctl restart mirza_bot 2>&1', $output, $return_code);
        $results[] = "Bot: " . ($return_code === 0 ? 'OK' : 'Failed');
        
        log_activity($_SESSION['admin_id'], 'services_restart', 'Restarted all services');
        echo json_encode(['success' => true, 'message' => 'سرویس‌ها راه‌اندازی شدند: ' . implode(', ', $results)]);
        break;
        
    case 'phpinfo':
        phpinfo();
        exit;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
