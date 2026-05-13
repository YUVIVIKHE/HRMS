<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();
$stmt = $db->query("SHOW COLUMNS FROM employees");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Remove auto/internal fields
$exclude = ['id', 'created_at'];
$templateHeaders = array_values(array_diff($columns, $exclude));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=employee_bulk_template.csv');

$output = fopen('php://output', 'w');
fputcsv($output, $templateHeaders);
fclose($output);
exit;
