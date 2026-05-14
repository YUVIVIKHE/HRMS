<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    $_SESSION['flash_error'] = "PhpSpreadsheet not installed. Run: composer install";
    header("Location: payroll.php"); exit;
}

guardRole('admin');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$db = getDB();

$monthFrom = max(1, min(12, (int)($_POST['month_from'] ?? date('n'))));
$monthTo   = max(1, min(12, (int)($_POST['month_to'] ?? date('n'))));
$year      = (int)($_POST['year'] ?? date('Y'));
$userIds   = trim($_POST['user_ids'] ?? '');

if ($monthFrom > $monthTo) { $tmp = $monthFrom; $monthFrom = $monthTo; $monthTo = $tmp; }

$rangeLabel = date('M', mktime(0,0,0,$monthFrom,1)) . '-' . date('M', mktime(0,0,0,$monthTo,1)) . ' ' . $year;

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

// Helper to get cell reference
function cell($col, $row) {
    return Coordinate::stringFromColumnIndex($col) . $row;
}

try {

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Salary Structure');

// ── Title ────────────────────────────────────────────────────
$sheet->setCellValue('A1', 'SALARY STRUCTURE REPORT — ' . strtoupper($rangeLabel));
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('4F46E5');
$sheet->getRowDimension(1)->setRowHeight(28);

// ── Build Headers (Row 3) ────────────────────────────────────
$col = 1;
$row = 3;

$allHeaders = ['#', 'Employee Name', 'Emp ID', 'Email', 'Department', 'Designation'];
$infoEndCol = count($allHeaders);

$earningHeaders = ['Gross (Annual)', 'Monthly CTC', 'Basic Salary', 'HRA', 'Special Allow.', 'Conveyance', 'Education Allow.', 'LTA', 'Mediclaim', 'Medical Reimb.', 'Mobile & Internet', 'Personal Allow.', 'Bonus (Yearly)', 'Total Earnings'];
$earningStartCol = $infoEndCol + 1;
$earningEndCol = $earningStartCol + count($earningHeaders) - 1;

$deductionHeaders = ['Prof. Tax', 'Tax Regime', 'ESI (%)', 'PF (%)', 'ESI Amount', 'PF Amount'];
foreach ($allCustomDedNames as $cdName) $deductionHeaders[] = $cdName;
$deductionHeaders[] = 'Total Deductions';
$dedStartCol = $earningEndCol + 1;
$dedEndCol = $dedStartCol + count($deductionHeaders) - 1;

$netCol = $dedEndCol + 1;
$monthHeaders = [];
for ($m = $monthFrom; $m <= $monthTo; $m++) $monthHeaders[] = date('M', mktime(0,0,0,$m,1)) . " $year";
$monthStartCol = $netCol + 1;
$monthEndCol = $monthStartCol + count($monthHeaders) - 1;

// Write all headers
$allHeadersFull = array_merge($allHeaders, $earningHeaders, $deductionHeaders, ['NET PAYABLE'], $monthHeaders);
foreach ($allHeadersFull as $i => $h) {
    $sheet->setCellValue(cell($i + 1, $row), $h);
}
$totalCols = count($allHeadersFull);

// Style headers
$headerRange = cell(1, 3) . ':' . cell($infoEndCol, 3);
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

$sheet->getStyle(cell($earningStartCol, 3) . ':' . cell($earningEndCol, 3))->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

$sheet->getStyle(cell($dedStartCol, 3) . ':' . cell($dedEndCol, 3))->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

$sheet->getStyle(cell($netCol, 3) . ':' . cell($monthEndCol > $netCol ? $monthEndCol : $netCol, 3))->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

$sheet->getRowDimension(3)->setRowHeight(22);

// ── Data Rows ────────────────────────────────────────────────
$row = 4;
foreach ($data as $idx => $r) {
    $basic = (float)$r['basic_salary'];
    $totalEarnings = $basic + (float)$r['hra'] + (float)$r['special_allowance'] + (float)$r['conveyance'] + (float)$r['education_allowance'] + (float)$r['lta'] + (float)$r['mediclaim_insurance'] + (float)$r['medical_reimbursement'] + (float)$r['mobile_internet'] + (float)$r['personal_allowance'];
    $monthlyProfTax = round((float)$r['professional_tax'] / 12, 2);
    $monthlyESI = round(($basic * (float)$r['esi_rate']) / 100, 2);
    $monthlyPF = round(($basic * (float)$r['pf_rate']) / 100, 2);

    $customDeds = json_decode($r['custom_deductions'] ?? '[]', true) ?: [];
    $customDedMap = [];
    $customDedTotal = 0;
    foreach ($customDeds as $cd) { $customDedMap[$cd['name']] = (float)$cd['amount']; $customDedTotal += (float)$cd['amount']; }

    $totalDeductions = $monthlyProfTax + $monthlyESI + $monthlyPF + $customDedTotal;
    $netPayable = $totalEarnings - $totalDeductions;

    $values = [
        $idx + 1, $r['full_name'], $r['emp_code'], $r['email'], $r['dept_name'] ?? '', $r['job_title'] ?? '',
        (float)$r['gross_salary'], round((float)$r['gross_salary']/12, 2), $basic, (float)$r['hra'],
        (float)$r['special_allowance'], (float)$r['conveyance'], (float)$r['education_allowance'],
        (float)$r['lta'], (float)$r['mediclaim_insurance'], (float)$r['medical_reimbursement'],
        (float)$r['mobile_internet'], (float)$r['personal_allowance'], (float)$r['bonus'],
        round($totalEarnings, 2),
        $monthlyProfTax, ucfirst($r['tax_regime']), (float)$r['esi_rate'], (float)$r['pf_rate'],
        $monthlyESI, $monthlyPF,
    ];

    // Custom deductions
    foreach ($allCustomDedNames as $cdName) $values[] = $customDedMap[$cdName] ?? 0;
    $values[] = round($totalDeductions, 2);
    $values[] = round($netPayable, 2);

    // Monthly
    for ($m = $monthFrom; $m <= $monthTo; $m++) {
        $mNet = $netPayable;
        if ($m === 12) $mNet += (float)$r['bonus'];
        $values[] = round($mNet, 2);
    }

    foreach ($values as $i => $v) {
        $sheet->setCellValue(cell($i + 1, $row), $v);
    }

    // Alternate row color
    $rowColor = ($idx % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
    $sheet->getStyle(cell(1, $row) . ':' . cell($totalCols, $row))->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ]);

    // Net payable highlight
    $sheet->getStyle(cell($netCol, $row))->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '4F46E5']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDE9FE']],
    ]);

    $row++;
}

// Auto-size columns (limit to avoid timeout)
for ($c = 1; $c <= min($totalCols, 30); $c++) {
    $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
}

// Freeze panes
$sheet->freezePane('G4');

// ── Output ───────────────────────────────────────────────────
$filename = 'Salary_Structure_' . str_replace(' ', '_', $rangeLabel) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

} catch (Exception $e) {
    $_SESSION['flash_error'] = "Export error: " . $e->getMessage();
    header("Location: payroll.php"); exit;
}
