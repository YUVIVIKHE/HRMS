<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
guardRole('admin');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Salary Structure');

// ── Styles ───────────────────────────────────────────────────
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C7D2FE']]],
];
$earningHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$deductionHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$netStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];
$currencyFormat = '₹#,##0.00';

// ── Title Row ────────────────────────────────────────────────
$sheet->setCellValue('A1', 'SALARY STRUCTURE REPORT — ' . strtoupper($rangeLabel));
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '4F46E5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// ── Build Headers (Row 3) ────────────────────────────────────
$col = 1;
$row = 3;

// Employee info headers
$infoHeaders = ['#', 'Employee Name', 'Emp ID', 'Email', 'Department', 'Designation'];
foreach ($infoHeaders as $h) { $sheet->setCellValueByColumnAndRow($col++, $row, $h); }

// Earnings headers
$earningHeaders = ['Gross (Annual)', 'Monthly CTC', 'Basic Salary', 'HRA', 'Special Allow.', 'Conveyance', 'Education Allow.', 'LTA', 'Mediclaim', 'Medical Reimb.', 'Mobile & Internet', 'Personal Allow.', 'Bonus (Yearly)', 'Total Earnings'];
$earningStartCol = $col;
foreach ($earningHeaders as $h) { $sheet->setCellValueByColumnAndRow($col++, $row, $h); }
$earningEndCol = $col - 1;

// Deduction headers
$deductionHeaders = ['Prof. Tax', 'Tax Regime', 'ESI (%)', 'PF (%)', 'ESI Amount', 'PF Amount'];
$dedStartCol = $col;
foreach ($deductionHeaders as $h) { $sheet->setCellValueByColumnAndRow($col++, $row, $h); }
// Custom deductions
foreach ($allCustomDedNames as $cdName) { $sheet->setCellValueByColumnAndRow($col++, $row, $cdName); }
$sheet->setCellValueByColumnAndRow($col++, $row, 'Total Deductions');
$dedEndCol = $col - 1;

// Net payable
$netCol = $col;
$sheet->setCellValueByColumnAndRow($col++, $row, 'NET PAYABLE');

// Monthly columns
$monthStartCol = $col;
for ($m = $monthFrom; $m <= $monthTo; $m++) {
    $sheet->setCellValueByColumnAndRow($col++, $row, date('M', mktime(0,0,0,$m,1)) . " $year");
}
$monthEndCol = $col - 1;
$totalCols = $col - 1;

// Apply header styles
$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1) . '3:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($infoHeaders)) . '3')->applyFromArray($headerStyle);
$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($earningStartCol) . '3:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($earningEndCol) . '3')->applyFromArray($earningHeaderStyle);
$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dedStartCol) . '3:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dedEndCol) . '3')->applyFromArray($deductionHeaderStyle);
$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($netCol) . '3')->applyFromArray($netStyle);
if ($monthStartCol <= $monthEndCol) {
    $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($monthStartCol) . '3:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($monthEndCol) . '3')->applyFromArray($netStyle);
}

$sheet->getRowDimension(3)->setRowHeight(24);

// ── Data Rows ────────────────────────────────────────────────
$row = 4;
foreach ($data as $idx => $r) {
    $col = 1;
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

    // Info
    $sheet->setCellValueByColumnAndRow($col++, $row, $idx + 1);
    $sheet->setCellValueByColumnAndRow($col++, $row, $r['full_name']);
    $sheet->setCellValueByColumnAndRow($col++, $row, $r['emp_code']);
    $sheet->setCellValueByColumnAndRow($col++, $row, $r['email']);
    $sheet->setCellValueByColumnAndRow($col++, $row, $r['dept_name'] ?? '');
    $sheet->setCellValueByColumnAndRow($col++, $row, $r['job_title'] ?? '');

    // Earnings
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['gross_salary']);
    $sheet->setCellValueByColumnAndRow($col++, $row, round((float)$r['gross_salary']/12, 2));
    $sheet->setCellValueByColumnAndRow($col++, $row, $basic);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['hra']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['special_allowance']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['conveyance']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['education_allowance']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['lta']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['mediclaim_insurance']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['medical_reimbursement']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['mobile_internet']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['personal_allowance']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['bonus']);
    $sheet->setCellValueByColumnAndRow($col++, $row, round($totalEarnings, 2));

    // Deductions
    $sheet->setCellValueByColumnAndRow($col++, $row, $monthlyProfTax);
    $sheet->setCellValueByColumnAndRow($col++, $row, ucfirst($r['tax_regime']));
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['esi_rate']);
    $sheet->setCellValueByColumnAndRow($col++, $row, (float)$r['pf_rate']);
    $sheet->setCellValueByColumnAndRow($col++, $row, $monthlyESI);
    $sheet->setCellValueByColumnAndRow($col++, $row, $monthlyPF);
    foreach ($allCustomDedNames as $cdName) {
        $sheet->setCellValueByColumnAndRow($col++, $row, $customDedMap[$cdName] ?? 0);
    }
    $sheet->setCellValueByColumnAndRow($col++, $row, round($totalDeductions, 2));

    // Net
    $sheet->setCellValueByColumnAndRow($col++, $row, round($netPayable, 2));

    // Monthly
    for ($m = $monthFrom; $m <= $monthTo; $m++) {
        $mNet = $netPayable;
        if ($m === 12) $mNet += (float)$r['bonus'];
        $sheet->setCellValueByColumnAndRow($col++, $row, round($mNet, 2));
    }

    // Alternate row color
    $rowColor = ($idx % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
    $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1) . $row . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols) . $row)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ]);

    // Net payable cell highlight
    $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($netCol) . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '4F46E5']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDE9FE']],
    ]);

    $row++;
}

// Auto-size columns
for ($c = 1; $c <= $totalCols; $c++) {
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
