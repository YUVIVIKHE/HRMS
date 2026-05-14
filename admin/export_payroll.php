<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

$monthFrom = max(1, min(12, (int)($_POST['month_from'] ?? date('n'))));
$monthTo   = max(1, min(12, (int)($_POST['month_to'] ?? date('n'))));
$year      = (int)($_POST['year'] ?? date('Y'));
$userIds   = trim($_POST['user_ids'] ?? '');

if ($monthFrom > $monthTo) { $tmp = $monthFrom; $monthFrom = $monthTo; $monthTo = $tmp; }

$rangeLabel = date('M', mktime(0,0,0,$monthFrom,1)) . '_to_' . date('M', mktime(0,0,0,$monthTo,1)) . '_' . $year;

// Build query
$where = "1=1";
$params = [];
if ($userIds) {
    $ids = array_filter(array_map('intval', explode(',', $userIds)));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where = "ss.user_id IN ($placeholders)";
        $params = $ids;
    }
}

$data = $db->prepare("
    SELECT ss.*, u.name AS full_name, e.employee_id AS emp_code, e.job_title,
           d.name AS dept_name, e.email
    FROM salary_structures ss
    JOIN users u ON ss.user_id = u.id
    JOIN employees e ON ss.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE $where
    ORDER BY u.name ASC
");
$data->execute($params);
$data = $data->fetchAll(PDO::FETCH_ASSOC);

if (empty($data)) {
    $_SESSION['flash_error'] = "No salary structures to export.";
    header("Location: payroll.php"); exit;
}

// Collect all custom deduction names
$allCustomDedNames = [];
foreach ($data as $row) {
    $cds = json_decode($row['custom_deductions'] ?? '[]', true) ?: [];
    foreach ($cds as $cd) {
        if (!empty($cd['name']) && !in_array($cd['name'], $allCustomDedNames)) {
            $allCustomDedNames[] = $cd['name'];
        }
    }
}

// Output as CSV (works everywhere, no dependencies)
$filename = "Salary_Structure_" . $rangeLabel . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$output = fopen('php://output', 'w');
// BOM for Excel to recognize UTF-8
fwrite($output, "\xEF\xBB\xBF");

// Title row
fputcsv($output, ['SALARY STRUCTURE REPORT - ' . strtoupper(str_replace('_', ' ', $rangeLabel))]);
fputcsv($output, []); // blank row

// Headers
$headers = [
    '#', 'Employee Name', 'Emp ID', 'Email', 'Department', 'Designation',
    'Gross Salary (Annual)', 'Monthly CTC',
    'Basic Salary', 'HRA', 'Special Allowance', 'Conveyance',
    'Education Allowance', 'LTA', 'Mediclaim Insurance',
    'Medical Reimbursement', 'Mobile & Internet', 'Personal Allowance',
    'Bonus (Yearly)', 'TOTAL EARNINGS',
    'Professional Tax', 'Tax Regime', 'ESI Rate (%)', 'PF Rate (%)',
    'ESI Amount', 'PF Amount',
];
foreach ($allCustomDedNames as $cdName) {
    $headers[] = $cdName;
}
$headers[] = 'TOTAL DEDUCTIONS';
$headers[] = 'NET PAYABLE';

// Monthly columns
for ($m = $monthFrom; $m <= $monthTo; $m++) {
    $headers[] = date('M', mktime(0,0,0,$m,1)) . ' ' . $year . ' Net';
}

fputcsv($output, $headers);

// Data rows
foreach ($data as $idx => $r) {
    $basic = (float)$r['basic_salary'];
    $totalEarnings = $basic + (float)$r['hra'] + (float)$r['special_allowance'] + (float)$r['conveyance'] + (float)$r['education_allowance'] + (float)$r['lta'] + (float)$r['mediclaim_insurance'] + (float)$r['medical_reimbursement'] + (float)$r['mobile_internet'] + (float)$r['personal_allowance'];

    $monthlyProfTax = round((float)$r['professional_tax'] / 12, 2);
    $monthlyESI = round(($basic * (float)$r['esi_rate']) / 100, 2);
    $monthlyPF = round(($basic * (float)$r['pf_rate']) / 100, 2);

    $customDeds = json_decode($r['custom_deductions'] ?? '[]', true) ?: [];
    $customDedMap = [];
    $customDedTotal = 0;
    foreach ($customDeds as $cd) {
        $customDedMap[$cd['name']] = (float)$cd['amount'];
        $customDedTotal += (float)$cd['amount'];
    }

    $totalDeductions = $monthlyProfTax + $monthlyESI + $monthlyPF + $customDedTotal;
    $netPayable = $totalEarnings - $totalDeductions;

    $row = [
        $idx + 1,
        $r['full_name'],
        $r['emp_code'],
        $r['email'],
        $r['dept_name'] ?? '',
        $r['job_title'] ?? '',
        number_format((float)$r['gross_salary'], 2, '.', ''),
        number_format((float)$r['gross_salary'] / 12, 2, '.', ''),
        number_format($basic, 2, '.', ''),
        number_format((float)$r['hra'], 2, '.', ''),
        number_format((float)$r['special_allowance'], 2, '.', ''),
        number_format((float)$r['conveyance'], 2, '.', ''),
        number_format((float)$r['education_allowance'], 2, '.', ''),
        number_format((float)$r['lta'], 2, '.', ''),
        number_format((float)$r['mediclaim_insurance'], 2, '.', ''),
        number_format((float)$r['medical_reimbursement'], 2, '.', ''),
        number_format((float)$r['mobile_internet'], 2, '.', ''),
        number_format((float)$r['personal_allowance'], 2, '.', ''),
        number_format((float)$r['bonus'], 2, '.', ''),
        number_format($totalEarnings, 2, '.', ''),
        number_format($monthlyProfTax, 2, '.', ''),
        ucfirst($r['tax_regime']),
        $r['esi_rate'],
        $r['pf_rate'],
        number_format($monthlyESI, 2, '.', ''),
        number_format($monthlyPF, 2, '.', ''),
    ];

    // Custom deductions
    foreach ($allCustomDedNames as $cdName) {
        $row[] = number_format($customDedMap[$cdName] ?? 0, 2, '.', '');
    }

    $row[] = number_format($totalDeductions, 2, '.', '');
    $row[] = number_format($netPayable, 2, '.', '');

    // Monthly net
    for ($m = $monthFrom; $m <= $monthTo; $m++) {
        $mNet = $netPayable;
        if ($m === 12) $mNet += (float)$r['bonus'];
        $row[] = number_format($mNet, 2, '.', '');
    }

    fputcsv($output, $row);
}

// Summary row
fputcsv($output, []);
fputcsv($output, ['', '', '', '', '', 'Generated on: ' . date('d M Y h:i A')]);

fclose($output);
exit;
