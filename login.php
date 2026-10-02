<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getConnection();
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Check if user exists in admins table
    $stmt = $conn->prepare("SELECT id, username, full_name, email, password_hash, is_active FROM admins WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if ($user['is_active'] == 1) {
            if (password_verify($password, $user['password_hash'])) {
                ensureAccountPermissions($conn);
                $roleStmt = $conn->prepare('SELECT role FROM account_permissions WHERE admin_id = ?');
                $roleStmt->bind_param('i', $user['id']);
                $roleStmt->execute();
                $role = $roleStmt->get_result()->fetch_assoc()['role'];
                $roleStmt->close();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $role;
                
                // Update last login
                updateLastLogin($conn, $user['id']);
                
                header('Location: index.php');
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "Account is inactive. Please contact administrator.";
        }
    } else {
        $error = "User not found!";
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Teacher's Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-lg p-5 sm:p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Teacher's Panel</h1>
            <p class="text-gray-600 mt-2">Login to access the dashboard</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Username or Email</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" name="username" required 
                           class="pl-10 w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter username or email">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="password" required 
                           class="pl-10 w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter your password">
                </div>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                Sign In
            </button>
        </form>
        
    </div>
</body>
</html>
