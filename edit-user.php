<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();
$user_id = isset($_GET['id']) ? $_GET['id'] : 0;
$message = '';
$error = '';

// Get user data
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $whatsapp = $_POST['whatsapp'];
    $user_class = $_POST['user_class'];
    $joined_year = $_POST['joined_year'];
    $institute = $_POST['institute'];
    
    // Check if email exists for other users
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $error = "Email already exists for another user!";
    } else {
        if (!empty($_POST['password'])) {
            // Update with password
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, whatsapp=?, password=?, user_class=?, joined_year=?, institute=? WHERE id=?");
            $stmt->bind_param("sssssssi", $name, $email, $whatsapp, $password, $user_class, $joined_year, $institute, $user_id);
        } else {
            // Update without password
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, whatsapp=?, user_class=?, joined_year=?, institute=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $email, $whatsapp, $user_class, $joined_year, $institute, $user_id);
        }
        
        if ($stmt->execute()) {
            $message = "Student updated successfully!";
            // Refresh user data
            $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
        } else {
            $error = "Error updating student: " . $conn->error;
        }
        $stmt->close();
    }
    
    $check->close();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit Student</h2>
            
            <?php if($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number</label>
                    <input type="text" name="whatsapp" value="<?php echo htmlspecialchars($user['whatsapp']); ?>" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                    <select name="user_class" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="Grade 10" <?php echo $user['user_class'] == 'Grade 10' ? 'selected' : ''; ?>>Grade 10</option>
                        <option value="Grade 11" <?php echo $user['user_class'] == 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                        <option value="after_OL" <?php echo $user['user_class'] == 'after_OL' ? 'selected' : ''; ?>>After O/L</option>
                        <option value="after_AL" <?php echo $user['user_class'] == 'after_AL' ? 'selected' : ''; ?>>After A/L</option>
                        <option value="Adult_Course" <?php echo $user['user_class'] == 'Adult_Course' ? 'selected' : ''; ?>>Adult Course</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Joined Year</label>
                    <select name="joined_year" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for($year = date('Y'); $year >= 2015; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $user['joined_year'] == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Institute</label>
                    <input type="text" name="institute" value="<?php echo htmlspecialchars($user['institute']); ?>" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="users.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        Update Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>