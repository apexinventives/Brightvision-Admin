<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

if (!canAccess('dashboard')) {
    include 'includes/header.php';
    include 'includes/sidebar.php';
    echo '<div class="p-4 sm:p-6 lg:p-8"><h1 class="text-3xl font-bold text-gray-800">Welcome, ' . htmlspecialchars($_SESSION['user_name']) . '</h1><p class="text-gray-500 mt-3">Use the navigation menu to open your permitted sections. Contact an administrator to change your access.</p></div>';
    include 'includes/footer.php';
    exit;
}

$conn = getConnection();

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_role = 'student'")->fetch_assoc()['count'];
$total_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
$pending_approvals = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE approved = 0")->fetch_assoc()['count'];
$today_exams = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE exam_date = CURDATE()")->fetch_assoc()['count'];

// Get recent reservations
$recent_reservations = $conn->query("
    SELECT r.*, u.name as student_name 
    FROM reservations r 
    LEFT JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC 
    LIMIT 5
");

// Get recent students
$recent_students = $conn->query("SELECT * FROM users WHERE user_role = 'student' ORDER BY created_at DESC LIMIT 5");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Dashboard Content -->
<div class="p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
        <div class="text-sm text-gray-600">
            <i class="far fa-calendar-alt mr-2"></i>
            <?php echo date('l, F j, Y'); ?>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <?php if (canAccess('dashboard', 'students') && canAccess('students')): ?>
<!-- Total Students -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Students</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $total_students; ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-users text-blue-500 text-xl"></i>
                </div>
            </div>
            <a href="users.php" class="text-sm text-blue-500 hover:text-blue-700 mt-2 inline-block">View all →</a>
        </div>
<?php endif; ?>
        
        <?php if (canAccess('dashboard', 'reservations') && canAccess('reservations')): ?>
<!-- Total Reservations -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Reservations</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $total_reservations; ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-calendar-check text-green-500 text-xl"></i>
                </div>
            </div>
            <a href="reservations.php" class="text-sm text-green-500 hover:text-green-700 mt-2 inline-block">View all →</a>
        </div>
<?php endif; ?>
        
        <?php if (canAccess('dashboard', 'pending') && canAccess('reservations')): ?>
<!-- Pending Approvals -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Pending Approvals</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $pending_approvals; ?></p>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                    <i class="fas fa-clock text-yellow-500 text-xl"></i>
                </div>
            </div>
            <a href="reservations.php?filter=pending" class="text-sm text-yellow-500 hover:text-yellow-700 mt-2 inline-block">Review →</a>
        </div>
<?php endif; ?>
        
        <?php if (canAccess('dashboard', 'exams') && canAccess('reservations')): ?>
<!-- Today's Exams -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Today's Exams</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $today_exams; ?></p>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <i class="fas fa-graduation-cap text-purple-500 text-xl"></i>
                </div>
            </div>
            <a href="reservations.php?filter=today" class="text-sm text-purple-500 hover:text-purple-700 mt-2 inline-block">View schedule →</a>
        </div>
<?php endif; ?>
    </div>
    
    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <?php if (canAccess('dashboard', 'registrations') && canAccess('students')): ?>
<!-- Monthly Registrations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Registrations</h2>
            <div class="h-64 flex items-end space-x-2">
                <?php
                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                foreach ($months as $month) {
                    $height = rand(40, 200); // This should be actual data from database
                    echo "<div class='flex-1 flex flex-col items-center'>";
                    echo "<div class='w-full bg-blue-500 rounded-t' style='height: {$height}px;'></div>";
                    echo "<span class='text-xs text-gray-600 mt-2'>{$month}</span>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
<?php endif; ?>
        
        <?php if (canAccess('dashboard', 'classes') && canAccess('students')): ?>
<!-- Class Distribution -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Students by Class</h2>
            <?php
            $classes = ['Grade 10', 'Grade 11', 'after_OL', 'after_AL', 'Adult_Course'];
            $colors = ['blue', 'green', 'yellow', 'purple', 'pink'];
            foreach ($classes as $index => $class) {
                $count = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_class = '$class'")->fetch_assoc()['count'];
                $percentage = $total_students > 0 ? round(($count / $total_students) * 100) : 0;
                ?>
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span><?php echo $class; ?></span>
                        <span><?php echo $count; ?> students (<?php echo $percentage; ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-<?php echo $colors[$index % count($colors)]; ?>-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
<?php endif; ?>
    </div>
    
    <!-- Recent Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php if (canAccess('dashboard', 'recent_students') && canAccess('students')): ?>
<!-- Recent Students -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Recent Students</h2>
                <a href="users.php" class="text-sm text-blue-500 hover:text-blue-700">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($student = $recent_students->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($student['name']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($student['user_class']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php endif; ?>
        
        <?php if (canAccess('dashboard', 'recent_reservations') && canAccess('reservations')): ?>
<!-- Recent Reservations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Recent Reservations</h2>
                <a href="reservations.php" class="text-sm text-blue-500 hover:text-blue-700">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Exam Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($reservation = $recent_reservations->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($reservation['full_name']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo date('M d, Y', strtotime($reservation['exam_date'])); ?></td>
                            <td class="px-4 py-3">
                                <?php if($reservation['approved']): ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>
