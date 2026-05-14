<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$monthFrom = max(1, min(12, (int)($_POST['month_from'] ?? date('n'))));
$monthTo   = max(1, min(12, (int)($_POST['month_to'] ?? date('n'))));
$year      = (int)($_POST['year'] ?? date('Y'));
$userIds   = trim($_POST['user_ids'] ?? '');

if ($monthFrom > $monthTo) { $tmp = $monthFrom; $monthFrom = $monthTo; $monthTo = $tmp; }
$rangeLabel = date('M', mktime(0,0,0,$monthFrom,1)) . '-' . date('M', mktime(0,0,0,$monthTo,1)) . ' ' . $year;

$where = "1=1"; $params = [];
if ($userIds) {
    $ids = array_filter(array_map('intval', explode(',', $userIds)));
    if (!empty($ids)) { $where = "ss.user_id IN (" . implode(',', $ids) . ")"; }
}

$data = $db->prepare("
    SELECT ss.*, u.name AS full_name, e.employee_id AS emp_code, e.job_title,
           d.name AS dept_name, e.email
    FROM salary_structures ss
    JOIN users u ON ss.user_id = u.id
    JOIN employees e ON ss.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE $where ORDER BY u.name ASC
");
$data->execute($params);
$data = $data->fetchAll(PDO::FETCH_ASSOC);

if (empty($data)) { $_SESSION['flash_error'] = "No salary structures to export."; header("Location: payroll.php"); exit; }

// Collect custom deduction names
$allCustomDedNames = [];
foreach ($data as $row) {
    $cds = json_decode($row['custom_deductions'] ?? '[]', true) ?: [];
    foreach ($cds as $cd) { if (!empty($cd['name']) && !in_array($cd['name'], $allCustomDedNames)) $allCustomDedNames[] = $cd['name']; }
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function numCell($v) { return '<Cell><Data ss:Type="Number">' . round((float)$v, 2) . '</Data></Cell>'; }
function strCell($v, $style='') { return '<Cell' . ($style ? " ss:StyleID=\"$style\"" : '') . '><Data ss:Type="String">' . e($v) . '</Data></Cell>'; }
function numStyleCell($v, $style) { return '<Cell ss:StyleID="' . $style . '"><Data ss:Type="Number">' . round((float)$v, 2) . '</Data></Cell>'; }

$fname = 'Salary_Structure_' . str_replace(' ', '_', $rangeLabel) . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
  <Style ss:ID="title"><Font ss:Bold="1" ss:Size="14" ss:Color="#4F46E5"/><Alignment ss:Vertical="Center"/></Style>
  <Style ss:ID="hInfo"><Font ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/><Interior ss:Color="#4F46E5" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
  <Style ss:ID="hEarn"><Font ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/><Interior ss:Color="#059669" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
  <Style ss:ID="hDed"><Font ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/><Interior ss:Color="#DC2626" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
  <Style ss:ID="hNet"><Font ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/><Interior ss:Color="#7C3AED" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
  <Style ss:ID="d"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="dAlt"><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="net"><Font ss:Bold="1" ss:Color="#4F46E5"/><Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="netAlt"><Font ss:Bold="1" ss:Color="#4F46E5"/><Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="totE"><Font ss:Bold="1" ss:Color="#059669"/><Alignment ss:Horizontal="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="totD"><Font ss:Bold="1" ss:Color="#DC2626"/><Alignment ss:Horizontal="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
</Styles>
<Worksheet ss:Name="Salary Structure">
<Table>
<?php
// Column widths
$colWidths = [30, 160, 80, 200, 120, 120];
for ($i = 0; $i < 20 + count($allCustomDedNames) + ($monthTo - $monthFrom + 1); $i++) $colWidths[] = 100;
foreach ($colWidths as $w): ?>
<Column ss:Width="<?= $w ?>"/>
<?php endforeach; ?>

<!-- Title -->
<Row ss:Height="28">
  <Cell ss:StyleID="title"><Data ss:Type="String">SALARY STRUCTURE REPORT — <?= e(strtoupper($rangeLabel)) ?></Data></Cell>
</Row>
<Row></Row>

<!-- Headers -->
<Row ss:Height="24">
<?php
$infoH = ['#','Employee Name','Emp ID','Email','Department','Designation'];
foreach ($infoH as $h) echo '<Cell ss:StyleID="hInfo"><Data ss:Type="String">' . e($h) . '</Data></Cell>';

$earnH = ['Gross (Annual)','Monthly CTC','Basic Salary','HRA','Special Allow.','Conveyance','Education Allow.','LTA','Mediclaim','Medical Reimb.','Mobile & Internet','Personal Allow.','Bonus (Yearly)','Total Earnings'];
foreach ($earnH as $h) echo '<Cell ss:StyleID="hEarn"><Data ss:Type="String">' . e($h) . '</Data></Cell>';

$dedH = ['Income Tax (Monthly)','Tax Regime','ESI (%)','PF (%)','ESI Amount','PF Amount'];
foreach ($dedH as $h) echo '<Cell ss:StyleID="hDed"><Data ss:Type="String">' . e($h) . '</Data></Cell>';
foreach ($allCustomDedNames as $cdName) echo '<Cell ss:StyleID="hDed"><Data ss:Type="String">' . e($cdName) . '</Data></Cell>';
echo '<Cell ss:StyleID="hDed"><Data ss:Type="String">Total Deductions</Data></Cell>';

echo '<Cell ss:StyleID="hNet"><Data ss:Type="String">NET PAYABLE</Data></Cell>';
for ($m = $monthFrom; $m <= $monthTo; $m++) echo '<Cell ss:StyleID="hNet"><Data ss:Type="String">' . date('M', mktime(0,0,0,$m,1)) . " $year" . '</Data></Cell>';
?>
</Row>

<!-- Data -->
<?php foreach ($data as $idx => $r):
    $basic = (float)$r['basic_salary'];
    $totalEarnings = $basic + (float)$r['hra'] + (float)$r['special_allowance'] + (float)$r['conveyance'] + (float)$r['education_allowance'] + (float)$r['lta'] + (float)$r['mediclaim_insurance'] + (float)$r['medical_reimbursement'] + (float)$r['mobile_internet'] + (float)$r['personal_allowance'];
    $monthlyProfTax = round((float)$r['professional_tax'] / 12, 2);
    $monthlyESI = round(($basic * (float)$r['esi_rate']) / 100, 2);
    $monthlyPF = round(($basic * (float)$r['pf_rate']) / 100, 2);
    $customDeds = json_decode($r['custom_deductions'] ?? '[]', true) ?: [];
    $customDedMap = []; $customDedTotal = 0;
    foreach ($customDeds as $cd) { $customDedMap[$cd['name']] = (float)$cd['amount']; $customDedTotal += (float)$cd['amount']; }
    $totalDeductions = $monthlyProfTax + $monthlyESI + $monthlyPF + $customDedTotal;
    $netPayable = $totalEarnings - $totalDeductions;
    $rowStyle = ($idx % 2 === 0) ? 'd' : 'dAlt';
    $netS = ($idx % 2 === 0) ? 'net' : 'netAlt';
?>
<Row>
  <Cell ss:StyleID="<?= $rowStyle ?>"><Data ss:Type="Number"><?= $idx+1 ?></Data></Cell>
  <Cell ss:StyleID="<?= $rowStyle ?>"><Data ss:Type="String"><?= e($r['full_name']) ?></Data></Cell>
  <Cell ss:StyleID="<?= $rowStyle ?>"><Data ss:Type="String"><?= e($r['emp_code']) ?></Data></Cell>
  <Cell ss:StyleID="<?= $rowStyle ?>"><Data ss:Type="String"><?= e($r['email']) ?></Data></Cell>
  <Cell ss:StyleID="<?= $rowStyle ?>"><Data ss:Type="String"><?= e($r['dept_name'] ?? '') ?></Data></Cell>
  <Cell ss:StyleID="<?= $rowStyle ?>"><Data ss:Type="String"><?= e($r['job_title'] ?? '') ?></Data></Cell>
  <?= numStyleCell($r['gross_salary'], $rowStyle) ?>
  <?= numStyleCell((float)$r['gross_salary']/12, $rowStyle) ?>
  <?= numStyleCell($basic, $rowStyle) ?>
  <?= numStyleCell($r['hra'], $rowStyle) ?>
  <?= numStyleCell($r['special_allowance'], $rowStyle) ?>
  <?= numStyleCell($r['conveyance'], $rowStyle) ?>
  <?= numStyleCell($r['education_allowance'], $rowStyle) ?>
  <?= numStyleCell($r['lta'], $rowStyle) ?>
  <?= numStyleCell($r['mediclaim_insurance'], $rowStyle) ?>
  <?= numStyleCell($r['medical_reimbursement'], $rowStyle) ?>
  <?= numStyleCell($r['mobile_internet'], $rowStyle) ?>
  <?= numStyleCell($r['personal_allowance'], $rowStyle) ?>
  <?= numStyleCell($r['bonus'], $rowStyle) ?>
  <?= numStyleCell($totalEarnings, 'totE') ?>
  <?= numStyleCell($monthlyProfTax, $rowStyle) ?>
  <Cell ss:StyleID="<?= $rowStyle ?>"><Data ss:Type="String"><?= e(ucfirst($r['tax_regime'])) ?></Data></Cell>
  <?= numStyleCell($r['esi_rate'], $rowStyle) ?>
  <?= numStyleCell($r['pf_rate'], $rowStyle) ?>
  <?= numStyleCell($monthlyESI, $rowStyle) ?>
  <?= numStyleCell($monthlyPF, $rowStyle) ?>
  <?php foreach ($allCustomDedNames as $cdName): ?>
  <?= numStyleCell($customDedMap[$cdName] ?? 0, $rowStyle) ?>
  <?php endforeach; ?>
  <?= numStyleCell($totalDeductions, 'totD') ?>
  <?= numStyleCell($netPayable, $netS) ?>
  <?php for ($m = $monthFrom; $m <= $monthTo; $m++):
    $mNet = $netPayable; if ($m === 12) $mNet += (float)$r['bonus'];
  ?>
  <?= numStyleCell($mNet, $netS) ?>
  <?php endfor; ?>
</Row>
<?php endforeach; ?>

</Table>
</Worksheet>
</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: ' . strlen($xml));
echo $xml;
exit;
