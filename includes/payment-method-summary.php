<?php

function summarizePaymentMethods(array $rows, array $labels) {
    $summary = [];
    $students = [];
    foreach ($labels as $method => $label) {
        $summary[$method] = ['label' => $label, 'plans' => 0, 'students' => 0, 'total' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
        $students[$method] = [];
    }
    foreach ($rows as $row) {
        $method = $row['payment_method'];
        if (!isset($summary[$method])) continue;
        $summary[$method]['plans']++;
        $students[$method][(string)$row['student_id_number']] = true;
        $summary[$method]['total'] += (float)$row['total_amount'];
        $summary[$method]['paid'] += (float)$row['paid_amount'];
        $summary[$method]['balance'] += max(0, (float)$row['total_amount'] - (float)$row['paid_amount']);
    }
    foreach ($summary as $method => &$item) $item['students'] = count($students[$method]);
    unset($item);
    return $summary;
}
