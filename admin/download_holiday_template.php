<?php
require_once __DIR__ . '/../auth/guard.php';
guardRole('admin');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="holiday_template.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['title', 'holiday_date', 'description']);
fputcsv($out, ['Republic Day',    '2025-01-26', 'National holiday']);
fputcsv($out, ['Holi',            '2025-03-14', 'Festival of colours']);
fputcsv($out, ['Independence Day','2025-08-15', 'National holiday']);
fclose($out);
exit;
