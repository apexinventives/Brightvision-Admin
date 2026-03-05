<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Sorting settings
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$allowed_columns = ['id', 'name', 'email','userid', 'user_class', 'joined_year', 'created_at'];
$allowed_orders = ['ASC', 'DESC'];

// Validate sort column and order
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'created_at';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'DESC';
}

// Handle filters
$class_filter = isset($_GET['class']) ? $_GET['class'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query for count
$count_query = "SELECT COUNT(*) as total FROM users WHERE user_role = 'student'";
$query = "SELECT * FROM users WHERE user_role = 'student'";

$params = [];
$types = "";

// Add search condition
if ($search) {
    // Fixed: Added missing OR operator and corrected the pattern
    $search_condition = " AND (name LIKE ? OR email LIKE ? OR user_id LIKE ? OR whatsapp LIKE ?)";
    $count_query .= $search_condition;
    $query .= $search_condition;
    $search_param = "%$search%";
    $params[] = $search_param; // for name
    $params[] = $search_param; // for email
    $params[] = $search_param; // for user_id
    $params[] = $search_param; // for whatsapp
    $types .= "ssss"; // Changed from "sss" to "ssss" for 4 parameters
}

// Add class filter
if ($class_filter) {
    $condition = " AND user_class = ?";
    $count_query .= $condition;
    $query .= $condition;
    $params[] = $class_filter;
    $types .= "s";
}

// Add year filter
if ($year_filter) {
    $condition = " AND joined_year = ?";
    $count_query .= $condition;
    $query .= $condition;
    $params[] = $year_filter;
    $types .= "s";
}

// Get total records for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Add sorting and pagination to main query
$query .= " ORDER BY $sort_column $sort_order LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get unique classes and years for filters
$classes = $conn->query("SELECT DISTINCT user_class FROM users WHERE user_role = 'student' AND user_class IS NOT NULL ORDER BY user_class");
$years = $conn->query("SELECT DISTINCT joined_year FROM users WHERE user_role = 'student' AND joined_year IS NOT NULL ORDER BY joined_year DESC");

// Function to generate sort URL
function getSortUrl($column, $current_sort, $current_order) {
    $new_order = ($column == $current_sort && $current_order == 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $new_order;
    return '?' . http_build_query($params);
}

// Function to get sort icon
function getSortIcon($column, $current_sort, $current_order) {
    if ($column != $current_sort) {
        return '<i class="fas fa-sort text-gray-400 ml-1"></i>';
    }
    return $current_order == 'ASC' ? 
        '<i class="fas fa-sort-up text-blue-600 ml-1"></i>' : 
        '<i class="fas fa-sort-down text-blue-600 ml-1"></i>';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Students Management</h1>
        <a href="add-user.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Add New Student
        </a>
    </div>
    
    <!-- Filters and Search -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name, email, phone..." 
                           class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Class Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Class</label>
                    <select name="class" class="border rounded-lg px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Classes</option>
                        <?php while($class = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $class['user_class']; ?>" <?php echo $class_filter == $class['user_class'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['user_class']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Year Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Year</label>
                    <select name="year" class="border rounded-lg px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Years</option>
                        <?php while($year = $years->fetch_assoc()): ?>
                            <option value="<?php echo $year['joined_year']; ?>" <?php echo $year_filter == $year['joined_year'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['joined_year']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Records Per Page -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Show</label>
                    <select name="per_page" class="border rounded-lg px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10 records</option>
                        <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25 records</option>
                        <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50 records</option>
                        <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100 records</option>
                    </select>
                </div>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Apply Filters
                </button>
                <a href="users.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                    Clear
                </a>
            </div>
        </form>
    </div>
    
    <!-- Results Info -->
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-600">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> students
        </p>
        <p class="text-sm text-gray-600">
            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
        </p>
    </div>
    
    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('id', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                ID <?php echo getSortIcon('id', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('name', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Name <?php echo getSortIcon('name', $sort_column, $sort_order); ?>
                            </a>
                        </th>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('user_id', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                BV SYS ID <?php echo getSortIcon('user_id', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('email', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Email <?php echo getSortIcon('email', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WhatsApp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('user_class', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Class <?php echo getSortIcon('user_class', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('joined_year', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Year <?php echo getSortIcon('joined_year', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Institute</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('created_at', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Joined <?php echo getSortIcon('created_at', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if($users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                            </td>
							<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['whatsapp']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['user_class']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['joined_year']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['institute']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="view-user.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete-user.php?id=<?php echo $user['id']; ?>" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirmDelete('Are you sure you want to delete this student?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                No students found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="flex justify-center mt-6">
        <nav class="flex items-center space-x-2">
            <!-- First Page -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
               class="px-3 py-2 rounded-lg <?php echo $page <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border">
                <i class="fas fa-angle-double-left"></i>
            </a>
            
            <!-- Previous Page -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>" 
               class="px-3 py-2 rounded-lg <?php echo $page <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border">
                <i class="fas fa-angle-left"></i>
            </a>
            
            <!-- Page Numbers -->
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for($i = $start; $i <= $end; $i++):
            ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="px-4 py-2 rounded-lg border <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <!-- Next Page -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>" 
               class="px-3 py-2 rounded-lg <?php echo $page >= $total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border">
                <i class="fas fa-angle-right"></i>
            </a>
            
            <!-- Last Page -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
               class="px-3 py-2 rounded-lg <?php echo $page >= $total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border">
                <i class="fas fa-angle-double-right"></i>
            </a>
        </nav>
    </div>
    
    <!-- Page Jump -->
    <div class="flex justify-center mt-4">
        <form method="GET" class="flex items-center space-x-2">
            <?php foreach($_GET as $key => $value): ?>
                <?php if($key != 'page'): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <span class="text-sm text-gray-600">Go to page:</span>
            <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $page; ?>" 
                   class="w-16 border rounded-lg px-2 py-1 text-center focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 text-white px-4 py-1 rounded-lg hover:bg-blue-700 text-sm">
                Go
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php
$conn->close();
include 'includes/footer.php';
?>