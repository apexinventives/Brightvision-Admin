<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();
$message = '';
$error = '';
$upload_result = [];

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $upload_date = $_POST['upload_date'] ?: date('Y-m-d');
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error uploading file. Code: " . $file['error'];
    } else {
        // Check file type
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, ['csv', 'txt'])) {
            $error = "Please upload a CSV file.";
        } else {
            // Process CSV file
            $handle = fopen($file['tmp_name'], 'r');
            
            // Read header row
            $header = fgetcsv($handle);
            
            // Expected headers (adjust based on your CSV structure)
            $expected_headers = ['BV NO', 'name', 'user_id', 'INTRODUCTION WRITING','REVIEW', 'MERIT POINTS', 'DEMERIT POINTS', 'TOTAL'];
            
            // Validate headers (optional)
            // if ($header !== $expected_headers) {
            //     $error = "Invalid CSV format. Please use the correct template.";
            // } else {
                $row_count = 0;
                $inserted = 0;
                $updated = 0;
                $skipped = 0;
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Prepare insert/update statement
                    $stmt = $conn->prepare("
                        INSERT INTO bv_growth_meter 
                        (bv_no, name, user_id, introduction_writing, review, merit_points, demerit_points, total_points, upload_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        bv_no = VALUES(bv_no), 
                        name = VALUES(name),
                        introduction_writing = VALUES(introduction_writing),
                        review = VALUES(review),
                        merit_points = VALUES(merit_points),
                        demerit_points = VALUES(demerit_points),
                        total_points = VALUES(total_points)
                    ");
                    
                    // Read data rows
                    while (($data = fgetcsv($handle)) !== FALSE) { 
                        $row_count++;
                        
                        // Skip empty rows
                        if (count($data) < 8 || empty(array_filter($data))) {
                            $skipped++;
                            continue;
                        }
                        
                        // Map data to variables
                        $bv_no = trim($data[0] ?? '');
                        $name = trim($data[1] ?? '');
                        $user_id_val = trim($data[2] ?? '');
                        $intro_writing = intval($data[3] ?? 0);
                        $review = intval($data[4] ?? 0);
                        $merit_points = intval($data[5] ?? 0);
                        $demerit_points = intval($data[6] ?? 0);
                        $total = intval($data[7] ?? 0);
                        
                        // Validate required fields
                        if (empty($name) || empty($user_id_val)) {
                            $skipped++;
                            continue;
                        }
                        
                        // Bind parameters
                        $stmt->bind_param(
                            "sssiiiiis",
                            $bv_no,
                            $name,
                            $user_id_val,
                            $intro_writing,
                            $review,
                            $merit_points,
                            $demerit_points,
                            $total,
                            $upload_date
                        );
                        
                        if ($stmt->execute()) {
                            if ($stmt->affected_rows > 0) {
                                if ($stmt->affected_rows == 2) {
                                    $updated++;
                                } else {
                                    $inserted++;
                                }
                            }
                        } else {
                            throw new Exception("Error inserting row $row_count: " . $stmt->error);
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $message = "CSV uploaded successfully!";
                    $upload_result = [
                        'total_rows' => $row_count,
                        'inserted' => $inserted,
                        'updated' => $updated,
                        'skipped' => $skipped
                    ];
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error processing file: " . $e->getMessage();
                }
                
                $stmt->close();
            // }
            
            fclose($handle);
        }
    }
}

// Get filter parameters
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query for displaying data
$query = "SELECT * FROM bv_growth_meter WHERE upload_date = ?";
$params = [$filter_date];
$types = "s";

if ($search) {
    $query .= " AND (name LIKE ? OR user_id LIKE ? OR bv_no LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " ORDER BY bv_no + 0 ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$today_data = $stmt->get_result();

// Get available upload dates for dropdown
$dates = $conn->query("SELECT DISTINCT upload_date FROM bv_growth_meter ORDER BY upload_date DESC");

// Calculate summary statistics for selected date
$summary = $conn->prepare("
    SELECT 
        COUNT(*) as total_students,
        SUM(introduction_writing) as total_intro,
        SUM(review) as total_review,
        SUM(merit_points) as total_merit,
        SUM(demerit_points) as total_demerit,
        SUM(total_points) as grand_total
    FROM bv_growth_meter 
    WHERE upload_date = ?
");
$summary->bind_param("s", $filter_date);
$summary->execute();
$stats = $summary->get_result()->fetch_assoc();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">BV Growth Meter</h1>
        <div class="flex space-x-2">
            <a href="bv-growth-history.php" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-chart-line mr-2"></i>
                View History
            </a>
        </div>
    </div>
    
    <!-- Upload Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Upload Daily CSV</h2>
        
        <?php if($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
                <?php if(!empty($upload_result)): ?>
                    <div class="mt-2 text-sm">
                        <p>Total rows processed: <?php echo $upload_result['total_rows']; ?></p>
                        <p>New records inserted: <?php echo $upload_result['inserted']; ?></p>
                        <p>Records updated: <?php echo $upload_result['updated']; ?></p>
                        <p>Skipped rows: <?php echo $upload_result['skipped']; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Date</label>
                    <input type="date" name="upload_date" value="<?php echo date('Y-m-d'); ?>" 
                           class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required
                           class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-upload mr-2"></i>
                    Upload CSV
                </button>
                <a href="templates/bv-growth-template.csv" class="text-blue-600 hover:text-blue-800 flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    Download Template
                </a>
            </div>
        </form>
    </div>
    
    <!-- Data View Section -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">Daily Data View</h2>
            
            <!-- Date Selector -->
            <form method="GET" class="flex items-center space-x-2">
                <select name="date" class="border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                    <?php while($date = $dates->fetch_assoc()): ?>
                        <option value="<?php echo $date['upload_date']; ?>" <?php echo $filter_date == $date['upload_date'] ? 'selected' : ''; ?>>
                            <?php echo date('F j, Y', strtotime($date['upload_date'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <input type="text" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>"
                       class="border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-search"></i>
                </button>
                
                <?php if($search): ?>
                    <a href="bv-growth-upload.php?date=<?php echo $filter_date; ?>" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg">
                <p class="text-sm text-blue-600">Total Students</p>
                <p class="text-2xl font-bold text-blue-800"><?php echo $stats['total_students'] ?? 0; ?></p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <p class="text-sm text-green-600">Intro Writing</p>
                <p class="text-2xl font-bold text-green-800"><?php echo $stats['total_intro'] ?? 0; ?></p>
            </div>           
			<div class="bg-green-50 p-4 rounded-lg">
                <p class="text-sm text-green-600">Review</p>
                <p class="text-2xl font-bold text-green-800"><?php echo $stats['review'] ?? 0; ?></p>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
                <p class="text-sm text-purple-600">Merit Points</p>
                <p class="text-2xl font-bold text-purple-800"><?php echo $stats['total_merit'] ?? 0; ?></p>
            </div>
            <div class="bg-red-50 p-4 rounded-lg">
                <p class="text-sm text-red-600">Demerit Points</p>
                <p class="text-2xl font-bold text-red-800"><?php echo $stats['total_demerit'] ?? 0; ?></p>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <p class="text-sm text-yellow-600">Grand Total</p>
                <p class="text-2xl font-bold text-yellow-800"><?php echo $stats['grand_total'] ?? 0; ?></p>
            </div>
        </div>
        
        <!-- Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BV No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Intro Writing</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Review</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Merit Points</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Demerit Points</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if($today_data->num_rows > 0): ?>
                        <?php while($row = $today_data->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['bv_no']); ?></td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($row['user_id']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo $row['introduction_writing']; ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo $row['review']; ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo $row['merit_points']; ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo $row['demerit_points']; ?></td>
                            <td class="px-4 py-3 text-sm font-bold text-blue-600"><?php echo $row['total_points']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No data available for <?php echo date('F j, Y', strtotime($filter_date)); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>