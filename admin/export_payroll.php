<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$monthFrom = max(1, min(12, (int)($_GET['month_from'] ?? date('n'))));
$monthTo   = max(1, min(12, (int)($_GET['month_to'] ?? date('n'))));
$year      = (int)($_GET['year'] ?? date('Y'));

if ($monthFrom > $monthTo) { $tmp = $monthFrom; $monthFrom = $monthTo; $monthTo = $tmp; }

$monthNames = [];
for ($m = $monthFrom; $m <= $monthTo; $m++) {
    $monthNames[] = date('M', mktime(0, 0, 0, $m, 1));
}
$rangeLabel = $monthNames[0] . ($monthFrom !== $monthTo ? '-' . end($monthNames) : '') . ' ' . $year;

// Get all salary structures with employee info
$data = $db->query("
    SELECT ss.*, u.name AS full_name, e.employee_id AS emp_code, e.job_title,
           d.name AS dept_name, e.email
    FROM salary_structures ss
    JOIN users u ON ss.user_id = u.id
    JOIN employees e ON ss.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($data)) {
    $_SESSION['flash_error'] = "No salary structures to export.";
    header("Location: payroll.php"); exit;
}

// Collect all unique custom deduction names across all employees
$allCustomDedNames = [];
foreach ($data as $row) {
    $cds = json_decode($row['custom_deductions'] ?? '[]', true) ?: [];
    foreach ($cds as $cd) {
        if (!empty($cd['name']) && !in_array($cd['name'], $allCustomDedNames)) {
            $allCustomDedNames[] = $cd['name'];
        }
    }
}

// Build CSV
$filename = "Payroll_" . str_replace(' ', '_', $rangeLabel) . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8
fwrite($output, "\xEF\xBB\xBF");

// Headers
$headers = [
    'Employee Name', 'Employee ID', 'Email', 'Department', 'Designation',
    'Gross Salary (Annual)', 'Monthly CTC',
    'Basic Salary', 'HRA', 'Special Allowance', 'Conveyance',
    'Education Allowance', 'LTA', 'Mediclaim Insurance',
    'Medical Reimbursement', 'Mobile & Internet', 'Personal Allowance',
    'Bonus (Yearly)',
    'Total Earnings',
    'Professional Tax', 'Tax Regime', 'ESI Rate (%)', 'PF Rate (%)',
    'ESI Amount', 'PF Amount',
];

// Add custom deduction columns dynamically
foreach ($allCustomDedNames as $cdName) {
    $headers[] = $cdName;
}

$headers[] = 'Total Deductions';
$headers[] = 'Net Payable';

// Add month columns
$numMonths = $monthTo - $monthFrom + 1;
for ($m = $monthFrom; $m <= $monthTo; $m++) {
    $headers[] = date('M', mktime(0, 0, 0, $m, 1)) . ' ' . $year . ' Net';
}

fputcsv($output, $headers);

// Data rows
foreach ($data as $row) {
    $basic = (float)$row['basic_salary'];
    $totalEarnings = $basic + (float)$row['hra'] + (float)$row['special_allowance'] + (float)$row['conveyance'] + (float)$row['education_allowance'] + (float)$row['lta'] + (float)$row['mediclaim_insurance'] + (float)$row['medical_reimbursement'] + (float)$row['mobile_internet'] + (float)$row['personal_allowance'];

    $monthlyProfTax = round((float)$row['professional_tax'] / 12, 2);
    $monthlyESI = round(($basic * (float)$row['esi_rate']) / 100, 2);
    $monthlyPF = round(($basic * (float)$row['pf_rate']) / 100, 2);

    $customDeds = json_decode($row['custom_deductions'] ?? '[]', true) ?: [];
    $customDedMap = [];
    $customDedTotal = 0;
    foreach ($customDeds as $cd) {
        $customDedMap[$cd['name']] = (float)$cd['amount'];
        $customDedTotal += (float)$cd['amount'];
    }

    $totalDeductions = $monthlyProfTax + $monthlyESI + $monthlyPF + $customDedTotal;
    $netPayable = $totalEarnings - $totalDeductions;

    $csvRow = [
        $row['full_name'],
        $row['emp_code'],
        $row['email'],
        $row['dept_name'] ?? '',
        $row['job_title'] ?? '',
        $row['gross_salary'],
        round((float)$row['gross_salary'] / 12, 2),
        $basic,
        $row['hra'],
        $row['special_allowance'],
        $row['conveyance'],
        $row['education_allowance'],
        $row['lta'],
        $row['mediclaim_insurance'],
        $row['medical_reimbursement'],
        $row['mobile_internet'],
        $row['personal_allowance'],
        $row['bonus'],
        round($totalEarnings, 2),
        round($monthlyProfTax, 2),
        ucfirst($row['tax_regime']),
        $row['esi_rate'],
        $row['pf_rate'],
        round($monthlyESI, 2),
        round($monthlyPF, 2),
    ];

    // Custom deduction columns (dynamic)
    foreach ($allCustomDedNames as $cdName) {
        $csvRow[] = $customDedMap[$cdName] ?? 0;
    }

    $csvRow[] = round($totalDeductions, 2);
    $csvRow[] = round($netPayable, 2);

    // Monthly net columns (same net for each month, bonus added in Dec)
    for ($m = $monthFrom; $m <= $monthTo; $m++) {
        $monthNet = $netPayable;
        if ($m === 12) $monthNet += (float)$row['bonus']; // Bonus in December
        $csvRow[] = round($monthNet, 2);
    }

    fputcsv($output, $csvRow);
}

fclose($output);
exit;
