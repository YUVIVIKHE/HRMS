<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

// Get all approved/pending leave applications with details
$leaves = $db->query("
    SELECT la.*, lt.name AS leave_type, u.name AS emp_name, u.role,
           e.employee_id AS emp_code, d.name AS dept_name
    FROM leave_applications la
    JOIN users u ON la.user_id = u.id
    JOIN employees e ON e.email = u.email
    LEFT JOIN leave_types lt ON la.leave_type_id = lt.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE la.status IN ('approved','pending')
    ORDER BY la.from_date DESC, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($leaves)) {
    $_SESSION['flash_error'] = "No leave records to export.";
    header("Location: leaves.php"); exit;
}

// Get balances for each user/type
$balMap = [];
$balRows = $db->query("SELECT user_id, leave_type_id, balance FROM leave_balances")->fetchAll();
foreach ($balRows as $b) $balMap[$b['user_id']][$b['leave_type_id']] = (float)$b['balance'];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$fname = 'Leave_Report_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
<Style ss:ID="title"><Font ss:Bold="1" ss:Size="14" ss:Color="#4F46E5"/></Style>
<Style ss:ID="hdr"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="9"/><Interior ss:Color="#4F46E5" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:WrapText="1"/></Style>
<Style ss:ID="d"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
<Style ss:ID="dAlt"><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
<Style ss:ID="approved"><Font ss:Bold="1" ss:Color="#059669"/><Alignment ss:Horizontal="Center"/></Style>
<Style ss:ID="pending"><Font ss:Bold="1" ss:Color="#D97706"/><Alignment ss:Horizontal="Center"/></Style>
</Styles>
<Worksheet ss:Name="Leave Report">
<Table>
<Column ss:Width="40"/><Column ss:Width="140"/><Column ss:Width="80"/><Column ss:Width="70"/><Column ss:Width="120"/><Column ss:Width="90"/><Column ss:Width="90"/><Column ss:Width="50"/><Column ss:Width="100"/><Column ss:Width="70"/><Column ss:Width="80"/><Column ss:Width="200"/>

<Row><Cell ss:StyleID="title"><Data ss:Type="String">LEAVE REPORT — Generated <?= date('d M Y') ?></Data></Cell></Row>
<Row><Cell><Data ss:Type="String">Total Records: <?= count($leaves) ?></Data></Cell></Row>
<Row></Row>

<Row ss:Height="20">
<Cell ss:StyleID="hdr"><Data ss:Type="String">#</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Employee Name</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Emp ID</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Role</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Department</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">From Date</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">To Date</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Days</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Leave Type</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Status</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Remaining Balance</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Reason</Data></Cell>
</Row>

<?php foreach($leaves as $idx => $l):
  $rs = ($idx%2===0)?'d':'dAlt';
  $typeName = $l['leave_type'] ?: 'ACL';
  $remaining = $balMap[$l['user_id']][$l['leave_type_id']] ?? 0;
  $stStyle = $l['status']==='approved' ? 'approved' : 'pending';
?>
<Row>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="Number"><?=$idx+1?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($l['emp_name'])?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($l['emp_code']??'')?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=ucfirst($l['role'])?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($l['dept_name']??'')?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=date('d-M-Y',strtotime($l['from_date']))?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=date('d-M-Y',strtotime($l['to_date']))?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="Number"><?=$l['days']?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($typeName)?></Data></Cell>
<Cell ss:StyleID="<?=$stStyle?>"><Data ss:Type="String"><?=ucfirst($l['status'])?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="Number"><?=$remaining?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($l['reason']??'')?></Data></Cell>
</Row>
<?php endforeach;?>

</Table>
</Worksheet>
</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: ' . strlen($xml));
echo $xml; exit;
