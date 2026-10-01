<?php
require __DIR__.'/../includes/payment-method-summary.php';
$labels=['full'=>'Full','half'=>'Half','quarter'=>'Quarter','scholarship'=>'Scholarship','free_card'=>'Free Card'];
$rows=[['payment_method'=>'half','student_id_number'=>'S1','total_amount'=>33000,'paid_amount'=>20000],['payment_method'=>'half','student_id_number'=>'S1','total_amount'=>33000,'paid_amount'=>13000],['payment_method'=>'full','student_id_number'=>'S2','total_amount'=>30000,'paid_amount'=>30000]];
$result=summarizePaymentMethods($rows,$labels);
if($result['half']['plans']!==2 || $result['half']['students']!==1 || $result['half']['total']!==66000.0 || $result['half']['paid']!==33000.0 || $result['half']['balance']!==33000.0 || $result['quarter']['plans']!==0 || count($result)!==5) throw new RuntimeException('Summary failed');
if(summarizePaymentMethods([],$labels)['full']['plans']!==0) throw new RuntimeException('Empty summary failed');
echo "Method counts, unique students, amounts and empty results checks passed.\n";
