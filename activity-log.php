<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotAdmin();

$conn = getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total records
$total = $conn->query("SELECT COUNT(*) as count FROM admin_activity_log")->fetch_assoc()['count'];
$total_pages = ceil($total / $per_page);

// Get activity logs
$logs = $conn->query("
    SELECT a.*, admins.username, admins.full_name 
    FROM admin_activity_log a
    LEFT JOIN admins ON a.admin_id = admins.id
    ORDER BY a.created_at DESC
    LIMIT $offset, $per_page
");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Admin Activity Log</h1>
    
    <!-- Activity Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php while($log = $logs->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></div>
                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($log['username'] ?? 'deleted'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($log['action']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($log['details']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo $log['ip_address']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="flex justify-center mt-6">
        <div class="flex space-x-2">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="px-4 py-2 border rounded-lg <?php echo $i == $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>