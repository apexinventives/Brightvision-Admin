<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();

// Get date range
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$start_date = date('Y-m-d', strtotime("-$days days"));

// Get student filter
$student_filter = isset($_GET['student']) ? $_GET['student'] : '';

// Build query for historical data
$query = "SELECT * FROM bv_growth_meter WHERE upload_date >= ?";
$params = [$start_date];
$types = "s";

if ($student_filter) {
    $query .= " AND (name LIKE ? OR user_id LIKE ?)";
    $student_param = "%$student_filter%";
    $params[] = $student_param;
    $params[] = $student_param;
    $types .= "ss";
}

$query .= " ORDER BY upload_date DESC, bv_no + 0 ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result();

// Get unique students for dropdown
$students = $conn->query("SELECT DISTINCT name, user_id FROM bv_growth_meter ORDER BY name");

// Get daily totals for chart
$daily_totals = $conn->prepare("
    SELECT upload_date, 
           COUNT(*) as student_count,
           SUM(total_points) as daily_total
    FROM bv_growth_meter 
    WHERE upload_date >= ?
    GROUP BY upload_date 
    ORDER BY upload_date DESC
");
$daily_totals->bind_param("s", $start_date);
$daily_totals->execute();
$daily_stats = $daily_totals->get_result();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">BV Growth History</h1>
        <a href="bv-growth-upload.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Upload
        </a>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Days Range</label>
                <select name="days" class="border rounded-lg px-4 py-2 w-40">
                    <option value="7" <?php echo $days == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                    <option value="30" <?php echo $days == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                    <option value="90" <?php echo $days == 90 ? 'selected' : ''; ?>>Last 90 days</option>
                    <option value="365" <?php echo $days == 365 ? 'selected' : ''; ?>>Last year</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter Student</label>
                <select name="student" class="border rounded-lg px-4 py-2 w-64">
                    <option value="">All Students</option>
                    <?php while($student = $students->fetch_assoc()): ?>
                        <option value="<?php echo $student['user_id']; ?>" <?php echo $student_filter == $student['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Apply Filters
                </button>
                <a href="bv-growth-history.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400">
                    Clear
                </a>
            </div>
        </form>
    </div>
    
    <!-- Daily Stats Chart -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daily Performance</h2>
        <div class="h-80">
            <canvas id="growthChart"></canvas>
        </div>
    </div>
    
    <!-- History Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BV No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Intro</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Merit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Demerit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php 
                    $prev_totals = [];
                    while($row = $history->fetch_assoc()): 
                        $user_id = $row['user_id'];
                        $prev_total = $prev_totals[$user_id] ?? null;
                        $change = $prev_total ? $row['total_points'] - $prev_total : 0;
                        $prev_totals[$user_id] = $row['total_points'];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo date('Y-m-d', strtotime($row['upload_date'])); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['bv_no']); ?></td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['name']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo $row['introduction_writing']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo $row['merit_points']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo $row['demerit_points']; ?></td>
                        <td class="px-4 py-3 text-sm font-bold text-blue-600"><?php echo $row['total_points']; ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php if($change > 0): ?>
                                <span class="text-green-600">+<?php echo $change; ?></span>
                            <?php elseif($change < 0): ?>
                                <span class="text-red-600"><?php echo $change; ?></span>
                            <?php else: ?>
                                <span class="text-gray-400">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js for visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for chart
const dates = [];
const totals = [];
const counts = [];

<?php 
$daily_stats->data_seek(0);
while($stat = $daily_stats->fetch_assoc()): 
?>
dates.push('<?php echo date('M d', strtotime($stat['upload_date'])); ?>');
totals.push(<?php echo $stat['daily_total'] ?? 0; ?>);
counts.push(<?php echo $stat['student_count'] ?? 0; ?>);
<?php endwhile; ?>

// Create chart
const ctx = document.getElementById('growthChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates.reverse(),
        datasets: [
            {
                label: 'Total Points',
                data: totals.reverse(),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.1,
                yAxisID: 'y'
            },
            {
                label: 'Student Count',
                data: counts.reverse(),
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.1,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Total Points'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Student Count'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
</script>

<?php
$conn->close();
include 'includes/footer.php';
?>