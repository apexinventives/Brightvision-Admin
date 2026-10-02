<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();
$conn = getConnection();
ensureAccountPermissions($conn);
if (empty($_SESSION['account_csrf'])) $_SESSION['account_csrf'] = bin2hex(random_bytes(32));
$errors = [];
$success = $_SESSION['account_success'] ?? '';
unset($_SESSION['account_success']);
$staffSections = permissionSections();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['account_csrf'], $_POST['csrf_token'])) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT) ?: 0;
        $role = $_POST['role'] ?? 'staff';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = [];
        if (!in_array($action, ['create', 'update', 'delete'], true)) $errors[] = 'Invalid action.';
        if ($action !== 'create' && !$id) $errors[] = 'Invalid account.';
        if ($action !== 'delete') {
            if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Enter a full name of up to 100 characters.';
            if (!preg_match('/^[a-zA-Z0-9_.@-]{3,50}$/', $username)) $errors[] = 'Username must be 3–50 letters, numbers, dots, @, underscores or hyphens.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $errors[] = 'Enter a valid email of up to 100 characters.';
            if (!in_array($role, ['admin', 'staff'], true)) $errors[] = 'Select a valid role.';
            if (($action === 'create' || $password !== '') && (strlen($password) < 8 || strlen($password) > 72)) $errors[] = 'Use a password between 8 and 72 characters.';
            foreach (permissionActions() as $section => $actions) {
                $permissions[$section] = [];
                foreach ($actions as $key => $label) {
                    $permissions[$section][$key] = ($_POST['permissions'][$section][$key] ?? '') === '1';
                }
            }
        }
        if ($id === (int)$_SESSION['user_id'] && ($action === 'delete' || !$active || $role !== 'admin')) $errors[] = 'You cannot delete, deactivate, or remove the admin role from your own account.';
        if (!$errors) {
            $conn->begin_transaction();
            try {
                // Serialize account changes so two requests cannot remove the last active admin.
                $locked = $conn->query('SELECT a.id, a.is_active, p.role FROM admins a JOIN account_permissions p ON p.admin_id = a.id ORDER BY a.id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
                $target = null;
                $activeAdmins = 0;
                foreach ($locked as $account) {
                    if ((int)$account['id'] === $id) $target = $account;
                    if ($account['is_active'] && $account['role'] === 'admin') $activeAdmins++;
                }
                if ($action !== 'create' && !$target) throw new InvalidArgumentException('Account no longer exists.');
                if ($target && $target['is_active'] && $target['role'] === 'admin' && ($action === 'delete' || !$active || $role !== 'admin') && $activeAdmins <= 1) throw new InvalidArgumentException('At least one active admin must remain.');
                if ($action === 'delete') {
                    $stmt = $conn->prepare('DELETE FROM admins WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    if (!$stmt->execute()) throw new RuntimeException('Account deletion failed.');
                    $stmt->close();
                } else {
                    $check = $conn->prepare('SELECT id FROM admins WHERE id <> ? AND (username = ? OR email = ? OR username = ? OR email = ?) LIMIT 1');
                    $check->bind_param('issss', $id, $username, $email, $email, $username);
                    $check->execute();
                    $duplicate = $check->get_result()->fetch_assoc();
                    $check->close();
                    if ($duplicate) throw new InvalidArgumentException('That username or email is already used by another account.');
                    if ($action === 'create') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare('INSERT INTO admins (username, full_name, email, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                        $stmt->bind_param('ssssi', $username, $name, $email, $hash, $active);
                        if (!$stmt->execute()) throw new RuntimeException('Account creation failed.');
                        $id = $stmt->insert_id;
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare('UPDATE admins SET username = ?, full_name = ?, email = ?, is_active = ? WHERE id = ?');
                        $stmt->bind_param('sssii', $username, $name, $email, $active, $id);
                        if (!$stmt->execute()) throw new RuntimeException('Account update failed.');
                        $stmt->close();
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
                            $stmt->bind_param('si', $hash, $id);
                            if (!$stmt->execute()) throw new RuntimeException('Password update failed.');
                            $stmt->close();
                        }
                    }
                    $json = json_encode($permissions);
                    $stmt = $conn->prepare('INSERT INTO account_permissions (admin_id, role, permissions_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role), permissions_json = VALUES(permissions_json)');
                    $stmt->bind_param('iss', $id, $role, $json);
                    if (!$stmt->execute()) throw new RuntimeException('Permission update failed.');
                    $stmt->close();
                }
                $conn->commit();
                $_SESSION['account_success'] = $action === 'delete' ? 'Account deleted.' : 'Account and permissions saved. Changes apply on the next request.';
                header('Location: accounts.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Account management failed: ' . $e->getMessage());
                $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'The account could not be saved or deleted. Please try again.';
            }
        }
    }
}
$accounts = $conn->query('SELECT a.id, a.username, a.full_name, a.email, a.is_active, a.last_login, p.role, p.permissions_json FROM admins a JOIN account_permissions p ON p.admin_id = a.id ORDER BY a.full_name, a.id')->fetch_all(MYSQLI_ASSOC);
function accountFieldEscape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function renderAccountForm(array $account, array $sections, $creating = false) {
    $failed = ($_POST['action'] ?? '') === ($creating ? 'create' : 'update') && ($creating || (int)($_POST['account_id'] ?? 0) === (int)$account['id']);
    $data = $failed ? $_POST : $account;
    $permissions = $failed ? ($_POST['permissions'] ?? []) : (json_decode($account['permissions_json'] ?? '{}', true) ?: []);
    ?>
    <form method="POST" class="account-form space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo accountFieldEscape($_SESSION['account_csrf']); ?>">
        <input type="hidden" name="action" value="<?php echo $creating ? 'create' : 'update'; ?>">
        <input type="hidden" name="account_id" value="<?php echo (int)($account['id'] ?? 0); ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach (['full_name' => 'Full name', 'username' => 'Username', 'email' => 'Email'] as $key => $label): ?>
                <label class="block text-sm font-medium text-gray-700"><?php echo $label; ?><input name="<?php echo $key; ?>" type="<?php echo $key === 'email' ? 'email' : 'text'; ?>" required maxlength="<?php echo $key === 'username' ? 50 : 100; ?>" value="<?php echo accountFieldEscape($data[$key] ?? ''); ?>" class="mt-1 w-full border rounded-lg px-3 py-2"></label>
            <?php endforeach; ?>
            <label class="block text-sm font-medium text-gray-700"><?php echo $creating ? 'Password' : 'New password (optional)'; ?><input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?php echo $creating ? 'required' : ''; ?> class="mt-1 w-full border rounded-lg px-3 py-2"><span class="text-xs text-gray-500">8–72 characters<?php echo $creating ? '' : '; leave blank to keep the password'; ?>.</span></label>
            <label class="block text-sm font-medium text-gray-700">Role<select name="role" class="account-role mt-1 w-full border rounded-lg px-3 py-2 bg-white"><option value="staff" <?php echo ($data['role'] ?? 'staff') === 'staff' ? 'selected' : ''; ?>>Staff</option><option value="admin" <?php echo ($data['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option></select></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?php echo ($failed ? isset($_POST['is_active']) : ($account['is_active'] ?? 1)) ? 'checked' : ''; ?>>Active account</label>
        </div>
        <p class="text-sm text-gray-500">Admins can manage every section, including Payments and User Accounts. Staff can access only the sections selected below.</p>
        <fieldset class="staff-permissions border rounded-lg p-4"><legend class="px-2 font-semibold text-gray-700">Staff section permissions</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach (permissionActions() as $section => $actions): ?>
                <div class="border rounded-lg p-3"><h3 class="font-semibold mb-2"><?php echo accountFieldEscape($sections[$section]); ?></h3>
                <?php foreach ($actions as $key => $label): $grant = accountCan(['is_active' => 1, 'role' => 'staff', 'permissions' => $permissions], $section, $key); ?>
                    <label class="flex gap-2 items-center text-sm py-1"><input type="checkbox" name="permissions[<?php echo $section; ?>][<?php echo $key; ?>]" value="1" <?php echo $grant ? 'checked' : ''; ?>><?php echo accountFieldEscape($label); ?></label>
                <?php endforeach; ?></div>
            <?php endforeach; ?></div>
            <p class="text-xs text-gray-500 mt-3">Enable the area permission together with its actions. Amount permissions are separate for Payments and Reports. Saving or editing plans and central amounts also requires Payments: View amounts. Exporting requires the report amount permission and the chosen export permission.</p>        </fieldset>
        <div class="flex justify-end gap-2"><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2"><?php echo $creating ? 'Create Account' : 'Save Changes'; ?></button></div>
    </form>
    <?php
}
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">User Accounts &amp; Permissions</h1>
    <p class="text-gray-500 mb-6">Manage login accounts here. Student records are managed separately in Students.</p>
    <?php if ($success): ?><div class="rounded-lg bg-green-50 text-green-800 p-4 mb-6"><?php echo accountFieldEscape($success); ?></div><?php endif; ?>
    <?php if ($errors): ?><div role="alert" class="rounded-lg bg-red-50 text-red-800 p-4 mb-6"><?php echo accountFieldEscape(implode(' ', $errors)); ?></div><?php endif; ?>
    <?php if (canAccess('accounts', 'manage')): ?><section class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6"><h2 class="text-xl font-semibold mb-4">Create Login Account</h2><?php renderAccountForm([], $staffSections, true); ?></section><?php endif; ?>
    <section class="bg-white rounded-xl shadow-md overflow-hidden"><h2 class="text-xl font-semibold p-4 sm:p-6">Login Accounts</h2>
        <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-sm"><thead class="bg-gray-50 text-gray-500"><tr><th class="text-left px-5 py-3">Name / Username</th><th class="text-left px-5 py-3">Email</th><th class="text-left px-5 py-3">Role</th><th class="text-left px-5 py-3">Status</th><th class="text-left px-5 py-3">Permissions</th><th class="text-right px-5 py-3">Actions</th></tr></thead><tbody class="divide-y">
            <?php foreach ($accounts as $account): $permissions = json_decode($account['permissions_json'], true) ?: []; ?>
            <tr><td class="px-5 py-4"><p class="font-semibold"><?php echo accountFieldEscape($account['full_name']); ?></p><p class="text-gray-500"><?php echo accountFieldEscape($account['username']); ?></p></td><td class="px-5 py-4"><?php echo accountFieldEscape($account['email']); ?></td><td class="px-5 py-4"><?php echo ucfirst($account['role']); ?></td><td class="px-5 py-4"><?php echo $account['is_active'] ? 'Active' : 'Inactive'; ?></td><td class="px-5 py-4"><?php if ($account['role'] === 'admin'): ?>All sections<?php else: foreach ($staffSections as $section => $label): if (($permissions[$section] ?? 'none') !== 'none'): ?><p><?php echo accountFieldEscape($label . ': ' . (is_array($permissions[$section]) ? implode(', ', array_keys(array_filter($permissions[$section]))) : $permissions[$section])); ?></p><?php endif; endforeach; endif; ?></td><td class="px-5 py-4"><div class="flex items-center justify-end gap-2"><?php if (canAccess('accounts', 'manage')): ?><button type="button" onclick="document.getElementById('account-edit-<?php echo (int)$account['id']; ?>').showModal()" class="bg-blue-50 text-blue-600 px-3 py-2 rounded-lg">Edit</button>
                <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?><form method="POST" onsubmit="return confirm('Delete this login account?');"><input type="hidden" name="csrf_token" value="<?php echo accountFieldEscape($_SESSION['account_csrf']); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="account_id" value="<?php echo (int)$account['id']; ?>"><button class="bg-red-50 text-red-600 px-3 py-2 rounded-lg">Delete</button></form><?php endif; ?>
            <?php endif; ?></div></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php if (canAccess('accounts', 'manage')): foreach ($accounts as $account): ?><dialog id="account-edit-<?php echo (int)$account['id']; ?>" class="account-dialog p-4 sm:p-6 rounded-xl shadow-xl" data-reopen="<?php echo $errors && ($_POST['action'] ?? '') === 'update' && (int)($_POST['account_id'] ?? 0) === (int)$account['id'] ? '1' : '0'; ?>"><div class="flex justify-between items-center gap-3 mb-4"><h2 class="text-xl font-semibold">Edit Login Account</h2><button type="button" onclick="this.closest('dialog').close()" class="text-xl text-gray-500" aria-label="Close">&times;</button></div><?php if ($errors && (int)($_POST['account_id'] ?? 0) === (int)$account['id']): ?><p class="text-red-700 mb-4"><?php echo accountFieldEscape(implode(' ', $errors)); ?></p><?php endif; ?><?php renderAccountForm($account, $staffSections); ?><button type="button" onclick="this.closest('dialog').close()" class="mt-3 text-gray-600">Cancel</button></dialog><?php endforeach; endif; ?>
</div>
<style>.account-dialog{width:min(700px,calc(100vw - 32px));max-width:calc(100vw - 32px);max-height:calc(100vh - 32px);margin:auto;border:0;overflow:auto;white-space:normal}.account-dialog::backdrop{background:rgba(15,23,42,.55)}</style>
<script>
document.querySelectorAll('.account-form').forEach(form => {
    const role = form.querySelector('.account-role');
    const fields = form.querySelector('.staff-permissions');
    const update = () => { fields.hidden = role.value === 'admin'; };
    role.addEventListener('change', update); update();
});
document.querySelectorAll('.account-dialog').forEach(dialog => { if (dialog.dataset.reopen === '1') dialog.showModal(); });
</script>
<?php $conn->close(); include 'includes/footer.php'; ?>
