<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

$conn = getConnection();
$methodInstallments = [
    'full' => 1,
    'half' => 2,
    'quarter' => 3,
    'scholarship' => 1,
    'free_card' => 1,
];
$methodLabels = [
    'full' => 'Full Payment',
    'half' => 'Half Payment',
    'quarter' => 'Quarter Payment',
    'scholarship' => 'Scholarship',
    'free_card' => 'Free Card',
];

if (empty($_SESSION['payment_csrf_token'])) {
    $_SESSION['payment_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = $_SESSION['payment_success'] ?? '';
unset($_SESSION['payment_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['payment_csrf_token'], $token)) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? 'create';

        if ($action === 'delete_payment') {
            $planId = filter_input(INPUT_POST, 'payment_plan_id', FILTER_VALIDATE_INT);
            if (!$planId) {
                $errors[] = 'Invalid payment record.';
            } else {
                $conn->begin_transaction();
                try {
                    // Delete children explicitly as well as relying on the database cascade.
                    $installmentStmt = $conn->prepare("DELETE FROM payment_installments WHERE payment_plan_id = ?");
                    $installmentStmt->bind_param('i', $planId);
                    $installmentStmt->execute();
                    $installmentStmt->close();

                    $planStmt = $conn->prepare("DELETE FROM payment_plans WHERE id = ?");
                    $planStmt->bind_param('i', $planId);
                    $planStmt->execute();
                    $deleted = $planStmt->affected_rows > 0;
                    $planStmt->close();
                    $conn->commit();

                    $_SESSION['payment_success'] = $deleted ? 'Payment record and all of its installments were deleted.' : 'Payment record was not found.';
                    $returnQuery = trim($_POST['return_query'] ?? '');
                    header('Location: payments.php' . ($returnQuery !== '' ? '?' . $returnQuery : '') . '#payment-records');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Payment deletion failed: ' . $e->getMessage());
                    $errors[] = 'The payment record could not be deleted. Please try again.';
                }
            }
        } elseif ($action === 'delete_course') {
            $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
            if (!$courseId) {
                $errors[] = 'Invalid course.';
            } else {
                $usageCheck = $conn->prepare("SELECT COUNT(*) AS total FROM payment_plans WHERE course_id = ?");
                $usageCheck->bind_param('i', $courseId);
                $usageCheck->execute();
                $usageCount = (int)$usageCheck->get_result()->fetch_assoc()['total'];
                $usageCheck->close();

                if ($usageCount > 0) {
                    $errors[] = 'This course cannot be deleted because it is used by ' . $usageCount . ' payment record(s).';
                } else {
                    $stmt = $conn->prepare("DELETE FROM payment_courses WHERE id = ?");
                    $stmt->bind_param('i', $courseId);
                    $stmt->execute();
                    $deleted = $stmt->affected_rows > 0;
                    $stmt->close();
                    if ((int)($_SESSION['selected_payment_course_id'] ?? 0) === (int)$courseId) {
                        unset($_SESSION['selected_payment_course_id']);
                    }
                    $_SESSION['payment_success'] = $deleted ? 'Course deleted successfully.' : 'Course was not found.';
                    header('Location: payments.php');
                    exit;
                }
            }
        } elseif ($action === 'create_course') {
            $courseNumber = trim($_POST['course_number'] ?? '');
            $courseName = trim($_POST['course_name'] ?? '');
            if ($courseNumber === '') $errors[] = 'Course number is required.';
            if ($courseName === '') $errors[] = 'Course name is required.';
            if (!$errors) {
                $check = $conn->prepare("SELECT id FROM payment_courses WHERE course_number = ? LIMIT 1");
                $check->bind_param('s', $courseNumber);
                $check->execute();
                $existingCourse = $check->get_result()->fetch_assoc();
                $check->close();
                if ($existingCourse) {
                    $errors[] = 'That course number already exists.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO payment_courses (course_number, course_name) VALUES (?, ?)");
                    $stmt->bind_param('ss', $courseNumber, $courseName);
                    $stmt->execute();
                    $courseId = $stmt->insert_id;
                    $stmt->close();
                    $_SESSION['selected_payment_course_id'] = $courseId;
                    $_SESSION['payment_success'] = 'Course created and selected automatically.';
                    header('Location: payments.php?course_id=' . $courseId . '#payment-form');
                    exit;
                }
            }
        } elseif ($action === 'toggle_installment') {
            $installmentId = filter_input(INPUT_POST, 'installment_id', FILTER_VALIDATE_INT);
            $newStatus = isset($_POST['is_paid']) && $_POST['is_paid'] === '1' ? 1 : 0;
            if (!$installmentId) {
                $errors[] = 'Invalid installment.';
            } else {
                $stmt = $conn->prepare("UPDATE payment_installments SET is_paid = ?, paid_at = IF(? = 1, NOW(), NULL) WHERE id = ?");
                $stmt->bind_param('iii', $newStatus, $newStatus, $installmentId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['payment_success'] = $newStatus ? 'Installment marked as paid.' : 'Installment marked as unpaid.';
                $returnQuery = trim($_POST['return_query'] ?? '');
                $returnAnchor = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['return_anchor'] ?? '');
                header('Location: payments.php' . ($returnQuery !== '' ? '?' . $returnQuery : '') . ($returnAnchor !== '' ? '#' . $returnAnchor : ''));
                exit;
            }
        } elseif ($action === 'create') {
            $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
            $studentName = trim($_POST['student_name'] ?? '');
            $studentIdNumber = trim($_POST['student_id_number'] ?? '');
            $paymentMethod = $_POST['payment_method'] ?? '';
            $notes = trim($_POST['notes'] ?? '');
            $totalAmount = filter_var($_POST['total_amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $expectedCount = $methodInstallments[$paymentMethod] ?? 0;
            $dates = $_POST['installment_date'] ?? [];
            $amounts = $_POST['installment_amount'] ?? [];
            $paidItems = $_POST['installment_paid'] ?? [];

            if (!$courseId) {
                $errors[] = 'Please select a course.';
            } else {
                $courseCheck = $conn->prepare("SELECT id FROM payment_courses WHERE id = ? LIMIT 1");
                $courseCheck->bind_param('i', $courseId);
                $courseCheck->execute();
                if (!$courseCheck->get_result()->fetch_assoc()) $errors[] = 'The selected course does not exist.';
                $courseCheck->close();
            }
            if ($studentName === '') $errors[] = 'Student name is required.';
            if ($studentIdNumber === '') $errors[] = 'Student ID number is required.';
            if (!$expectedCount) $errors[] = 'Please select a valid payment method.';
            if ($totalAmount === false || $totalAmount < 0) $errors[] = 'Enter a valid total amount.';
            if (in_array($paymentMethod, ['scholarship', 'free_card'], true)) $totalAmount = 0.00;
            if (count($dates) !== $expectedCount || count($amounts) !== $expectedCount) {
                $errors[] = 'The installment details do not match the selected payment method.';
            }

            $validatedInstallments = [];
            $installmentTotal = 0.0;
            for ($i = 0; $i < $expectedCount; $i++) {
                $date = $dates[$i] ?? '';
                $dateObject = DateTime::createFromFormat('Y-m-d', $date);
                $amount = filter_var($amounts[$i] ?? null, FILTER_VALIDATE_FLOAT);
                if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
                    $errors[] = 'A valid date is required for installment ' . ($i + 1) . '.';
                }
                if ($amount === false || $amount < 0) {
                    $errors[] = 'Enter a valid amount for installment ' . ($i + 1) . '.';
                }
                $amount = $amount === false ? 0.0 : (float)$amount;
                $installmentTotal += $amount;
                $validatedInstallments[] = [$date, $amount, isset($paidItems[$i]) ? 1 : 0];
            }
            if (!$errors && abs($installmentTotal - (float)$totalAmount) > 0.01) {
                $errors[] = 'Installment amounts must add up to the total amount.';
            }

            if (!$errors) {
                $conn->begin_transaction();
                try {
                    $studentUserId = null;
                    $lookup = $conn->prepare("SELECT id FROM users WHERE user_role = 'student' AND (user_id = ? OR CAST(id AS CHAR) = ?) LIMIT 1");
                    $lookup->bind_param('ss', $studentIdNumber, $studentIdNumber);
                    $lookup->execute();
                    $foundStudent = $lookup->get_result()->fetch_assoc();
                    if ($foundStudent) $studentUserId = (int)$foundStudent['id'];
                    $lookup->close();

                    $createdBy = (int)($_SESSION['user_id'] ?? 0);
                    $stmt = $conn->prepare("INSERT INTO payment_plans (course_id, student_user_id, student_name, student_id_number, payment_method, total_amount, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iisssdsi', $courseId, $studentUserId, $studentName, $studentIdNumber, $paymentMethod, $totalAmount, $notes, $createdBy);
                    $stmt->execute();
                    $planId = $stmt->insert_id;
                    $stmt->close();

                    $itemStmt = $conn->prepare("INSERT INTO payment_installments (payment_plan_id, installment_number, amount, payment_date, is_paid, paid_at) VALUES (?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))");
                    foreach ($validatedInstallments as $index => $installment) {
                        [$date, $amount, $isPaid] = $installment;
                        $number = $index + 1;
                        $itemStmt->bind_param('iidsii', $planId, $number, $amount, $date, $isPaid, $isPaid);
                        $itemStmt->execute();
                    }
                    $itemStmt->close();
                    $conn->commit();
                    $_SESSION['selected_payment_course_id'] = $courseId;
                    $_SESSION['payment_success'] = 'Payment plan saved successfully.';
                    header('Location: payments.php?course_id=' . $courseId . '#payment-form');
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Payment save failed: ' . $e->getMessage());
                    $errors[] = 'The payment plan could not be saved. Please try again.';
                }
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$allowedStatuses = ['', 'paid', 'pending', 'overdue'];
if (!in_array($status, $allowedStatuses, true)) $status = '';

$where = ['1=1'];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(p.student_name LIKE ? OR p.student_id_number LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($status === 'paid') $where[] = 'NOT EXISTS (SELECT 1 FROM payment_installments x WHERE x.payment_plan_id = p.id AND x.is_paid = 0)';
if ($status === 'pending') $where[] = 'EXISTS (SELECT 1 FROM payment_installments x WHERE x.payment_plan_id = p.id AND x.is_paid = 0 AND x.payment_date >= CURDATE())';
if ($status === 'overdue') $where[] = 'EXISTS (SELECT 1 FROM payment_installments x WHERE x.payment_plan_id = p.id AND x.is_paid = 0 AND x.payment_date < CURDATE())';

$sql = "SELECT p.*, c.course_number, c.course_name,
        COUNT(i.id) AS installment_count,
        SUM(i.is_paid) AS paid_count,
        COALESCE(SUM(CASE WHEN i.is_paid = 1 THEN i.amount ELSE 0 END), 0) AS paid_amount,
        MIN(CASE WHEN i.is_paid = 0 THEN i.payment_date END) AS next_due_date
    FROM payment_plans p
    JOIN payment_installments i ON i.payment_plan_id = p.id
    LEFT JOIN payment_courses c ON c.id = p.course_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$courses = $conn->query("SELECT id, course_number, course_name FROM payment_courses ORDER BY course_name, course_number")->fetch_all(MYSQLI_ASSOC);
$selectedCourseId = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? $_SESSION['selected_payment_course_id'] ?? 0);
if ($selectedCourseId > 0) $_SESSION['selected_payment_course_id'] = $selectedCourseId;
$openPlanId = (int)($_GET['open_plan'] ?? 0);

$installmentsByPlan = [];
if ($plans) {
    $planIds = array_column($plans, 'id');
    $idList = implode(',', array_map('intval', $planIds));
    $result = $conn->query("SELECT * FROM payment_installments WHERE payment_plan_id IN ($idList) ORDER BY payment_plan_id, installment_number");
    while ($row = $result->fetch_assoc()) $installmentsByPlan[$row['payment_plan_id']][] = $row;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Student Payments</h1>
            <p class="text-gray-500 mt-1">Create payment plans and track every installment date.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
            <button type="button" onclick="document.getElementById('payment-form').scrollIntoView({behavior:'smooth'})" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg w-full sm:w-auto">
                <i class="fas fa-plus mr-2"></i>New Payment
            </button>
            <a href="payment-reports.php" class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-2.5 rounded-lg text-center w-full sm:w-auto"><i class="fas fa-chart-column mr-2"></i>Reports</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800"><i class="fas fa-circle-check mr-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 text-red-800">
            <p class="font-semibold mb-1">Please correct the following:</p>
            <ul class="list-disc ml-5"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Create Course</h2>
                <p class="text-sm text-gray-500 mt-1">Create each course once. It will then remain available in the payment form.</p>
            </div>
            <form method="POST" class="grid grid-cols-1 sm:grid-cols-[180px_1fr_auto] gap-3 w-full lg:max-w-3xl">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payment_csrf_token']); ?>">
                <input type="hidden" name="action" value="create_course">
                <input name="course_number" required maxlength="50" class="border rounded-lg px-4 py-2.5" placeholder="Course number">
                <input name="course_name" required maxlength="255" class="border rounded-lg px-4 py-2.5" placeholder="Course name">
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg whitespace-nowrap"><i class="fas fa-plus mr-2"></i>Create Course</button>
            </form>
        </div>
        <?php if ($courses): ?>
            <div class="border-t mt-5 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Saved Courses</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($courses as $course): ?>
                        <div class="inline-flex items-center gap-2 rounded-lg border bg-gray-50 pl-3 pr-1 py-1">
                            <span class="text-sm text-gray-700"><strong><?php echo htmlspecialchars($course['course_number']); ?></strong> — <?php echo htmlspecialchars($course['course_name']); ?></span>
                            <form method="POST" onsubmit="return confirm('Delete this course? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payment_csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete_course">
                                <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">
                                <button type="submit" class="w-8 h-8 rounded-md text-red-500 hover:text-white hover:bg-red-600" title="Delete course" aria-label="Delete <?php echo htmlspecialchars($course['course_name']); ?>">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-500 mt-3">Courses already used in payment records are protected from deletion.</p>
            </div>
        <?php endif; ?>
    </section>

    <section id="payment-form" class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-8">
        <div class="flex items-center justify-between mb-6">
            <div><h2 class="text-xl font-semibold text-gray-800">Create Payment Plan</h2><p class="text-sm text-gray-500 mt-1">Installments are generated from the payment method.</p></div>
            <div class="hidden sm:flex w-11 h-11 rounded-full bg-blue-100 text-blue-600 items-center justify-center"><i class="fas fa-receipt"></i></div>
        </div>
        <form method="POST" id="createPaymentForm" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payment_csrf_token']); ?>">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">Course <span class="text-red-500">*</span></label>
                    <select id="course_id" name="course_id" required class="w-full border rounded-lg px-4 py-2.5 bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo (int)$course['id']; ?>" <?php echo $selectedCourseId === (int)$course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['course_number'] . ' — ' . $course['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$courses): ?><p class="text-xs text-amber-600 mt-1">Create a course above before saving a payment.</p><?php endif; ?>
                </div>
                <div>
                    <label for="student_id_number" class="block text-sm font-medium text-gray-700 mb-2">Student ID Number <span class="text-red-500">*</span></label>
                    <input id="student_id_number" name="student_id_number" required value="<?php echo htmlspecialchars($_POST['student_id_number'] ?? ''); ?>" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="e.g. BV-2026-001">
                </div>
                <div>
                    <label for="student_name" class="block text-sm font-medium text-gray-700 mb-2">Student Name <span class="text-red-500">*</span></label>
                    <input id="student_name" name="student_name" required list="studentNames" value="<?php echo htmlspecialchars($_POST['student_name'] ?? ''); ?>" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Enter student name">
                    <datalist id="studentNames">
                        <?php $studentOptions = $conn->query("SELECT name, user_id, id FROM users WHERE user_role = 'student' ORDER BY name"); while ($student = $studentOptions->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($student['name']); ?>" data-student-id="<?php echo htmlspecialchars($student['user_id'] ?: $student['id']); ?>"><?php echo htmlspecialchars($student['user_id'] ?: $student['id']); ?></option>
                        <?php endwhile; ?>
                    </datalist>
                </div>
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">Payment Method <span class="text-red-500">*</span></label>
                    <select id="payment_method" name="payment_method" required class="w-full border rounded-lg px-4 py-2.5 bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">Select payment method</option>
                        <?php foreach ($methodLabels as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo (($_POST['payment_method'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo $label; ?> (<?php echo $methodInstallments[$value]; ?> payment<?php echo $methodInstallments[$value] > 1 ? 's' : ''; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="total_amount" class="block text-sm font-medium text-gray-700 mb-2">Total Amount <span class="text-red-500">*</span></label>
                    <input type="number" id="total_amount" name="total_amount" required min="0" step="0.01" value="<?php echo htmlspecialchars($_POST['total_amount'] ?? ''); ?>" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="0.00">
                    <p id="amountHelp" class="text-xs text-gray-500 mt-1">Enter the complete course/payment amount.</p>
                </div>
            </div>

            <div id="installmentSection" class="hidden">
                <div class="border-t pt-5">
                    <h3 class="font-semibold text-gray-800">Payment Schedule</h3>
                    <p class="text-sm text-gray-500 mb-4">Set each payment date and tick payments already received.</p>
                    <div id="installmentRows" class="space-y-3"></div>
                </div>
            </div>
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="3" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Add scholarship details, receipt reference, or other notes"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>
            <div class="flex justify-end">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2.5 rounded-lg"><i class="fas fa-floppy-disk mr-2"></i>Save Payment Plan</button>
            </div>
        </form>
    </section>

    <section id="payment-records" class="bg-white rounded-xl shadow-md overflow-hidden scroll-mt-6">
        <div class="p-4 sm:p-6 border-b">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div><h2 class="text-xl font-semibold text-gray-800">Payment Records</h2><p class="text-sm text-gray-500 mt-1"><?php echo count($plans); ?> record(s) shown</p></div>
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input name="search" value="<?php echo htmlspecialchars($search); ?>" class="border rounded-lg px-4 py-2" placeholder="Student name or ID">
                    <select name="status" class="border rounded-lg px-4 py-2 bg-white">
                        <option value="">All statuses</option><option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Fully paid</option><option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                    <button class="bg-gray-800 text-white px-4 py-2 rounded-lg"><i class="fas fa-search mr-2"></i>Filter</button>
                </form>
            </div>
        </div>
        <?php if (!$plans): ?>
            <div class="p-12 text-center text-gray-500"><i class="fas fa-receipt text-4xl text-gray-300 mb-3"></i><p>No payment records found.</p></div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="text-left px-6 py-3">Student</th><th class="text-left px-6 py-3">Course</th><th class="text-left px-6 py-3">Method</th><th class="text-left px-6 py-3">Progress</th><th class="text-left px-6 py-3">Amount</th><th class="text-left px-6 py-3">Next date</th><th class="text-left px-6 py-3">Status</th><th class="text-right px-6 py-3">Action</th></tr></thead>
                    <tbody class="divide-y">
                    <?php foreach ($plans as $plan):
                        $complete = (int)$plan['paid_count'] === (int)$plan['installment_count'];
                        $overdue = !$complete && $plan['next_due_date'] && $plan['next_due_date'] < date('Y-m-d');
                    ?>
                        <tr id="payment-plan-<?php echo (int)$plan['id']; ?>" class="hover:bg-gray-50 align-top scroll-mt-6">
                            <td class="px-6 py-4"><p class="font-medium text-gray-900"><?php echo htmlspecialchars($plan['student_name']); ?></p><p class="text-sm text-gray-500"><?php echo htmlspecialchars($plan['student_id_number']); ?></p></td>
                            <td class="px-6 py-4"><p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($plan['course_number'] ?? '—'); ?></p><p class="text-xs text-gray-500"><?php echo htmlspecialchars($plan['course_name'] ?? 'No course'); ?></p></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($methodLabels[$plan['payment_method']] ?? $plan['payment_method']); ?></td>
                            <td class="px-6 py-4"><p class="text-sm font-medium"><?php echo (int)$plan['paid_count']; ?> / <?php echo (int)$plan['installment_count']; ?> paid</p><details class="mt-2" <?php echo $openPlanId === (int)$plan['id'] ? 'open' : ''; ?>><summary class="text-blue-600 text-sm cursor-pointer">View schedule</summary><div class="mt-3 space-y-2 min-w-[280px]">
                                <?php foreach ($installmentsByPlan[$plan['id']] ?? [] as $item): ?>
                                    <form method="POST" class="flex items-center justify-between gap-3 rounded-lg border p-2 bg-white">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payment_csrf_token']); ?>"><input type="hidden" name="action" value="toggle_installment"><input type="hidden" name="installment_id" value="<?php echo (int)$item['id']; ?>"><input type="hidden" name="is_paid" value="<?php echo $item['is_paid'] ? '0' : '1'; ?>"><input type="hidden" name="return_query" value="<?php echo htmlspecialchars(http_build_query(['search' => $search, 'status' => $status, 'open_plan' => (int)$plan['id']])); ?>"><input type="hidden" name="return_anchor" value="payment-plan-<?php echo (int)$plan['id']; ?>">
                                        <div><p class="text-xs font-semibold">Payment <?php echo (int)$item['installment_number']; ?> · <?php echo date('M j, Y', strtotime($item['payment_date'])); ?></p><p class="text-xs text-gray-500">Rs. <?php echo number_format((float)$item['amount'], 2); ?></p></div>
                                        <button class="text-xs px-2 py-1 rounded <?php echo $item['is_paid'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>"><?php echo $item['is_paid'] ? 'Paid ✓' : 'Mark paid'; ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div></details></td>
                            <td class="px-6 py-4 text-sm"><p class="font-medium">Rs. <?php echo number_format((float)$plan['paid_amount'], 2); ?></p><p class="text-gray-500">of Rs. <?php echo number_format((float)$plan['total_amount'], 2); ?></p></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?php echo $plan['next_due_date'] ? date('M j, Y', strtotime($plan['next_due_date'])) : '—'; ?></td>
                            <td class="px-6 py-4"><?php if ($complete): ?><span class="px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Paid</span><?php elseif ($overdue): ?><span class="px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold">Overdue</span><?php else: ?><span class="px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-semibold">Pending</span><?php endif; ?></td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" onsubmit="return confirm('Delete this complete payment record and all installment rows? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payment_csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_payment">
                                    <input type="hidden" name="payment_plan_id" value="<?php echo (int)$plan['id']; ?>">
                                    <input type="hidden" name="return_query" value="<?php echo htmlspecialchars(http_build_query(['search' => $search, 'status' => $status])); ?>">
                                    <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white" title="Delete payment record" aria-label="Delete payment record for <?php echo htmlspecialchars($plan['student_name']); ?>">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
(() => {
    const counts = {full: 1, half: 2, quarter: 3, scholarship: 1, free_card: 1};
    const method = document.getElementById('payment_method');
    const total = document.getElementById('total_amount');
    const section = document.getElementById('installmentSection');
    const rows = document.getElementById('installmentRows');
    const amountHelp = document.getElementById('amountHelp');
    const course = document.getElementById('course_id');

    function localDate(offsetMonths = 0) {
        const date = new Date(); date.setMonth(date.getMonth() + offsetMonths);
        return date.toLocaleDateString('en-CA');
    }
    function renderSchedule() {
        const selected = method.value;
        const count = counts[selected] || 0;
        const free = selected === 'scholarship' || selected === 'free_card';
        total.readOnly = free;
        total.classList.toggle('bg-gray-100', free);
        if (free) total.value = '0.00';
        amountHelp.textContent = free ? 'No payment is charged for this method.' : 'Enter the complete course/payment amount.';
        section.classList.toggle('hidden', !count);
        if (!count) { rows.innerHTML = ''; return; }
        const value = Math.max(0, parseFloat(total.value) || 0);
        const base = Math.floor((value / count) * 100) / 100;
        rows.innerHTML = Array.from({length: count}, (_, index) => {
            const amount = index === count - 1 ? (value - base * (count - 1)).toFixed(2) : base.toFixed(2);
            return `<div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3 items-end rounded-lg bg-gray-50 border p-4">
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Payment ${index + 1} date</label><input type="date" name="installment_date[]" required value="${localDate(index)}" class="w-full border rounded-lg px-3 py-2 bg-white"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Amount</label><input type="number" name="installment_amount[]" required min="0" step="0.01" value="${amount}" class="w-full border rounded-lg px-3 py-2 bg-white"></div>
                <label class="flex items-center gap-2 text-sm pb-2"><input type="checkbox" name="installment_paid[${index}]" value="1" class="rounded text-blue-600">Received</label>
            </div>`;
        }).join('');
    }
    method.addEventListener('change', renderSchedule);
    total.addEventListener('input', renderSchedule);
    document.getElementById('student_name').addEventListener('change', event => {
        const option = [...document.getElementById('studentNames').options].find(item => item.value === event.target.value);
        if (option) document.getElementById('student_id_number').value = option.dataset.studentId || '';
    });
    if (course) {
        const rememberedCourse = localStorage.getItem('bvSelectedPaymentCourse');
        if (!course.value && rememberedCourse && [...course.options].some(option => option.value === rememberedCourse)) course.value = rememberedCourse;
        course.addEventListener('change', () => {
            if (course.value) localStorage.setItem('bvSelectedPaymentCourse', course.value);
            else localStorage.removeItem('bvSelectedPaymentCourse');
        });
        if (course.value) localStorage.setItem('bvSelectedPaymentCourse', course.value);
    }
    if (method.value) renderSchedule();
})();
</script>

<?php
$conn->close();
include 'includes/footer.php';
?>
