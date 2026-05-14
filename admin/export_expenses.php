<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$filterProject = (int)($_GET['project_id'] ?? 0);
$filterCat     = $_GET['category'] ?? '';
$filterFrom    = trim($_GET['date_from'] ?? '');
$filterTo      = trim($_GET['date_to'] ?? '');

$where = ["1=1"]; $params = [];
if ($filterProject) { $where[] = "pe.project_id=?"; $params[] = $filterProject; }
if ($filterCat) { $where[] = "pe.category=?"; $params[] = $filterCat; }
if ($filterFrom) { $where[] = "pe.expense_date>=?"; $params[] = $filterFrom; }
if ($filterTo) { $where[] = "pe.expense_date<=?"; $params[] = $filterTo; }

$expenses = $db->prepare("
    SELECT pe.*, p.project_name, p.project_code
    FROM project_expenses pe
    JOIN projects p ON pe.project_id = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY pe.expense_date DESC
");
$expenses->execute($params);
$expenses = $expenses->fetchAll(PDO::FETCH_ASSOC);

if (empty($expenses)) {
    $_SESSION['flash_error'] = "No expenses to export.";
    header("Location: expenses.php"); exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$projName = $filterProject ? $expenses[0]['project_name'] : 'All_Projects';
$fname = 'Expenses_' . preg_replace('/[^a-zA-Z0-9]/', '_', $projName) . '_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

// Category totals
$catTotals = ['Travel'=>0,'Food'=>0,'Hotel'=>0,'Other'=>0];
$total = 0;
foreach ($expenses as $ex) { $catTotals[$ex['category']] += (float)$ex['amount']; $total += (float)$ex['amount']; }

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
  <Style ss:ID="title"><Font ss:Bold="1" ss:Size="14" ss:Color="#4F46E5"/></Style>
  <Style ss:ID="hdr"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="10"/><Interior ss:Color="#4F46E5" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
  <Style ss:ID="d"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="dAlt"><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="tot"><Font ss:Bold="1" ss:Color="#4F46E5"/><Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/></Style>
  <Style ss:ID="sum"><Font ss:Bold="1" ss:Size="11"/><Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/></Style>
</Styles>
<Worksheet ss:Name="Expense Report">
<Table>
<Column ss:Width="30"/><Column ss:Width="100"/><Column ss:Width="180"/><Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="250"/><Column ss:Width="100"/>

<Row><Cell ss:StyleID="title"><Data ss:Type="String">EXPENSE REPORT — <?= e($projName) ?></Data></Cell></Row>
<Row><Cell><Data ss:Type="String">Generated: <?= date('d M Y') ?><?= $filterFrom ? " | From: $filterFrom" : '' ?><?= $filterTo ? " To: $filterTo" : '' ?></Data></Cell></Row>
<Row></Row>

<!-- Summary -->
<Row>
  <Cell ss:StyleID="sum"><Data ss:Type="String">Total</Data></Cell>
  <Cell ss:StyleID="sum"><Data ss:Type="Number"><?= $total ?></Data></Cell>
  <Cell ss:StyleID="sum"><Data ss:Type="String">Travel: ₹<?= number_format($catTotals['Travel'],0) ?></Data></Cell>
  <Cell ss:StyleID="sum"><Data ss:Type="String">Food: ₹<?= number_format($catTotals['Food'],0) ?></Data></Cell>
  <Cell ss:StyleID="sum"><Data ss:Type="String">Hotel: ₹<?= number_format($catTotals['Hotel'],0) ?></Data></Cell>
  <Cell ss:StyleID="sum"><Data ss:Type="String">Other: ₹<?= number_format($catTotals['Other'],0) ?></Data></Cell>
</Row>
<Row></Row>

<!-- Headers -->
<Row>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">#</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Date</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Project</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Category</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Amount (₹)</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Description</Data></Cell>
</Row>

<?php foreach ($expenses as $idx => $ex): $rs = ($idx%2===0)?'d':'dAlt'; ?>
<Row>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $idx+1 ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= $ex['expense_date'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= e($ex['project_code'].' - '.$ex['project_name']) ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= e($ex['category']) ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $ex['amount'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= e($ex['description'] ?? '') ?></Data></Cell>
</Row>
<?php endforeach; ?>

<!-- Total row -->
<Row>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String">TOTAL</Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="Number"><?= $total ?></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"><?= count($expenses) ?> entries</Data></Cell>
</Row>

</Table>
</Worksheet>
</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: ' . strlen($xml));
echo $xml;
exit;
