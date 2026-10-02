<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/payment-amounts.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = getConnection();
// Temporary tables shadow real tables only within this test connection.
$conn->query("CREATE TEMPORARY TABLE payment_plans (id INT PRIMARY KEY, payment_method VARCHAR(20), total_amount DECIMAL(12,2)) ENGINE=InnoDB");
$conn->query("CREATE TEMPORARY TABLE payment_installments (id INT AUTO_INCREMENT PRIMARY KEY, payment_plan_id INT, installment_number INT, amount DECIMAL(12,2), is_paid INT, payment_date DATE, paid_at DATETIME) ENGINE=InnoDB");
$conn->query("INSERT INTO payment_plans VALUES (1,'full',1),(2,'half',2),(3,'quarter',3),(4,'scholarship',0),(5,'free_card',0)");
$conn->query("INSERT INTO payment_installments VALUES (1,1,1,1,1,'2026-10-01','2026-10-01 12:00:00'),(2,2,1,1,1,'2026-10-01','2026-10-01 12:00:00'),(3,2,2,1,0,'2026-11-01',NULL),(4,3,1,1,0,'2026-10-01',NULL),(5,3,2,1,0,'2026-11-01',NULL),(6,3,3,1,0,'2026-12-01',NULL),(7,4,1,0,1,'2026-10-01',NULL),(8,5,1,0,1,'2026-10-01',NULL)");
$before = $conn->query('SELECT id,is_paid,payment_date,paid_at FROM payment_installments ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$conn->begin_transaction();
updateStudentPaymentAmounts($conn, ['full_amount'=>30000,'half_first'=>20000,'half_second'=>13000,'quarter_each'=>11000]);
$totals = array_map('floatval', array_column($conn->query('SELECT total_amount FROM payment_plans ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'total_amount'));
$items = array_map('floatval', array_column($conn->query('SELECT amount FROM payment_installments ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'amount'));
$after = $conn->query('SELECT id,is_paid,payment_date,paid_at FROM payment_installments ORDER BY id')->fetch_all(MYSQLI_ASSOC);
if ($totals !== [30000.0,33000.0,33000.0,0.0,0.0] || $items !== [30000.0,20000.0,13000.0,11000.0,11000.0,11000.0,0.0,0.0] || $before !== $after) throw new RuntimeException('Payment propagation check failed');
$conn->rollback();
if ((float)$conn->query('SELECT total_amount FROM payment_plans WHERE id=1')->fetch_assoc()['total_amount'] !== 1.0) throw new RuntimeException('Rollback failed');
echo "Central updates, installment totals, paid status, dates, free plans and rollback checks passed.\n";
$settings = ['full_amount'=>30000,'half_first'=>20000,'half_second'=>13000,'quarter_each'=>11000];
foreach (['quarter'=>[33000,[11000,11000,11000]],'half'=>[33000,[20000,13000]],'full'=>[30000,[30000]],'scholarship'=>[0,[0]],'free_card'=>[0,[0]]] as $method=>$expected) {
    $conn->begin_transaction();
    updatePaymentMethodSchedule($conn, 1, $method, $expected[0], $settings);
    $rows = $conn->query('SELECT amount,is_paid,payment_date,paid_at FROM payment_installments WHERE payment_plan_id=1 ORDER BY installment_number')->fetch_all(MYSQLI_ASSOC);
    if (array_map('floatval', array_column($rows, 'amount')) !== array_map('floatval', $expected[1])) throw new RuntimeException('Wrong schedule for '.$method);
    if ($rows[0]['is_paid'] !== '1' || $rows[0]['payment_date'] !== '2026-10-01' || $rows[0]['paid_at'] !== '2026-10-01 12:00:00') throw new RuntimeException('Payment metadata lost');
    $conn->commit();
}
$conn->query('UPDATE payment_installments SET is_paid=1 WHERE id=3');
$conn->begin_transaction();
$blocked=false;
try { updatePaymentMethodSchedule($conn, 2, 'full', 30000, $settings); } catch (InvalidArgumentException $e) { $blocked=true; }
$conn->rollback();
if (!$blocked) throw new RuntimeException('Paid installment removal was not blocked');
echo "All five method schedules and paid installment protection checks passed.\n";
foreach ([[21000,12000],[10000,23000]] as $custom) {
    $conn->begin_transaction();
    updatePaymentMethodSchedule($conn, 1, 'half', 33000, $settings, $custom);
    $saved = array_map('floatval', array_column($conn->query('SELECT amount FROM payment_installments WHERE payment_plan_id=1 ORDER BY installment_number')->fetch_all(MYSQLI_ASSOC), 'amount'));
    if ($saved !== array_map('floatval', $custom)) throw new RuntimeException('Custom amounts were not saved exactly');
    $conn->commit();
}
echo "Custom installment amounts and same-total redistribution checks passed.\n";
$conn->begin_transaction();
updatePaymentMethodSchedule($conn, 1, 'quarter', 33000, $settings, [10000,12000,11000], ['2026-10-02','2026-11-15','2026-12-20'], [0,1,0]);
$edited = $conn->query('SELECT amount,payment_date,is_paid,paid_at FROM payment_installments WHERE payment_plan_id=1 ORDER BY installment_number')->fetch_all(MYSQLI_ASSOC);
if (array_column($edited,'payment_date') !== ['2026-10-02','2026-11-15','2026-12-20'] || array_map('intval',array_column($edited,'is_paid')) !== [0,1,0] || $edited[0]['paid_at'] !== null || !$edited[1]['paid_at'] || $edited[2]['paid_at'] !== null) throw new RuntimeException('Dates or paid states incorrect');
$paidAt=$edited[1]['paid_at'];
updatePaymentMethodSchedule($conn, 1, 'quarter', 33000, $settings, [10000,12000,11000], ['2026-10-02','2026-11-15','2026-12-20'], [0,1,0]);
$again=$conn->query('SELECT paid_at FROM payment_installments WHERE payment_plan_id=1 AND installment_number=2')->fetch_assoc();
if($again['paid_at'] !== $paidAt) throw new RuntimeException('Existing paid timestamp changed');
$conn->rollback();
echo "Custom second/third dates, paid/unpaid switches and paid timestamps checks passed.\n";
$conn->close();
