<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();
$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get admin details from your admins table
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        
        // Check if username exists for other users
        $check_username = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $check_username->bind_param("si", $username, $admin_id);
        $check_username->execute();
        $check_username->store_result();
        
        // Check if email exists for other users
        $check_email = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $admin_id);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_username->num_rows > 0) {
            $error = "Username already exists!";
        } elseif ($check_email->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            $update = $conn->prepare("UPDATE admins SET full_name = ?, email = ?, username = ? WHERE id = ?");
            $update->bind_param("sssi", $full_name, $email, $username, $admin_id);
            
            if ($update->execute()) {
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['username'] = $username;
                $message = "Profile updated successfully!";
                
                // Refresh admin data
                $admin['full_name'] = $full_name;
                $admin['email'] = $email;
                $admin['username'] = $username;
            } else {
                $error = "Error updating profile: " . $conn->error;
            }
            $update->close();
        }
        $check_username->close();
        $check_email->close();
    }
    
    // Handle password change
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password, $admin['password_hash'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                    $update->bind_param("si", $password_hash, $admin_id);
                    
                    if ($update->execute()) {
                        $message = "Password changed successfully!";
                    } else {
                        $error = "Error changing password: " . $conn->error;
                    }
                    $update->close();
                } else {
                    $error = "Password must be at least 8 characters long!";
                }
            } else {
                $error = "New passwords do not match!";
            }
        } else {
            $error = "Current password is incorrect!";
        }
    }
    
    // Handle profile picture upload
    elseif (isset($_POST['update_avatar']) && isset($_FILES['avatar'])) {
        $target_dir = "uploads/avatars/";
        
        // Create directory if not exists
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = "admin_" . $admin_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                // Delete old avatar if exists
                if ($admin['avatar'] && $admin['avatar'] != 'NULL' && file_exists($target_dir . $admin['avatar'])) {
                    unlink($target_dir . $admin['avatar']);
                }
                
                $update = $conn->prepare("UPDATE admins SET avatar = ? WHERE id = ?");
                $update->bind_param("si", $new_filename, $admin_id);
                
                if ($update->execute()) {
                    $message = "Profile picture updated successfully!";
                    $admin['avatar'] = $new_filename;
                }
                $update->close();
            } else {
                $error = "Error uploading file!";
            }
        } else {
            $error = "Only JPG, JPEG, PNG & GIF files are allowed!";
        }
    }
}

// Get last login info
$last_login = $admin['last_login'] ? date('F j, Y H:i:s', strtotime($admin['last_login'])) : 'Never';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">My Profile</h1>
        <div class="text-sm text-gray-600">
            Last login: <?php echo $last_login; ?>
        </div>
    </div>
    
    <?php if($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
            <span><i class="fas fa-check-circle mr-2"></i><?php echo $message; ?></span>
            <button onclick="this.parentElement.remove()" class="text-green-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
            <span><i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Profile Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Profile Information Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Profile Information</h2>
                </div>
                
                <div class="p-6">
                    <form method="POST" class="space-y-4"><fieldset <?php echo canAccess('profile', 'edit') ? '' : 'disabled'; ?>>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin ID</label>
                                <input type="text" value="<?php echo $admin['id']; ?>" 
                                       class="w-full border rounded-lg px-4 py-2 bg-gray-100" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Member Since</label>
                                <input type="text" value="<?php echo date('F j, Y', strtotime($admin['created_at'])); ?>" 
                                       class="w-full border rounded-lg px-4 py-2 bg-gray-100" readonly>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required
                                   class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="update_profile" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i>
                                Update Profile
                            </button>
                        </div>
                    </fieldset></form>
                </div>
            </div>
            
            <!-- Change Password Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Change Password</h2>
                </div>
                
                <div class="p-6">
                    <form method="POST" class="space-y-4" onsubmit="return validatePassword()"><fieldset <?php echo canAccess('profile', 'edit') ? '' : 'disabled'; ?>>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <div class="relative">
                                <input type="password" name="current_password" id="current_password" required
                                       class="w-full border rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-green-500">
                                <button type="button" onclick="togglePassword('current_password')" 
                                        class="absolute right-3 top-3 text-gray-400">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <div class="relative">
                                <input type="password" name="new_password" id="new_password" required
                                       class="w-full border rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-green-500">
                                <button type="button" onclick="togglePassword('new_password')" 
                                        class="absolute right-3 top-3 text-gray-400">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-2 text-sm">
                                <div class="password-requirement" id="length-check">
                                    <i class="fas fa-circle text-gray-400 mr-2"></i> At least 8 characters
                                </div>
                                <div class="password-requirement" id="uppercase-check">
                                    <i class="fas fa-circle text-gray-400 mr-2"></i> One uppercase letter
                                </div>
                                <div class="password-requirement" id="number-check">
                                    <i class="fas fa-circle text-gray-400 mr-2"></i> One number
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" required
                                       class="w-full border rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-green-500">
                                <button type="button" onclick="togglePassword('confirm_password')" 
                                        class="absolute right-3 top-3 text-gray-400">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password-match" class="text-sm mt-1 hidden"></div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="change_password" 
                                    class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-key mr-2"></i>
                                Change Password
                            </button>
                        </div>
                    </fieldset></form>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Avatar & Status -->
        <div class="space-y-6">
            <!-- Profile Picture Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-600 to-yellow-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Profile Picture</h2>
                </div>
                
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="w-32 h-32 mx-auto rounded-full overflow-hidden border-4 border-gray-200">
                            <?php 
                            $avatar_path = "uploads/avatars/" . $admin['avatar'];
                            if(!empty($admin['avatar']) && $admin['avatar'] != 'NULL' && file_exists($avatar_path)): 
                            ?>
                                <img src="<?php echo $avatar_path; ?>?t=<?php echo time(); ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full bg-blue-500 flex items-center justify-center">
                                    <span class="text-4xl text-white font-bold">
                                        <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data"><fieldset <?php echo canAccess('profile', 'edit') ? '' : 'disabled'; ?>>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Upload New Picture</label>
                            <input type="file" name="avatar" accept="image/*" required
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                        <button type="submit" name="update_avatar" 
                                class="w-full bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700">
                            <i class="fas fa-upload mr-2"></i>
                            Upload Picture
                        </button>
                    </fieldset></form>
                </div>
            </div>
            
            <!-- Account Status Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-gray-600 to-gray-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Account Status</h2>
                </div>
                
                <div class="p-6">
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Account Status</span>
                            <?php if($admin['is_active']): ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-circle text-xs mr-1"></i> Active
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                    <i class="fas fa-circle text-xs mr-1"></i> Inactive
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Last Login</span>
                            <span class="text-sm font-medium">
                                <?php echo $last_login; ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Account Created</span>
                            <span class="text-sm font-medium">
                                <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Card -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-red-600 to-red-800 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Quick Actions</h2>
                </div>
                
                <div class="p-6">
                    <div class="space-y-2">
                        <button onclick="showSessionInfo()" class="block w-full text-left px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="fas fa-info-circle mr-2 text-gray-600"></i>
                            Session Information
                        </button>
                        <button onclick="exportProfile()" class="block w-full text-left px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="fas fa-download mr-2 text-gray-600"></i>
                            Export Profile Data
                        </button>
                        <hr class="my-2">
                        <?php if (canAccess('logout')): ?><a href="logout.php" class="block w-full text-left px-4 py-2 bg-red-50 hover:bg-red-100 rounded-lg transition-colors text-red-600">
                            <i class="fas fa-sign-out-alt mr-2"></i>
                            Logout
                        </a><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Session Info Modal -->
<div id="sessionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Session Information</h3>
            <button onclick="closeSessionModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Session ID</span>
                    <span class="text-sm font-mono"><?php echo session_id(); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">User ID</span>
                    <span class="text-sm font-medium"><?php echo $_SESSION['user_id']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Username</span>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['username'] ?? $admin['username']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">IP Address</span>
                    <span class="text-sm font-medium"><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">User Agent</span>
                    <span class="text-sm text-gray-500 truncate max-w-[200px]"><?php echo $_SERVER['HTTP_USER_AGENT']; ?></span>
                </div>
            </div>
        </div>
        <div class="flex justify-end p-6 border-t">
            <button onclick="closeSessionModal()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.type === 'password' ? 'text' : 'password';
    field.type = type;
}

// Password validation
document.getElementById('new_password')?.addEventListener('input', function() {
    const password = this.value;
    
    // Length check
    const lengthCheck = document.getElementById('length-check');
    if (password.length >= 8) {
        lengthCheck.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i> At least 8 characters';
    } else {
        lengthCheck.innerHTML = '<i class="fas fa-circle text-gray-400 mr-2"></i> At least 8 characters';
    }
    
    // Uppercase check
    const uppercaseCheck = document.getElementById('uppercase-check');
    if (/[A-Z]/.test(password)) {
        uppercaseCheck.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i> One uppercase letter';
    } else {
        uppercaseCheck.innerHTML = '<i class="fas fa-circle text-gray-400 mr-2"></i> One uppercase letter';
    }
    
    // Number check
    const numberCheck = document.getElementById('number-check');
    if (/[0-9]/.test(password)) {
        numberCheck.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i> One number';
    } else {
        numberCheck.innerHTML = '<i class="fas fa-circle text-gray-400 mr-2"></i> One number';
    }
});

// Password match validation
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = this.value;
    const matchDiv = document.getElementById('password-match');
    
    if (confirmPass) {
        matchDiv.classList.remove('hidden');
        if (newPass === confirmPass) {
            matchDiv.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i> Passwords match';
            matchDiv.className = 'text-sm mt-1 text-green-600';
        } else {
            matchDiv.innerHTML = '<i class="fas fa-exclamation-circle text-red-500 mr-2"></i> Passwords do not match';
            matchDiv.className = 'text-sm mt-1 text-red-600';
        }
    } else {
        matchDiv.classList.add('hidden');
    }
});

// Validate password before submit
function validatePassword() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (newPass.length < 8) {
        alert('Password must be at least 8 characters long!');
        return false;
    }
    
    return true;
}

// Session modal functions
function showSessionInfo() {
    document.getElementById('sessionModal').classList.remove('hidden');
    document.getElementById('sessionModal').classList.add('flex');
}

function closeSessionModal() {
    document.getElementById('sessionModal').classList.add('hidden');
    document.getElementById('sessionModal').classList.remove('flex');
}

// Export profile data
function exportProfile() {
    const profileData = {
        id: '<?php echo $admin['id']; ?>',
        username: '<?php echo $admin['username']; ?>',
        full_name: '<?php echo $admin['full_name']; ?>',
        email: '<?php echo $admin['email']; ?>',
        created_at: '<?php echo $admin['created_at']; ?>',
        last_login: '<?php echo $admin['last_login']; ?>',
        is_active: '<?php echo $admin['is_active']; ?>'
    };
    
    const dataStr = JSON.stringify(profileData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const exportFileDefaultName = 'profile_<?php echo $admin['username']; ?>_' + new Date().toISOString().slice(0,10) + '.json';
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
}

// Close modal when clicking outside
document.getElementById('sessionModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeSessionModal();
    }
});
</script>

<style>
.password-requirement {
    @apply text-xs text-gray-600 mt-1;
}
</style>

<?php
$conn->close();
include 'includes/footer.php';
?>