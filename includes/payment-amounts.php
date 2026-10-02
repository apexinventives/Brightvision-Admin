<?php

// The caller saves the settings and applies these updates in one transaction.
function updateStudentPaymentAmounts($conn, array $amounts) {
    $halfTotal = $amounts['half_first'] + $amounts['half_second'];
    $quarterTotal = $amounts['quarter_each'] * 3;
    $stmt = $conn->prepare("UPDATE payment_plans SET total_amount = CASE payment_method
        WHEN 'full' THEN ? WHEN 'half' THEN ? WHEN 'quarter' THEN ? END
        WHERE payment_method IN ('full', 'half', 'quarter')");
    $stmt->bind_param('ddd', $amounts['full_amount'], $halfTotal, $quarterTotal);
    if (!$stmt->execute()) throw new RuntimeException('Could not update student payment totals.');
    $updatedPlans = $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare("UPDATE payment_installments i
        JOIN payment_plans p ON p.id = i.payment_plan_id
        SET i.amount = CASE
            WHEN p.payment_method = 'full' AND i.installment_number = 1 THEN ?
            WHEN p.payment_method = 'half' AND i.installment_number = 1 THEN ?
            WHEN p.payment_method = 'half' AND i.installment_number = 2 THEN ?
            WHEN p.payment_method = 'quarter' AND i.installment_number BETWEEN 1 AND 3 THEN ?
            ELSE i.amount END
        WHERE p.payment_method IN ('full', 'half', 'quarter')");
    $stmt->bind_param('dddd', $amounts['full_amount'], $amounts['half_first'], $amounts['half_second'], $amounts['quarter_each']);
    if (!$stmt->execute()) throw new RuntimeException('Could not update student installment amounts.');
    $stmt->close();
    return $updatedPlans;
}

function updatePaymentMethodSchedule($conn, $planId, $method, $total, array $settings, array $customAmounts = null, array $customDates = null, array $customPaid = null) {
    $weights = [
        'full' => [$settings['full_amount']],
        'half' => [$settings['half_first'], $settings['half_second']],
        'quarter' => array_fill(0, 3, $settings['quarter_each']),
        'scholarship' => [0], 'free_card' => [0],
    ][$method];
    $stmt = $conn->prepare('SELECT * FROM payment_installments WHERE payment_plan_id = ? ORDER BY installment_number FOR UPDATE');
    $stmt->bind_param('i', $planId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($items as $item) {
        if ((int)$item['installment_number'] > count($weights) && $item['is_paid']) {
            throw new InvalidArgumentException('This method would remove a paid installment. Mark that installment unpaid before changing the method.');
        }
    }
    $byNumber = [];
    foreach ($items as $item) $byNumber[(int)$item['installment_number']] = $item;
    $remaining = (int)round($total * 100);
    $totalCents = $remaining;
    $weightTotal = array_sum($weights);
    $firstDate = $items ? $items[0]['payment_date'] : date('Y-m-d');
    foreach ($weights as $index => $weight) {
        $number = $index + 1;
        $cents = $number === count($weights) ? $remaining : (int)floor($totalCents * ($weightTotal > 0 ? $weight / $weightTotal : 1 / count($weights)));
        $remaining -= $cents;
        $amount = $customAmounts !== null ? $customAmounts[$index] : $cents / 100;
        if (isset($byNumber[$number])) {
            $itemId = (int)$byNumber[$number]['id'];
            $date = $customDates !== null ? $customDates[$index] : $byNumber[$number]['payment_date'];
            if ($customPaid !== null) {
                $paid = $customPaid[$index];
                $stmt = $conn->prepare('UPDATE payment_installments SET amount = ?, payment_date = ?, paid_at = CASE WHEN ? = 0 THEN NULL WHEN is_paid = 1 THEN paid_at ELSE NOW() END, is_paid = ? WHERE id = ?');
                $stmt->bind_param('dsiii', $amount, $date, $paid, $paid, $itemId);
            } else {
                $stmt = $conn->prepare('UPDATE payment_installments SET amount = ?, payment_date = ? WHERE id = ?');
                $stmt->bind_param('dsi', $amount, $date, $itemId);
            }
        } else {
            $date = $customDates !== null ? $customDates[$index] : (new DateTime($firstDate))->modify('+' . $index . ' month')->format('Y-m-d');
            $paid = $customPaid !== null ? $customPaid[$index] : 0;
            $stmt = $conn->prepare('INSERT INTO payment_installments (payment_plan_id, installment_number, amount, payment_date, is_paid, paid_at) VALUES (?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))');
            $stmt->bind_param('iidsii', $planId, $number, $amount, $date, $paid, $paid);
        }
        if (!$stmt->execute()) throw new RuntimeException('Could not update payment schedule.');
        $stmt->close();
    }
    $count = count($weights);
    $stmt = $conn->prepare('DELETE FROM payment_installments WHERE payment_plan_id = ? AND installment_number > ? AND is_paid = 0');
    $stmt->bind_param('ii', $planId, $count);
    if (!$stmt->execute()) throw new RuntimeException('Could not remove unused installments.');
    $stmt->close();
}
