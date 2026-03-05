<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();
$user_id = isset($_GET['id']) ? $_GET['id'] : 0;

$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$reservations = $conn->query("SELECT * FROM reservations WHERE user_id = $user_id ORDER BY exam_date DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <div class="max-w-4xl mx-auto">
        <!-- User Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4">
                <h2 class="text-xl font-bold text-white">Student Details</h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Full Name</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">WhatsApp</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['whatsapp']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Class</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['user_class']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Joined Year</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['joined_year']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Institute</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['institute']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Registered On</p>
                        <p class="font-medium"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User's Reservations -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Exam Reservations</h3>
            </div>
            
            <?php if($reservations->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIC</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while($res = $reservations->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm"><?php echo date('Y-m-d', strtotime($res['exam_date'])); ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo date('h:i A', strtotime($res['exam_time'])); ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($res['nic_number']); ?></td>
                                <td class="px-4 py-3">
                                    <?php if($res['approved']): ?>
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
            <?php else: ?>
                <div class="p-6 text-center text-gray-500">
                    No exam reservations found.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-6 flex justify-end">
            <a href="users.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                Back to Students
            </a>
        </div>
    </div>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>