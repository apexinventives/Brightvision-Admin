<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();

// Handle approval with SMS
if (isset($_POST['approve_with_sms'])) {
    $id = intval($_POST['id']);
    $phone = $_POST['phone'];
    $exam_date = $_POST['exam_date'];
    $exam_time = $_POST['exam_time'];
    $full_name = $_POST['full_name'];
    
    // Update approval status
    $update_stmt = $conn->prepare("UPDATE reservations SET approved = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $id);
    
	if ($update_stmt->execute()) {
		// Convert time from 24h to 12h format
		$exam_time_12h = date('h:i A', strtotime($exam_time)); 
		
		// Send SMS
		$msg = "Bright Vision English Academy \nDear $full_name,\nYour Exam Approved\n\n" . 
			   "Exam Date: " . $exam_date . "\nExam Time: " . $exam_time_12h . "\n\nPowered by apexinventives";
        
        $smsResponse = send_quicksend_sms_single("apexdigital", $phone, $msg);
        
        // Parse SMS response
        $responseData = json_decode($smsResponse, true);
        $smsStatus = isset($responseData['error']) ? 'Failed' : 'Sent';
        $smsMessage = isset($responseData['message']) ? $responseData['message'] : '';
        
        // Log to file
        $logEntry = date('Y-m-d H:i:s') . " | ID: $id | Phone: $phone | Status: $smsStatus | Response: " . substr($smsResponse, 0, 100) . "\n";
        file_put_contents('sms_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
        
        // Set session message for display
        $_SESSION['sms_result'] = [
            'status' => $smsStatus,
            'phone' => $phone,
            'message' => $smsMessage,
            'name' => $full_name
        ];
    }
    
	$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
	header("Location: reservations.php?page=$page");
	exit;
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $records_per_page;

// Sorting settings
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'exam_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$allowed_columns = ['id', 'full_name', 'exam_date', 'exam_time', 'created_at', 'approved', 'user_id'];
$allowed_orders = ['ASC', 'DESC'];

// Validate sort column and order
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'exam_date';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'DESC';
}

// Handle filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build base query
$count_query = "SELECT COUNT(*) as total FROM reservations r WHERE 1=1";
$query = "SELECT r.*, u.name as student_name, u.email as student_email 
          FROM reservations r 
          LEFT JOIN users u ON r.user_id = u.id 
          WHERE 1=1";

$params = [];
$types = "";

// Add filter conditions
if ($filter == 'pending') {
    $count_query .= " AND r.approved = 0";
    $query .= " AND r.approved = 0";
} elseif ($filter == 'approved') {
    $count_query .= " AND r.approved = 1";
    $query .= " AND r.approved = 1";
} elseif ($filter == 'today') {
    $count_query .= " AND r.exam_date = CURDATE()";
    $query .= " AND r.exam_date = CURDATE()";
}

// Add date range filter
if ($date_from) {
    $count_query .= " AND r.exam_date >= ?";
    $query .= " AND r.exam_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to) {
    $count_query .= " AND r.exam_date <= ?";
    $query .= " AND r.exam_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add search filter
if ($search) {
    $search_condition = " AND (r.full_name LIKE ? OR r.nic_number LIKE ? OR r.whatsapp_number LIKE ? OR u.name LIKE ?)";
    $count_query .= $search_condition;
    $query .= $search_condition;
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
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
$reservations = $stmt->get_result();

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

// SMS Sending Function
function send_quicksend_sms_single($senderID, $to, $msg)
{
    $username = "info.kawshalya@gmail.com";
    $password = "6242364296715faf9ea553721903698";

    $curl = curl_init();

    $postData = json_encode([
        "senderID" => $senderID,
        "to" => $to,
        "msg" => $msg,
    ]);

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://quicksend.lk/Client/api.php?FUN=SEND_SINGLE",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Basic " . base64_encode($username . ":" . $password),
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Exam Reservations</h1>
        <div class="flex space-x-2">
            <a href="reservations.php?filter=pending" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-clock mr-2"></i>
                Pending (<?php echo $conn->query("SELECT COUNT(*) as count FROM reservations WHERE approved = 0")->fetch_assoc()['count']; ?>)
            </a>
            <a href="reservations.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-list mr-2"></i>
                All Reservations
            </a>
        </div>
    </div>
    
    <!-- SMS Result Notification -->
    <?php if(isset($_SESSION['sms_result'])): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $_SESSION['sms_result']['status'] == 'Sent' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <?php if($_SESSION['sms_result']['status'] == 'Sent'): ?>
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                <?php endif; ?>
            </div>
            <div class="ml-3">
                <p class="font-medium">
                    SMS to <?php echo htmlspecialchars($_SESSION['sms_result']['name']); ?> (<?php echo $_SESSION['sms_result']['phone']; ?>): 
                    <strong><?php echo $_SESSION['sms_result']['status']; ?></strong>
                </p>
                <?php if($_SESSION['sms_result']['message']): ?>
                    <p class="text-sm mt-1"><?php echo htmlspecialchars($_SESSION['sms_result']['message']); ?></p>
                <?php endif; ?>
            </div>
            <div class="ml-auto">
                <button onclick="this.parentElement.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['sms_result']); ?>
    <?php endif; ?>
    
    <!-- Filters and Search -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name, NIC, phone..." 
                           class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Filter by Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="filter" class="border rounded-lg px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="today" <?php echo $filter == 'today' ? 'selected' : ''; ?>>Today's Exams</option>
                    </select>
                </div>
                
                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                           class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                           class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                <a href="reservations.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                    Clear
                </a>
            </div>
        </form>
    </div>
    
    <!-- Results Info -->
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-600">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> reservations
        </p>
        <p class="text-sm text-gray-600">
            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
        </p>
    </div>
    
    <!-- Reservations Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table id="reservations-table" class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="<?php echo getSortUrl('id', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                ID <?php echo getSortIcon('id', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="<?php echo getSortUrl('user_id', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Student <?php echo getSortIcon('user_id', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIC</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">WhatsApp</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="<?php echo getSortUrl('exam_date', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Exam Date <?php echo getSortIcon('exam_date', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="<?php echo getSortUrl('exam_time', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Exam Time <?php echo getSortIcon('exam_time', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participants</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="<?php echo getSortUrl('approved', $sort_column, $sort_order); ?>" class="flex items-center hover:text-gray-700">
                                Status <?php echo getSortIcon('approved', $sort_column, $sort_order); ?>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if($reservations->num_rows > 0): ?>
                        <?php while($reservation = $reservations->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4 whitespace-nowrap text-sm"><?php echo $reservation['id']; ?></td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($reservation['student_name'] ?? 'N/A'); ?></div>
                                <div class="text-xs text-gray-500">ID: <?php echo $reservation['user_id']; ?></div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($reservation['nic_number']); ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($reservation['whatsapp_number']); ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('Y-m-d', strtotime($reservation['exam_date'])); ?>
                                <?php if($reservation['exam_date'] == date('Y-m-d')): ?>
                                    <span class="ml-1 px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded-full">Today</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('h:i A', strtotime($reservation['exam_time'])); ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?php echo $reservation['participants']; ?></td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <?php if($reservation['approved']): ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <?php if(!$reservation['approved'] && canAccess('reservations', 'approve') && canAccess('reservations', 'sms')): ?>
                                        <button onclick="showSMSConfirm(<?php echo $reservation['id']; ?>, '<?php echo addslashes($reservation['full_name']); ?>', '<?php echo $reservation['whatsapp_number']; ?>', '<?php echo $reservation['exam_date']; ?>', '<?php echo $reservation['exam_time']; ?>')" 
                                                class="text-green-600 hover:text-green-900" title="Approve with SMS">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!$reservation['approved'] && canAccess('reservations', 'approve') && !canAccess('reservations', 'sms')): ?>
                                    <a href="approve-reservation.php?id=<?php echo (int)$reservation['id']; ?>" class="text-green-600" title="Approve"><i class="fas fa-check-circle"></i></a>
                                    <?php endif; ?>
                                    <a href="#" class="text-blue-600 hover:text-blue-900" title="View Details" 
                                       onclick='showDetails(<?php echo json_encode($reservation); ?>)'>
                                        <i class="fas fa-info-circle"></i>
                                    </a>
                                    <?php if (canAccess('reservations', 'delete')): ?>
                                    <a href="delete-reservation.php?id=<?php echo $reservation['id']; ?>" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirmDelete('Are you sure you want to delete this reservation?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                                No reservations found.
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

<!-- SMS Confirmation Modal -->
<div id="smsConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Confirm Approval with SMS</h3>
            <button onclick="closeSMSConfirm()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" id="smsConfirmForm">
            <div class="p-6">
                <p class="mb-4">You are about to approve:</p>
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <p><strong>Student:</strong> <span id="confirm_name"></span></p>
                    <p><strong>Phone:</strong> <span id="confirm_phone"></span></p>
                    <p><strong>Exam Date:</strong> <span id="confirm_date"></span></p>
                    <p><strong>Exam Time:</strong> <span id="confirm_time"></span></p>
                </div>
                <p class="text-sm text-gray-600 mb-4">An SMS notification will be sent to the student's WhatsApp number.</p>
                
                <input type="hidden" name="id" id="confirm_id">
                <input type="hidden" name="phone" id="confirm_phone_input">
                <input type="hidden" name="exam_date" id="confirm_date_input">
                <input type="hidden" name="exam_time" id="confirm_time_input">
                <input type="hidden" name="full_name" id="confirm_name_input">
                <input type="hidden" name="approve_with_sms" value="1">
            </div>
            <div class="flex justify-end p-6 border-t gap-2">
                <button type="button" onclick="closeSMSConfirm()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                    Cancel
                </button>
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    Approve & Send SMS
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">Reservation Details</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6" id="modalContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="flex justify-end p-6 border-t">
            <button onclick="closeModal()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                Close
            </button>
        </div>
    </div>
</div>

<!-- SMS Log Viewer Modal -->
<div id="smsLogModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4">
        <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-xl font-bold text-gray-800">SMS Log</h3>
            <button onclick="closeSMSLog()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 max-h-96 overflow-y-auto">
            <pre id="logContent" class="text-sm font-mono bg-gray-50 p-4 rounded-lg"></pre>
        </div>
        <div class="flex justify-end p-6 border-t gap-2">
            <button onclick="downloadSMSLog()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                <i class="fas fa-download mr-2"></i>
                Download Log
            </button>
            <button onclick="closeSMSLog()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function showSMSConfirm(id, name, phone, exam_date, exam_time) {
    document.getElementById('confirm_id').value = id;
    document.getElementById('confirm_phone_input').value = phone;
    document.getElementById('confirm_date_input').value = exam_date;
    document.getElementById('confirm_time_input').value = exam_time;
    document.getElementById('confirm_name_input').value = name;
    
    document.getElementById('confirm_name').textContent = name;
    document.getElementById('confirm_phone').textContent = phone;
    document.getElementById('confirm_date').textContent = exam_date;
    document.getElementById('confirm_time').textContent = exam_time;
    
    document.getElementById('smsConfirmModal').classList.remove('hidden');
    document.getElementById('smsConfirmModal').classList.add('flex');
}

function closeSMSConfirm() {
    document.getElementById('smsConfirmModal').classList.add('hidden');
    document.getElementById('smsConfirmModal').classList.remove('flex');
}

function showSMSLog() {
    fetch('get_sms_log.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('logContent').textContent = data || 'No log entries found.';
            document.getElementById('smsLogModal').classList.remove('hidden');
            document.getElementById('smsLogModal').classList.add('flex');
        })
        .catch(error => {
            alert('Error loading log: ' + error);
        });
}

function closeSMSLog() {
    document.getElementById('smsLogModal').classList.add('hidden');
    document.getElementById('smsLogModal').classList.remove('flex');
}

function downloadSMSLog() {
    const content = document.getElementById('logContent').textContent;
    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sms_log_' + new Date().toISOString().slice(0,10) + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function showDetails(reservation) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('modalContent');
    
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500">Full Name</p>
                <p class="font-medium">${escapeHtml(reservation.full_name)}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Cername</p>
                <p class="font-medium">${escapeHtml(reservation.cername)}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Call Name</p>
                <p class="font-medium">${escapeHtml(reservation.callname || 'N/A')}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Name with Initials</p>
                <p class="font-medium">${escapeHtml(reservation.name_with_initials || 'N/A')}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">NIC Number</p>
                <p class="font-medium">${escapeHtml(reservation.nic_number || 'N/A')}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">WhatsApp Number</p>
                <p class="font-medium">${escapeHtml(reservation.whatsapp_number || 'N/A')}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Participants</p>
                <p class="font-medium">${reservation.participants || 1}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Exam Date</p>
                <p class="font-medium">${reservation.exam_date}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Exam Time</p>
                <p class="font-medium">${formatTime(reservation.exam_time)}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="font-medium">
                    <span class="px-2 py-1 text-xs font-semibold rounded-full ${reservation.approved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                        ${reservation.approved ? 'Approved' : 'Pending'}
                    </span>
                </p>
            </div>
            <div class="col-span-2">
                <p class="text-sm text-gray-500">Postal Address</p>
                <p class="font-medium">${escapeHtml(reservation.postal_address || 'N/A')}</p>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('detailsModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function escapeHtml(text) {
    if (!text) return 'N/A';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(timeString) {
    if (!timeString) return 'N/A';
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

function confirmDelete(message) {
    return confirm(message);
}

// Close modal when clicking outside
document.getElementById('smsConfirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSMSConfirm();
    }
});

document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('smsLogModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSMSLog();
    }
});
</script>

<?php
$conn->close();
include 'includes/footer.php';
?>
