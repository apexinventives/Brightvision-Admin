<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getConnection();
    
    $name = $_POST['name'];
    $email = $_POST['email'];
    $whatsapp = $_POST['whatsapp'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_class = $_POST['user_class'];
    $joined_year = $_POST['joined_year'];
    $institute = $_POST['institute'];
    
    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, whatsapp, password, user_role, user_class, joined_year, institute) VALUES (?, ?, ?, ?, 'student', ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $email, $whatsapp, $password, $user_class, $joined_year, $institute);
        
        if ($stmt->execute()) {
            $message = "Student added successfully!";
        } else {
            $error = "Error adding student: " . $conn->error;
        }
        $stmt->close();
    }
    
    $check->close();
    $conn->close();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Add New Student</h2>
            
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
                    <input type="text" name="name" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" name="email" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number</label>
                    <input type="text" name="whatsapp" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                    <input type="password" name="password" required class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                    <select name="user_class" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="Grade 10">Grade 10</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="after_OL">After O/L</option>
                        <option value="after_AL">After A/L</option>
                        <option value="Adult_Course">Adult Course</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Joined Year</label>
                    <select name="joined_year" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for($year = date('Y'); $year >= 2015; $year--): ?>
                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Institute</label>
                    <input type="text" name="institute" value="Maxicon Institute" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="users.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>