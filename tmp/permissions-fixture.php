<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/permissions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = getConnection();
ensureAccountPermissions($conn);
$path = __DIR__ . '/permissions-fixture.json';
$mode = $argv[1] ?? 'setup';
if ($mode === 'setup') {
    $prefix = 'codex_access_' . bin2hex(random_bytes(5));
    $password = bin2hex(random_bytes(12));
    $username = $prefix . '_admin';
    $email = $username . '@example.invalid';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (username, full_name, email, password_hash, is_active, created_at) VALUES (?, 'Permission test admin', ?, ?, 1, NOW())");
    $stmt->bind_param('sss', $username, $email, $hash);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt = $conn->prepare("INSERT INTO account_permissions VALUES (?, 'admin', '{}')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    file_put_contents($path, json_encode(['prefix'=>$prefix,'username'=>$username,'password'=>$password,'id'=>$id]));
    echo "Disposable account fixture ready.\n";
} else {
    $fixture = json_decode(file_get_contents($path), true);
    $username = $fixture['prefix'] . '_staff';
    if ($mode === 'revoke') {
        $stmt = $conn->prepare("UPDATE account_permissions p JOIN admins a ON a.id=p.admin_id SET p.permissions_json='{}' WHERE a.username=?");
        $stmt->bind_param('s', $username); $stmt->execute();
    } elseif ($mode === 'deactivate') {
        $stmt = $conn->prepare('UPDATE admins SET is_active=0 WHERE username=?');
        $stmt->bind_param('s', $username); $stmt->execute();
    } elseif ($mode === 'cleanup') {
        $names = [$fixture['username'], $username, $fixture['prefix'].'_crud'];
        foreach ($names as $name) {
            $stmt = $conn->prepare('DELETE FROM admins WHERE username=?');
            $stmt->bind_param('s', $name); $stmt->execute();
        }
        unlink($path);
        echo "Disposable accounts removed.\n";
    }
}
