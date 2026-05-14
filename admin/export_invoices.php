<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$filterProject = (int)($_GET['project_id'] ?? 0);
$filterStatus  = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

$where = ["1=1"]; $params = [];
if ($filterProject) { $where[] = "pi.project_id=?"; $params[] = $filterProject; }
if ($filterStatus) { $where[] = "pi.status=?"; $params[] = $filterStatus; }
if ($search) { $where[] = "(pi.invoice_no LIKE ? OR p.project_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$invoices = $db->prepare("
    SELECT pi.*, p.project_name, p.project_code, p.client_name
    FROM project_invoices pi JOIN projects p ON pi.project_id=p.id
    WHERE " . implode(' AND ', $where) . " ORDER BY pi.invoice_date DESC
");
$invoices->execute($params);
$invoices = $invoices->fetchAll(PDO::FETCH_ASSOC);

if (empty($invoices)) { $_SESSION['flash_error'] = "No invoices to export."; header("Location: invoices.php"); exit; }

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$fname = 'Invoices_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$total = array_sum(array_column($invoices, 'total_amount'));

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
  <Style ss:ID="title"><Font ss:Bold="1" ss:Size="14" ss:Color="#4F46E5"/></Style>
  <Style ss:ID="hdr"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="10"/><Interior ss:Color="#4F46E5" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="d"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="dAlt"><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="tot"><Font ss:Bold="1" ss:Color="#4F46E5"/><Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/></Style>
</Styles>
<Worksheet ss:Name="Invoices">
<Table>
<Column ss:Width="40"/><Column ss:Width="120"/><Column ss:Width="180"/><Column ss:Width="120"/><Column ss:Width="90"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="90"/><Column ss:Width="80"/><Column ss:Width="100"/><Column ss:Width="70"/>
<Row><Cell ss:StyleID="title"><Data ss:Type="String">INVOICE REPORT — <?= date('d M Y') ?></Data></Cell></Row>
<Row></Row>
<Row>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">#</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Invoice No</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Project</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Client</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Date</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Hours</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Rate/Hr</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Subtotal</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Tax</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Total</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Status</Data></Cell>
</Row>
<?php foreach($invoices as $idx => $inv): $rs=($idx%2===0)?'d':'dAlt'; ?>
<Row>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $idx+1 ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= e($inv['invoice_no']) ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= e($inv['project_code'].' - '.$inv['project_name']) ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= e($inv['client_name']??'') ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= $inv['invoice_date'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $inv['utilized_hours'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $inv['rate_per_hour'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $inv['subtotal'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $inv['tax_amount'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="Number"><?= $inv['total_amount'] ?></Data></Cell>
  <Cell ss:StyleID="<?= $rs ?>"><Data ss:Type="String"><?= ucfirst($inv['status']) ?></Data></Cell>
</Row>
<?php endforeach; ?>
<Row>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String">TOTAL</Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="Number"><?= $total ?></Data></Cell>
  <Cell ss:StyleID="tot"><Data ss:Type="String"><?= count($invoices) ?> invoices</Data></Cell>
</Row>
</Table>
</Worksheet>
</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: ' . strlen($xml));
echo $xml; exit;
