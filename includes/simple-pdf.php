<?php

function pdfSafeText($text) {
    $text = (string)$text;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) $text = $converted;
    }
    $text = preg_replace('/[^\x20-\x7E]/', '?', $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function pdfFitText($text, $maxChars) {
    $text = (string)$text;
    return strlen($text) > $maxChars ? substr($text, 0, max(0, $maxChars - 3)) . '...' : $text;
}

function createPaymentReportPdf(array $rows, array $summary, string $filterDescription): string {
    $pageWidth = 842;
    $pageHeight = 595;
    $left = 28;
    $top = 558;
    $rowHeight = 20;
    $rowsPerPage = 20;
    $pages = array_chunk($rows, $rowsPerPage);
    if (!$pages) $pages = [[]];
    $streams = [];

    foreach ($pages as $pageIndex => $pageRows) {
        $commands = [];
        $commands[] = '0.12 0.25 0.48 rg 0 535 842 60 re f';
        $commands[] = 'BT /F1 18 Tf 1 1 1 rg ' . $left . ' 561 Td (Payment Report) Tj ET';
        $commands[] = 'BT /F1 8 Tf 0.88 0.93 1 rg ' . $left . ' 546 Td (' . pdfSafeText('Generated ' . date('Y-m-d H:i') . ' | ' . $filterDescription) . ') Tj ET';

        if ($pageIndex === 0) {
            $cards = [
                'Plans: ' . $summary['plans'],
                'Total: Rs. ' . number_format($summary['total'], 2),
                'Collected: Rs. ' . number_format($summary['paid'], 2),
                'Balance: Rs. ' . number_format($summary['balance'], 2),
            ];
            foreach ($cards as $index => $card) {
                $x = $left + ($index * 197);
                $commands[] = '0.94 0.96 0.99 rg ' . $x . ' 496 183 27 re f';
                $commands[] = 'BT /F1 9 Tf 0.12 0.20 0.32 rg ' . ($x + 8) . ' 506 Td (' . pdfSafeText($card) . ') Tj ET';
            }
            $tableTop = 480;
        } else {
            $tableTop = 520;
        }

        $columns = [
            ['Student', 122, 20], ['Student ID', 82, 13], ['Course', 118, 19],
            ['Method', 72, 11], ['Total', 75, 14], ['Paid', 75, 14],
            ['Balance', 75, 14], ['Progress', 62, 10], ['Status', 66, 10], ['Next Date', 72, 11],
        ];
        $commands[] = '0.20 0.36 0.62 rg ' . $left . ' ' . ($tableTop - $rowHeight) . ' 786 ' . $rowHeight . ' re f';
        $x = $left;
        foreach ($columns as [$heading, $width]) {
            $commands[] = 'BT /F1 7 Tf 1 1 1 rg ' . ($x + 4) . ' ' . ($tableTop - 13) . ' Td (' . pdfSafeText($heading) . ') Tj ET';
            $x += $width;
        }

        $y = $tableTop - $rowHeight;
        foreach ($pageRows as $rowIndex => $row) {
            $y -= $rowHeight;
            $shade = $rowIndex % 2 === 0 ? '0.98 0.99 1' : '0.94 0.96 0.98';
            $commands[] = $shade . ' rg ' . $left . ' ' . $y . ' 786 ' . $rowHeight . ' re f';
            $values = [
                $row['student_name'], $row['student_id_number'], trim(($row['course_number'] ?? '') . ' ' . ($row['course_name'] ?? '')),
                $row['method_label'], 'Rs. ' . number_format((float)$row['total_amount'], 2),
                'Rs. ' . number_format((float)$row['paid_amount'], 2), 'Rs. ' . number_format((float)$row['balance'], 2),
                $row['paid_count'] . '/' . $row['installment_count'], $row['status_label'], $row['next_due_date'] ?: '-',
            ];
            $x = $left;
            foreach ($columns as $columnIndex => $column) {
                [$heading, $width, $maxChars] = $column;
                $commands[] = 'BT /F1 6.8 Tf 0.12 0.16 0.22 rg ' . ($x + 4) . ' ' . ($y + 7) . ' Td (' . pdfSafeText(pdfFitText($values[$columnIndex], $maxChars)) . ') Tj ET';
                $x += $width;
            }
        }
        $commands[] = 'BT /F1 7 Tf 0.35 0.40 0.48 rg 740 18 Td (Page ' . ($pageIndex + 1) . ' of ' . count($pages) . ') Tj ET';
        if (!$pageRows) $commands[] = 'BT /F1 11 Tf 0.35 0.40 0.48 rg 330 430 Td (No payment records found.) Tj ET';
        $streams[] = implode("\n", $commands);
    }

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $kids = [];
    foreach ($streams as $index => $stream) {
        $pageObject = 4 + ($index * 2);
        $contentObject = $pageObject + 1;
        $kids[] = $pageObject . ' 0 R';
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight . '] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
        $objects[$contentObject] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($streams) . ' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $object) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}
