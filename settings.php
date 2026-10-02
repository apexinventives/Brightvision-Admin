<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();
$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';
if (empty($_SESSION['settings_csrf'])) $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));

// Get current admin info
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Create settings table if it doesn't exist
$create_settings_table = "CREATE TABLE IF NOT EXISTS system_settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    description TEXT,
    updated_by INT(11),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
)";

$conn->query($create_settings_table);

// Older installations created this table without the audit column.
$auditColumn = $conn->query("SHOW COLUMNS FROM system_settings LIKE 'updated_by'");
if ($auditColumn && $auditColumn->num_rows === 0) {
    $conn->query('ALTER TABLE system_settings ADD COLUMN updated_by INT(11) NULL');
}

// Create activity log table if it doesn't exist
$create_log_table = "CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    admin_id INT(11),
    action VARCHAR(255),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
)";

$conn->query($create_log_table);

// Repair partially populated installations without replacing saved settings.
{
    $default_settings = [
        ['site_name', 'Teacher\'s Admin Panel', 'text', 'Website name displayed in header'],
        ['site_description', 'Educational Management System', 'text', 'Site description for meta tags'],
        ['items_per_page', '10', 'number', 'Default items per page in tables'],
        ['date_format', 'Y-m-d', 'text', 'Date display format (Y-m-d, d/m/Y, m/d/Y)'],
        ['time_format', 'H:i:s', 'text', 'Time display format (H:i:s or h:i:s A)'],
        ['timezone', 'Asia/Colombo', 'text', 'System timezone'],
        ['session_timeout', '3600', 'number', 'Session timeout in seconds'],
        ['max_login_attempts', '5', 'number', 'Maximum login attempts before lockout'],
        ['lockout_time', '900', 'number', 'Account lockout time in seconds'],
        ['maintenance_mode', '0', 'boolean', 'Enable maintenance mode (0/1)'],
        ['allow_registration', '1', 'boolean', 'Allow new user registrations'],
        ['email_notifications', '1', 'boolean', 'Enable email notifications'],
        ['backup_frequency', 'daily', 'select', 'Database backup frequency'],
        ['log_retention_days', '30', 'number', 'Days to keep activity logs'],
        ['default_user_role', 'student', 'text', 'Default role for new users'],
        ['enable_charts', '1', 'boolean', 'Enable dashboard charts'],
        ['theme_color', 'blue', 'select', 'Dashboard theme color'],
        ['company_name', 'Maxicon Institute', 'text', 'Company/Institute name'],
        ['company_address', '', 'text', 'Company address'],
        ['company_phone', '', 'text', 'Company phone number'],
        ['company_email', '', 'email', 'Company email'],
        ['facebook_url', '', 'url', 'Facebook page URL'],
        ['twitter_url', '', 'url', 'Twitter profile URL'],
        ['linkedin_url', '', 'url', 'LinkedIn company URL']
    ];
    
    // Preserve the registration preference used by older installations.
    $legacyRegistration = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_registration'")->fetch_assoc();
    if ($legacyRegistration) {
        foreach ($default_settings as &$default) {
            if ($default[0] === 'allow_registration') $default[1] = $legacyRegistration['setting_value'] === '1' ? '1' : '0';
        }
        unset($default);
    }
    $insert_stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
    foreach ($default_settings as $setting) {
        $insert_stmt->bind_param("ssss", $setting[0], $setting[1], $setting[2], $setting[3]);
        $insert_stmt->execute();
    }
    $insert_stmt->close();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['settings_csrf'], $_POST['csrf_token'])) { http_response_code(403); exit('Invalid settings request. Refresh and try again.'); }
    if (isset($_POST['update_settings'])) {
        $updated = 0;
        foreach (($_POST['settings'] ?? []) as $key => $value) {
            // Sanitize based on type
            if (!is_string($value)) continue;
            $typeStmt = $conn->prepare('SELECT setting_type FROM system_settings WHERE setting_key = ?');
            $typeStmt->bind_param('s', $key);
            $typeStmt->execute();
            $stored = $typeStmt->get_result()->fetch_assoc();
            $typeStmt->close();
            if (!$stored) continue;
            if ($stored) {
                $type = $stored['setting_type'];
                if ($type == 'boolean') {
                    $value = $value ? '1' : '0';
                } elseif ($type == 'number') {
                    $value = intval($value);
                }
            }
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $stmt->bind_param("sis", $value, $admin_id, $key);
            if ($stmt->execute()) {
                $updated++;
            }
            $stmt->close();
        }
        
        // Log activity
        $action = "Settings Update";
        $details = "Updated $updated system settings";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $log_stmt->bind_param("isss", $admin_id, $action, $details, $ip);
        $log_stmt->execute();
        $log_stmt->close();
        
        $message = "Settings updated successfully! ($updated settings updated)";
    }
    
    elseif (isset($_POST['clear_cache'])) {
        // Clear system cache
        $cache_dir = "../cache/";
        if (file_exists($cache_dir)) {
            $files = glob($cache_dir . "*");
            $count = 0;
            foreach($files as $file) {
                if(is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
            $message = "Cache cleared successfully! ($count files removed)";
        } else {
            $message = "Cache directory not found.";
        }
        
        // Log activity
        $action = "Cache Cleared";
        $details = "Cleared system cache";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $log_stmt->bind_param("isss", $admin_id, $action, $details, $ip);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    elseif (isset($_POST['test_email'])) {
        $to = $admin['email'];
        $subject = "Test Email from " . $_SERVER['HTTP_HOST'];
        $message_body = "This is a test email from your admin panel. If you received this, email settings are working correctly.";
        $headers = "From: " . ($_POST['settings']['company_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST']);
        
        if (mail($to, $subject, $message_body, $headers)) {
            $message = "Test email sent successfully to $to!";
        } else {
            $error = "Failed to send test email. Check your server email configuration.";
        }
    }
}

// Get all settings
$settings_result = $conn->query("SELECT * FROM system_settings ORDER BY setting_key");
$settings = [];
$settings_by_type = [];

while($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
    $settings_by_type[$row['setting_type']][] = $row;
}

// Keep the form usable even if an installation cannot insert missing defaults.
foreach ($default_settings as $default) {
    if (!isset($settings[$default[0]])) {
        $settings[$default[0]] = ['setting_value' => $default[1], 'setting_type' => $default[2], 'description' => $default[3]];
    }
}

// Get system information
$system_info = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Server Protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
    'HTTP Host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Database Version' => $conn->server_info,
    'Database Name' => DB_NAME,
    'Max Upload Size' => ini_get('upload_max_filesize'),
    'Max Post Size' => ini_get('post_max_size'),
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
    'Session Save Path' => session_save_path() ?: 'Default',
    'Timezone' => date_default_timezone_get(),
    'Server Time' => date('Y-m-d H:i:s'),
    'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    'Your IP' => $_SERVER['REMOTE_ADDR']
];

// Get recent activity
$recent_activity = $conn->query("
    SELECT a.*, admins.username, admins.full_name 
    FROM admin_activity_log a
    LEFT JOIN admins ON a.admin_id = admins.id
    ORDER BY a.created_at DESC 
    LIMIT 10
");

// Get settings stats
$total_settings = $conn->query("SELECT COUNT(*) as count FROM system_settings")->fetch_assoc()['count'];
$updated_today = $conn->query("SELECT COUNT(*) as count FROM system_settings WHERE DATE(updated_at) = CURDATE()")->fetch_assoc()['count'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">System Settings</h1>
        <div class="flex space-x-2">
            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                <i class="fas fa-cog mr-1"></i> <?php echo $total_settings; ?> Settings
            </span>
            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                <i class="fas fa-calendar-day mr-1"></i> <?php echo $updated_today; ?> Updated Today
            </span>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex justify-between items-center animate-fade-in">
            <span><i class="fas fa-check-circle mr-2"></i><?php echo $message; ?></span>
            <button onclick="this.parentElement.remove()" class="text-green-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex justify-between items-center animate-fade-in">
            <span><i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <!-- Settings Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="settingsTabs">
            <li class="mr-2">
                <a href="#general" class="inline-block p-4 border-b-2 border-blue-600 text-blue-600 rounded-t-lg active" onclick="showTab('general')" id="tab-general">
                    <i class="fas fa-globe mr-2"></i>General
                </a>
            </li>
            <li class="mr-2">
                <a href="#company" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg" onclick="showTab('company')" id="tab-company">
                    <i class="fas fa-building mr-2"></i>Company
                </a>
            </li>
            <li class="mr-2">
                <a href="#security" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg" onclick="showTab('security')" id="tab-security">
                    <i class="fas fa-shield-alt mr-2"></i>Security
                </a>
            </li>
            <li class="mr-2">
                <a href="#email" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg" onclick="showTab('email')" id="tab-email">
                    <i class="fas fa-envelope mr-2"></i>Email
                </a>
            </li>
            <li class="mr-2">
                <a href="#system" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg" onclick="showTab('system')" id="tab-system">
                    <i class="fas fa-server mr-2"></i>System
                </a>
            </li>
            <li class="mr-2">
                <a href="#backup" class="inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 rounded-t-lg" onclick="showTab('backup')" id="tab-backup">
                    <i class="fas fa-database mr-2"></i>Backup
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Settings Forms -->
    <form method="POST" id="settingsForm"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['settings_csrf']); ?>"><fieldset <?php echo canAccess('settings', 'manage') ? '' : 'disabled'; ?>>
        <!-- General Settings Tab -->
        <div id="general-tab" class="settings-tab">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">General Settings</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Site Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['site_name']['description'] ?? 'Site Name'; ?>
                            </label>
                            <input type="text" name="settings[site_name]" 
                                   value="<?php echo htmlspecialchars($settings['site_name']['setting_value']); ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="hidden" name="types[site_name]" value="<?php echo $settings['site_name']['setting_type']; ?>">
                        </div>
                        
                        <!-- Items Per Page -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['items_per_page']['description'] ?? 'Items Per Page'; ?>
                            </label>
                            <input type="number" name="settings[items_per_page]" 
                                   value="<?php echo $settings['items_per_page']['setting_value']; ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <input type="hidden" name="types[items_per_page]" value="<?php echo $settings['items_per_page']['setting_type']; ?>">
                        </div>
                        
                        <!-- Site Description -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['site_description']['description'] ?? 'Site Description'; ?>
                            </label>
                            <textarea name="settings[site_description]" rows="3"
                                      class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($settings['site_description']['setting_value']); ?></textarea>
                            <input type="hidden" name="types[site_description]" value="<?php echo $settings['site_description']['setting_type']; ?>">
                        </div>
                        
                        <!-- Date Format -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['date_format']['description'] ?? 'Date Format'; ?>
                            </label>
                            <select name="settings[date_format]" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Y-m-d" <?php echo $settings['date_format']['setting_value'] == 'Y-m-d' ? 'selected' : ''; ?>>2023-12-31 (Y-m-d)</option>
                                <option value="d/m/Y" <?php echo $settings['date_format']['setting_value'] == 'd/m/Y' ? 'selected' : ''; ?>>31/12/2023 (d/m/Y)</option>
                                <option value="m/d/Y" <?php echo $settings['date_format']['setting_value'] == 'm/d/Y' ? 'selected' : ''; ?>>12/31/2023 (m/d/Y)</option>
                                <option value="F j, Y" <?php echo $settings['date_format']['setting_value'] == 'F j, Y' ? 'selected' : ''; ?>>December 31, 2023</option>
                            </select>
                            <input type="hidden" name="types[date_format]" value="<?php echo $settings['date_format']['setting_type']; ?>">
                        </div>
                        
                        <!-- Time Format -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['time_format']['description'] ?? 'Time Format'; ?>
                            </label>
                            <select name="settings[time_format]" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="H:i:s" <?php echo $settings['time_format']['setting_value'] == 'H:i:s' ? 'selected' : ''; ?>>14:30:00 (24 Hour)</option>
                                <option value="h:i:s A" <?php echo $settings['time_format']['setting_value'] == 'h:i:s A' ? 'selected' : ''; ?>>02:30:00 PM (12 Hour)</option>
                            </select>
                            <input type="hidden" name="types[time_format]" value="<?php echo $settings['time_format']['setting_type']; ?>">
                        </div>
                        
                        <!-- Timezone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['timezone']['description'] ?? 'Timezone'; ?>
                            </label>
                            <select name="settings[timezone]" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php
                                $timezones = ['Asia/Colombo', 'Asia/Kolkata', 'Asia/Dubai', 'UTC', 'America/New_York', 'Europe/London', 'Australia/Sydney'];
                                foreach($timezones as $tz):
                                ?>
                                    <option value="<?php echo $tz; ?>" <?php echo $settings['timezone']['setting_value'] == $tz ? 'selected' : ''; ?>>
                                        <?php echo str_replace('_', ' ', $tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="types[timezone]" value="<?php echo $settings['timezone']['setting_type']; ?>">
                        </div>
                        
                        <!-- Default User Role -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['default_user_role']['description'] ?? 'Default User Role'; ?>
                            </label>
                            <select name="settings[default_user_role]" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="student" <?php echo $settings['default_user_role']['setting_value'] == 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="teacher" <?php echo $settings['default_user_role']['setting_value'] == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="staff" <?php echo $settings['default_user_role']['setting_value'] == 'staff' ? 'selected' : ''; ?>>Staff</option>
                            </select>
                            <input type="hidden" name="types[default_user_role]" value="<?php echo $settings['default_user_role']['setting_type']; ?>">
                        </div>
                        
                        <!-- Theme Color -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['theme_color']['description'] ?? 'Theme Color'; ?>
                            </label>
                            <select name="settings[theme_color]" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="blue" <?php echo $settings['theme_color']['setting_value'] == 'blue' ? 'selected' : ''; ?>>Blue</option>
                                <option value="green" <?php echo $settings['theme_color']['setting_value'] == 'green' ? 'selected' : ''; ?>>Green</option>
                                <option value="purple" <?php echo $settings['theme_color']['setting_value'] == 'purple' ? 'selected' : ''; ?>>Purple</option>
                                <option value="red" <?php echo $settings['theme_color']['setting_value'] == 'red' ? 'selected' : ''; ?>>Red</option>
                                <option value="orange" <?php echo $settings['theme_color']['setting_value'] == 'orange' ? 'selected' : ''; ?>>Orange</option>
                            </select>
                            <input type="hidden" name="types[theme_color]" value="<?php echo $settings['theme_color']['setting_type']; ?>">
                        </div>
                        
                        <!-- Toggle Switches -->
                        <div class="md:col-span-2 grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="flex items-center space-x-3">
                                    <input type="hidden" name="settings[maintenance_mode]" value="0">
                                    <input type="checkbox" name="settings[maintenance_mode]" value="1" 
                                           <?php echo $settings['maintenance_mode']['setting_value'] == '1' ? 'checked' : ''; ?>
                                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm font-medium text-gray-700">Maintenance Mode</span>
                                </label>
                                <input type="hidden" name="types[maintenance_mode]" value="boolean">
                                <p class="text-xs text-gray-500 mt-1"><?php echo $settings['maintenance_mode']['description']; ?></p>
                            </div>
                            
                            <div>
                                <label class="flex items-center space-x-3">
                                    <input type="hidden" name="settings[allow_registration]" value="0">
                                    <input type="checkbox" name="settings[allow_registration]" value="1" 
                                           <?php echo $settings['allow_registration']['setting_value'] == '1' ? 'checked' : ''; ?>
                                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm font-medium text-gray-700">Allow Registration</span>
                                </label>
                                <input type="hidden" name="types[allow_registration]" value="boolean">
                                <p class="text-xs text-gray-500 mt-1"><?php echo $settings['allow_registration']['description']; ?></p>
                            </div>
                            
                            <div>
                                <label class="flex items-center space-x-3">
                                    <input type="hidden" name="settings[enable_charts]" value="0">
                                    <input type="checkbox" name="settings[enable_charts]" value="1" 
                                           <?php echo $settings['enable_charts']['setting_value'] == '1' ? 'checked' : ''; ?>
                                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm font-medium text-gray-700">Enable Dashboard Charts</span>
                                </label>
                                <input type="hidden" name="types[enable_charts]" value="boolean">
                                <p class="text-xs text-gray-500 mt-1"><?php echo $settings['enable_charts']['description']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Company Settings Tab -->
        <div id="company-tab" class="settings-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Company Information</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Company Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company/Institute Name</label>
                            <input type="text" name="settings[company_name]" 
                                   value="<?php echo htmlspecialchars($settings['company_name']['setting_value'] ?? ''); ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <input type="hidden" name="types[company_name]" value="text">
                        </div>
                        
                        <!-- Company Phone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="text" name="settings[company_phone]" 
                                   value="<?php echo htmlspecialchars($settings['company_phone']['setting_value'] ?? ''); ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <input type="hidden" name="types[company_phone]" value="text">
                        </div>
                        
                        <!-- Company Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="settings[company_email]" 
                                   value="<?php echo htmlspecialchars($settings['company_email']['setting_value'] ?? ''); ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <input type="hidden" name="types[company_email]" value="email">
                        </div>
                        
                        <!-- Company Address -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                            <textarea name="settings[company_address]" rows="3"
                                      class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"><?php echo htmlspecialchars($settings['company_address']['setting_value'] ?? ''); ?></textarea>
                            <input type="hidden" name="types[company_address]" value="text">
                        </div>
                        
                        <!-- Social Media Links -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Facebook URL</label>
                            <input type="url" name="settings[facebook_url]" 
                                   value="<?php echo htmlspecialchars($settings['facebook_url']['setting_value'] ?? ''); ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <input type="hidden" name="types[facebook_url]" value="url">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Twitter URL</label>
                            <input type="url" name="settings[twitter_url]" 
                                   value="<?php echo htmlspecialchars($settings['twitter_url']['setting_value'] ?? ''); ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <input type="hidden" name="types[twitter_url]" value="url">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn URL</label>
                            <input type="url" name="settings[linkedin_url]" 
                                   value="<?php echo htmlspecialchars($settings['linkedin_url']['setting_value'] ?? ''); ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <input type="hidden" name="types[linkedin_url]" value="url">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security Settings Tab -->
        <div id="security-tab" class="settings-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-red-600 to-red-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Security Settings</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Session Timeout -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['session_timeout']['description'] ?? 'Session Timeout'; ?> (seconds)
                            </label>
                            <input type="number" name="settings[session_timeout]" 
                                   value="<?php echo $settings['session_timeout']['setting_value']; ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <input type="hidden" name="types[session_timeout]" value="<?php echo $settings['session_timeout']['setting_type']; ?>">
                        </div>
                        
                        <!-- Max Login Attempts -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['max_login_attempts']['description'] ?? 'Max Login Attempts'; ?>
                            </label>
                            <input type="number" name="settings[max_login_attempts]" 
                                   value="<?php echo $settings['max_login_attempts']['setting_value']; ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <input type="hidden" name="types[max_login_attempts]" value="<?php echo $settings['max_login_attempts']['setting_type']; ?>">
                        </div>
                        
                        <!-- Lockout Time -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['lockout_time']['description'] ?? 'Lockout Time'; ?> (seconds)
                            </label>
                            <input type="number" name="settings[lockout_time]" 
                                   value="<?php echo $settings['lockout_time']['setting_value']; ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <input type="hidden" name="types[lockout_time]" value="<?php echo $settings['lockout_time']['setting_type']; ?>">
                        </div>
                        
                        <!-- Log Retention Days -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo $settings['log_retention_days']['description'] ?? 'Log Retention'; ?> (days)
                            </label>
                            <input type="number" name="settings[log_retention_days]" 
                                   value="<?php echo $settings['log_retention_days']['setting_value']; ?>"
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <input type="hidden" name="types[log_retention_days]" value="<?php echo $settings['log_retention_days']['setting_type']; ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Email Settings Tab -->
        <div id="email-tab" class="settings-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Email Settings</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Email Notifications Toggle -->
                        <div class="md:col-span-2">
                            <label class="flex items-center space-x-3">
                                <input type="hidden" name="settings[email_notifications]" value="0">
                                <input type="checkbox" name="settings[email_notifications]" value="1" 
                                       <?php echo ($settings['email_notifications']['setting_value'] ?? '1') == '1' ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                                <span class="text-sm font-medium text-gray-700">Enable Email Notifications</span>
                            </label>
                            <input type="hidden" name="types[email_notifications]" value="boolean">
                            <p class="text-xs text-gray-500 mt-1"><?php echo $settings['email_notifications']['description'] ?? 'Enable email notifications'; ?></p>
                        </div>
                        
                        <!-- Test Email Button -->
                        <div class="md:col-span-2 mt-4">
                            <button type="submit" name="test_email" 
                                    class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-paper-plane mr-2"></i>
                                Send Test Email
                            </button>
                            <p class="text-xs text-gray-500 mt-2">Test email will be sent to: <?php echo $admin['email']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Info Tab -->
        <div id="system-tab" class="settings-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-gray-600 to-gray-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">System Information</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($system_info as $label => $value): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-600"><?php echo $label; ?></span>
                            <span class="text-sm font-medium text-gray-900"><?php echo $value; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Recent Activity Log</h2>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <?php if(canAccess('activity') && $recent_activity->num_rows > 0): ?>
                            <?php while($log = $recent_activity->fetch_assoc()): ?>
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-history text-indigo-600"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['action']); ?></p>
                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars($log['details']); ?></p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="far fa-clock mr-1"></i>
                                        <?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?>
                                        by <?php echo htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'Unknown'); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-gray-500 py-4">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Backup Tab -->
        <div id="backup-tab" class="settings-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-600 to-yellow-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Backup & Maintenance</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Backup Frequency -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Backup Frequency</label>
                            <select name="settings[backup_frequency]" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                <option value="hourly" <?php echo ($settings['backup_frequency']['setting_value'] ?? 'daily') == 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                <option value="daily" <?php echo ($settings['backup_frequency']['setting_value'] ?? 'daily') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo ($settings['backup_frequency']['setting_value'] ?? 'daily') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo ($settings['backup_frequency']['setting_value'] ?? 'daily') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                            <input type="hidden" name="types[backup_frequency]" value="select">
                        </div>
                        
                        <!-- Clear Cache Button -->
                        <div class="flex items-end">
                            <button type="submit" name="clear_cache" 
                                    class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700">
                                <i class="fas fa-broom mr-2"></i>
                                Clear System Cache
                            </button>
                        </div>
                        
                        <!-- Manual Backup Buttons -->
                        <div class="md:col-span-2 grid grid-cols-2 gap-4 mt-4">
                            <a href="backup-database.php" class="bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 text-center">
                                <i class="fas fa-database mr-2"></i>
                                Backup Database
                            </a>
                            <a href="backup-files.php" class="bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 text-center">
                                <i class="fas fa-folder mr-2"></i>
                                Backup Files
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Save Button (visible on all tabs) -->
        <div class="mt-6 flex justify-end space-x-4">
            <button type="submit" name="update_settings" 
                    class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 text-lg font-semibold shadow-lg">
                <i class="fas fa-save mr-2"></i>
                Save All Settings
            </button>
        </div>
    </fieldset></form>
</div>

<script>
// Tab switching functionality
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.remove('hidden');
    
    // Update tab styles
    document.querySelectorAll('[id^="tab-"]').forEach(tabLink => {
        tabLink.classList.remove('border-blue-600', 'text-blue-600');
        tabLink.classList.add('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
    });
    
    // Highlight active tab
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
    activeTab.classList.add('border-blue-600', 'text-blue-600');
}

// Handle hash in URL
window.addEventListener('load', function() {
    const hash = window.location.hash.substring(1);
    if (hash && ['general', 'company', 'security', 'email', 'system', 'backup'].includes(hash)) {
        showTab(hash);
    } else {
        showTab('general');
    }
});

// Auto-hide messages after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.bg-green-100, .bg-red-100').forEach(function(el) {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 5000);

// Confirm before leaving with unsaved changes
let formChanged = false;
document.getElementById('settingsForm').addEventListener('input', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
}); 

// Reset form changed flag on submit
document.getElementById('settingsForm').addEventListener('submit', function() {
    formChanged = false;
});
</script>

<style>
.settings-tab {
    transition: opacity 0.3s ease-in-out;
}
.animate-fade-in {
    animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php
$conn->close();
include 'includes/footer.php';
?>
