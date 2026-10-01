<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/simple-pdf.php';
redirectIfNotLoggedIn();

$conn = getConnection();
$methodLabels = ['full' => 'Full', 'half' => 'Half', 'quarter' => 'Quarter', 'scholarship' => 'Scholarship', 'free_card' => 'Free Card'];
$search = trim($_GET['search'] ?? '');
$courseId = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?: 0;
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$format = $_GET['format'] ?? '';
if (!in_array($status, ['', 'paid', 'pending', 'overdue'], true)) $status = '';
if ($dateFrom && !DateTime::createFromFormat('Y-m-d', $dateFrom)) $dateFrom = '';
if ($dateTo && !DateTime::createFromFormat('Y-m-d', $dateTo)) $dateTo = '';

$where = ['1=1'];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(p.student_name LIKE ? OR p.student_id_number LIKE ? OR c.course_name LIKE ? OR c.course_number LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
if ($courseId) { $where[] = 'p.course_id = ?'; $params[] = $courseId; $types .= 'i'; }
if ($dateFrom) { $where[] = 'DATE(p.created_at) >= ?'; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo) { $where[] = 'DATE(p.created_at) <= ?'; $params[] = $dateTo; $types .= 's'; }
if ($status === 'paid') $where[] = 'NOT EXISTS (SELECT 1 FROM payment_installments x WHERE x.payment_plan_id = p.id AND x.is_paid = 0)';
if ($status === 'pending') $where[] = 'EXISTS (SELECT 1 FROM payment_installments x WHERE x.payment_plan_id = p.id AND x.is_paid = 0 AND x.payment_date >= CURDATE())';
if ($status === 'overdue') $where[] = 'EXISTS (SELECT 1 FROM payment_installments x WHERE x.payment_plan_id = p.id AND x.is_paid = 0 AND x.payment_date < CURDATE())';

$sql = "SELECT p.*, c.course_number, c.course_name, COUNT(i.id) installment_count, SUM(i.is_paid) paid_count,
        COALESCE(SUM(CASE WHEN i.is_paid = 1 THEN i.amount ELSE 0 END), 0) paid_amount,
        MIN(CASE WHEN i.is_paid = 0 THEN i.payment_date END) next_due_date
    FROM payment_plans p
    JOIN payment_installments i ON i.payment_plan_id = p.id
    LEFT JOIN payment_courses c ON c.id = p.course_id
    WHERE " . implode(' AND ', $where) . " GROUP BY p.id ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$summary = ['plans' => count($rows), 'total' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
foreach ($rows as &$row) {
    $row['balance'] = max(0, (float)$row['total_amount'] - (float)$row['paid_amount']);
    $isPaid = (int)$row['paid_count'] === (int)$row['installment_count'];
    $isOverdue = !$isPaid && $row['next_due_date'] && $row['next_due_date'] < date('Y-m-d');
    $row['status_label'] = $isPaid ? 'Paid' : ($isOverdue ? 'Overdue' : 'Pending');
    $row['method_label'] = $methodLabels[$row['payment_method']] ?? $row['payment_method'];
    $summary['total'] += (float)$row['total_amount'];
    $summary['paid'] += (float)$row['paid_amount'];
    $summary['balance'] += $row['balance'];
}
unset($row);

$filterParts = [];
if ($search) $filterParts[] = 'Search: ' . $search;
if ($courseId) {
    $courseFilterLabel = 'Course ID ' . $courseId;
    if ($rows && !empty($rows[0]['course_number'])) $courseFilterLabel = $rows[0]['course_number'] . ' - ' . $rows[0]['course_name'];
    $filterParts[] = 'Course: ' . $courseFilterLabel;
}
if ($status) $filterParts[] = 'Status: ' . ucfirst($status);
if ($dateFrom) $filterParts[] = 'From: ' . $dateFrom;
if ($dateTo) $filterParts[] = 'To: ' . $dateTo;
$filterDescription = $filterParts ? implode(' | ', $filterParts) : 'All payment records';
$filenameDate = date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="payment-report-' . $filenameDate . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Student ID', 'Student Name', 'Course Number', 'Course Name', 'Payment Method', 'Total Amount', 'Paid Amount', 'Balance', 'Paid Installments', 'Total Installments', 'Next Payment Date', 'Status', 'Created Date']);
    foreach ($rows as $row) {
        fputcsv($output, [$row['student_id_number'], $row['student_name'], $row['course_number'], $row['course_name'], $row['method_label'], $row['total_amount'], $row['paid_amount'], $row['balance'], $row['paid_count'], $row['installment_count'], $row['next_due_date'], $row['status_label'], date('Y-m-d', strtotime($row['created_at']))]);
    }
    fclose($output);
    exit;
}
if ($format === 'pdf') {
    $pdf = createPaymentReportPdf($rows, $summary, $filterDescription);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="payment-report-' . $filenameDate . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$courses = $conn->query("SELECT id, course_number, course_name FROM payment_courses ORDER BY course_name")->fetch_all(MYSQLI_ASSOC);
$exportParams = array_filter(
    ['search' => $search, 'course_id' => $courseId, 'status' => $status, 'date_from' => $dateFrom, 'date_to' => $dateTo],
    function ($value) { return $value !== '' && $value !== 0; }
);
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-8">
        <div><h1 class="text-3xl font-bold text-gray-800">Payment Reports</h1><p class="text-gray-500 mt-1">Filter, review, and export student payment information.</p></div>
        <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
            <a href="?<?php echo htmlspecialchars(http_build_query($exportParams + ['format' => 'csv'])); ?>" class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2.5 rounded-lg text-center w-full sm:w-auto"><i class="fas fa-file-csv mr-2"></i>Export CSV</a>
            <a href="?<?php echo htmlspecialchars(http_build_query($exportParams + ['format' => 'pdf'])); ?>" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg text-center w-full sm:w-auto"><i class="fas fa-file-pdf mr-2"></i>Export PDF</a>
        </div>
    </div>
    <form method="GET" class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Search</label><input name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Student or course"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Course</label><select name="course_id" class="w-full border rounded-lg px-3 py-2 bg-white"><option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?php echo (int)$course['id']; ?>" <?php echo $courseId === (int)$course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['course_number'] . ' - ' . $course['course_name']); ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Status</label><select name="status" class="w-full border rounded-lg px-3 py-2 bg-white"><option value="">All statuses</option><option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option><option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option></select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Created From</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="w-full border rounded-lg px-3 py-2"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Created To</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="w-full border rounded-lg px-3 py-2"></div>
        </div>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 mt-4"><a href="payment-reports.php" class="px-4 py-2 border rounded-lg text-gray-600 text-center">Clear</a><button class="bg-blue-600 text-white px-5 py-2 rounded-lg"><i class="fas fa-filter mr-2"></i>Generate Report</button></div>
    </form>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-white shadow rounded-xl p-5 border-l-4 border-blue-500"><p class="text-sm text-gray-500">Payment Plans</p><p class="text-2xl font-bold text-gray-800"><?php echo $summary['plans']; ?></p></div>
        <div class="bg-white shadow rounded-xl p-5 border-l-4 border-purple-500"><p class="text-sm text-gray-500">Total Amount</p><p class="text-2xl font-bold text-gray-800">Rs. <?php echo number_format($summary['total'], 2); ?></p></div>
        <div class="bg-white shadow rounded-xl p-5 border-l-4 border-green-500"><p class="text-sm text-gray-500">Collected</p><p class="text-2xl font-bold text-green-700">Rs. <?php echo number_format($summary['paid'], 2); ?></p></div>
        <div class="bg-white shadow rounded-xl p-5 border-l-4 border-orange-500"><p class="text-sm text-gray-500">Outstanding</p><p class="text-2xl font-bold text-orange-700">Rs. <?php echo number_format($summary['balance'], 2); ?></p></div>
    </div>
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto"><table class="w-full min-w-[1050px]"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="text-left px-5 py-3">Student</th><th class="text-left px-5 py-3">Course</th><th class="text-left px-5 py-3">Method</th><th class="text-right px-5 py-3">Total</th><th class="text-right px-5 py-3">Paid</th><th class="text-right px-5 py-3">Balance</th><th class="text-left px-5 py-3">Progress</th><th class="text-left px-5 py-3">Next Date</th><th class="text-left px-5 py-3">Status</th></tr></thead><tbody class="divide-y">
        <?php if (!$rows): ?><tr><td colspan="9" class="px-6 py-12 text-center text-gray-500">No payment records match these filters.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr class="hover:bg-gray-50"><td class="px-5 py-4"><p class="font-medium"><?php echo htmlspecialchars($row['student_name']); ?></p><p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['student_id_number']); ?></p></td><td class="px-5 py-4 text-sm"><p class="font-medium"><?php echo htmlspecialchars($row['course_number'] ?? '—'); ?></p><p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['course_name'] ?? 'No course'); ?></p></td><td class="px-5 py-4 text-sm"><?php echo htmlspecialchars($row['method_label']); ?></td><td class="px-5 py-4 text-sm text-right">Rs. <?php echo number_format($row['total_amount'], 2); ?></td><td class="px-5 py-4 text-sm text-right text-green-700">Rs. <?php echo number_format($row['paid_amount'], 2); ?></td><td class="px-5 py-4 text-sm text-right text-orange-700">Rs. <?php echo number_format($row['balance'], 2); ?></td><td class="px-5 py-4 text-sm"><?php echo (int)$row['paid_count']; ?>/<?php echo (int)$row['installment_count']; ?></td><td class="px-5 py-4 text-sm"><?php echo $row['next_due_date'] ? date('M j, Y', strtotime($row['next_due_date'])) : '—'; ?></td><td class="px-5 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $row['status_label'] === 'Paid' ? 'bg-green-100 text-green-700' : ($row['status_label'] === 'Overdue' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>"><?php echo $row['status_label']; ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
<?php $conn->close(); include 'includes/footer.php'; ?>
