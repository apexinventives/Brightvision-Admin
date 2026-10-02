<?php

function permissionSections() {
    return ['sidebar' => 'Sidebar', 'dashboard' => 'Dashboard', 'students' => 'Students', 'reservations' => 'Exam Reservations', 'growth' => 'BV Growth Meter', 'payments' => 'Payments', 'reports' => 'Payment Reports', 'profile' => 'My Profile', 'settings' => 'Settings', 'activity' => 'Activity Log', 'accounts' => 'User Accounts', 'logout' => 'Logout'];
}

function permissionActions() {
    return [
        'sidebar' => ['view' => 'Show navigation'],
        'dashboard' => ['view' => 'Open dashboard', 'students' => 'Total students card', 'reservations' => 'Total reservations card', 'pending' => 'Pending approvals card', 'exams' => 'Today’s exams card', 'registrations' => 'Monthly registrations chart', 'classes' => 'Students by class chart', 'recent_students' => 'Recent students', 'recent_reservations' => 'Recent reservations'],
        'students' => ['view' => 'Student area', 'detail' => 'View student', 'create' => 'Add student', 'edit' => 'Edit student', 'delete' => 'Delete student'],
        'reservations' => ['view' => 'Reservation area', 'sms' => 'Send SMS', 'approve' => 'Approve reservation', 'delete' => 'Delete reservation'],
        'payments' => ['view' => 'Payment area', 'amounts' => 'View amounts', 'apply' => 'Apply payment updates', 'central' => 'Apply central amounts', 'codes' => 'Create / delete course codes', 'save_plan' => 'Save payment plan', 'edit_student' => 'Edit payment student', 'delete_student' => 'Delete payment student'],
        'reports' => ['view' => 'Report area', 'amounts' => 'View report amounts', 'pdf' => 'Export PDF', 'csv' => 'Export CSV'],
        'growth' => ['view' => 'View growth meter', 'manage' => 'Upload growth data'],
        'profile' => ['view' => 'View my profile', 'edit' => 'Change my profile'],
        'accounts' => ['view' => 'View login accounts', 'manage' => 'Change accounts and permissions'],
        'settings' => ['view' => 'View settings', 'manage' => 'Change settings / maintenance'],
        'activity' => ['view' => 'View activity log'], 'logout' => ['view' => 'Log out'],
    ];
}

function ensureAccountPermissions($conn) {
    if (!$conn->query("CREATE TABLE IF NOT EXISTS account_permissions (
        admin_id INT(11) NOT NULL PRIMARY KEY,
        role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
        permissions_json TEXT NOT NULL,
        CONSTRAINT fk_account_permissions_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB")) throw new RuntimeException('Unable to initialize account permissions.');
    // Existing login accounts are administrators; new accounts always get an explicit role.
    if (!$conn->query("INSERT IGNORE INTO account_permissions (admin_id, role, permissions_json) SELECT id, 'admin', '{}' FROM admins")) throw new RuntimeException('Unable to initialize existing account roles.');
}

function accountCan(array $account, $section, $action = 'view') {
    if (empty($account['is_active']) || !isset(permissionSections()[$section])) return false;
    if (!isset(permissionActions()[$section][$action])) return false;
    if (($account['role'] ?? '') === 'admin') return true;
    $level = $account['permissions'][$section] ?? 'none';
    if (is_array($level)) {
        if ($action !== 'view' && empty($level['view'])) return false;
        return !empty($level[$action]);
    }
    // Support the previous section-level grants while accounts are upgraded.
    if ($level === 'manage') return true;
    return $level === 'view' && ($action === 'view' || ($section === 'students' && $action === 'detail') || $section === 'dashboard');
}

function requirePermission($section, $action = 'view') {
    if (!canAccess($section, $action)) denyPermission();
}

function displayAmount($amount, $section = 'payments') {
    return canAccess($section, 'amounts') ? number_format((float)$amount, 2) : 'Hidden';
}

function currentAccount() {
    static $loaded = false;
    static $account = null;
    if ($loaded) return $account;
    $loaded = true;
    if (!isset($_SESSION['user_id'])) return null;
    require_once __DIR__ . '/database.php';
    $conn = getConnection();
    ensureAccountPermissions($conn);
    $stmt = $conn->prepare('SELECT a.id, a.full_name, a.username, a.email, a.is_active, p.role, p.permissions_json FROM admins a JOIN account_permissions p ON p.admin_id = a.id WHERE a.id = ?');
    $id = (int)$_SESSION['user_id'];
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    $conn->close();
    if ($account && $account['is_active']) {
        $account['permissions'] = json_decode($account['permissions_json'], true) ?: [];
        $_SESSION['user_role'] = $account['role'];
        $_SESSION['user_name'] = $account['full_name'];
        $_SESSION['username'] = $account['username'];
        $_SESSION['user_email'] = $account['email'];
    }
    return $account;
}

function canAccess($section, $action = 'view') {
    $account = currentAccount();
    return $account ? accountCan($account, $section, $action) : false;
}

function denyPermission() {
    http_response_code(403);
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        exit(json_encode(['error' => 'You do not have permission for this action.']));
    }
    exit('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access denied</title></head><body style="font-family:system-ui;padding:32px"><h1>Access denied</h1><p>You do not have permission for this section or action.</p><a href="index.php">Go to dashboard</a> · <a href="profile.php">My profile</a></body></html>');
}

function enforcePagePermission() {
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $routes = [
        'index.php' => ['dashboard', 'view'],
        'users.php' => ['students', 'view'], 'view-user.php' => ['students', 'detail'],
        'add-user.php' => ['students', 'create'], 'edit-user.php' => ['students', 'edit'], 'delete-user.php' => ['students', 'delete'],
        'reservations.php' => ['reservations', 'view'], 'approve-reservation.php' => ['reservations', 'approve'], 'delete-reservation.php' => ['reservations', 'delete'],
        'update-approval.php' => ['reservations', 'approve'], 'save-sms-log.php' => ['reservations', 'sms'],
        'bv-growth-upload.php' => ['growth', 'view'], 'bv-growth-history.php' => ['growth', 'view'],
        'payments.php' => ['payments', 'view'], 'payment-reports.php' => ['reports', 'view'],
        'profile.php' => ['profile', 'view'], 'logout.php' => ['logout', 'view'],
        'settings.php' => ['settings', 'view'], 'activity-log.php' => ['activity', 'view'], 'accounts.php' => ['accounts', 'view'],
    ];
    // Every active account has a safe landing page; dashboard data is gated separately.
    if ($page === 'index.php') return;
    if (!isset($routes[$page])) return;
    list($section, $action) = $routes[$page];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        if ($page === 'payments.php') {
            $actions = ['save_amount_settings' => 'central', 'delete_payment' => 'delete_student', 'edit_student' => 'edit_student', 'delete_course' => 'codes', 'create_course' => 'codes', 'toggle_installment' => 'apply', 'create' => 'save_plan'];
            $action = $actions[$_POST['action'] ?? ''] ?? '';
            if (in_array($action, ['central', 'edit_student', 'save_plan'], true)) requirePermission('payments', 'amounts');
        } elseif ($page === 'reservations.php') {
            requirePermission('reservations', 'sms');
            $action = 'approve';
        } elseif ($page === 'profile.php') $action = 'edit';
        elseif (in_array($page, ['settings.php', 'accounts.php', 'bv-growth-upload.php'], true)) $action = 'manage';
    }
    if (!canAccess($section, $action)) denyPermission();
}
