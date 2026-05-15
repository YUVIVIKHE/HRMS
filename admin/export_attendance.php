<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$filterFrom = $_GET['from'] ?? date('Y-m-01');
$filterTo   = $_GET['to'] ?? date('Y-m-d');
$filterUser = (int)($_GET['user_id'] ?? 0);
$filterRole = $_GET['role'] ?? '';

if ($filterFrom > $filterTo) $filterFrom = $filterTo;

$where  = ["al.log_date BETWEEN ? AND ?"];
$params = [$filterFrom, $filterTo];
if ($filterUser > 0) { $where[] = "al.user_id = ?"; $params[] = $filterUser; }
if ($filterRole)     { $where[] = "u.role = ?";     $params[] = $filterRole; }

$stmt = $db->prepare("
    SELECT al.*, u.name AS user_name, u.role AS user_role,
           loc.name AS location_name, e.employee_id AS emp_code
    FROM attendance_logs al
    INNER JOIN users u ON al.user_id = u.id
    INNER JOIN employees e ON e.email = u.email
    LEFT JOIN attendance_locations loc ON al.location_id = loc.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY al.log_date DESC, u.name ASC
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    $_SESSION['flash_error'] = "No attendance records to export.";
    header("Location: attendance.php"); exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$fname = 'Attendance_' . $filterFrom . '_to_' . $filterTo . '.xls';
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
  <Style ss:ID="hdr"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="10"/><Interior ss:Color="#4F46E5" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="d"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="dAlt"><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
</Styles>
<Worksheet ss:Name="Attendance">
<Table>
<Column ss:Width="40"/><Column ss:Width="150"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="100"/><Column ss:Width="90"/><Column ss:Width="90"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="120"/>

<Row><Cell ss:StyleID="title"><Data ss:Type="String">ATTENDANCE LOG — <?= e($filterFrom) ?> to <?= e($filterTo) ?></Data></Cell></Row>
<Row></Row>
<Row>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">#</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Employee</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Emp ID</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Role</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Date</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Clock In</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Clock Out</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Work Hours</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Status</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Location</Data></Cell>
</Row>
<?php foreach($logs as $idx => $l):
  $rs = ($idx%2===0)?'d':'dAlt';
  $workHrs = $l['work_seconds'] ? sprintf('%dh %02dm', floor($l['work_seconds']/3600), floor(($l['work_seconds']%3600)/60)) : '';
?>
<Row>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="Number"><?=$idx+1?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($l['user_name'])?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($l['emp_code']??'')?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=ucfirst($l['user_role'])?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$l['log_date']?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$l['clock_in']?date('h:i A',strtotime($l['clock_in'])):''?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$l['clock_out']?date('h:i A',strtotime($l['clock_out'])):''?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$workHrs?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=ucfirst(str_replace('_',' ',$l['status']))?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($l['location_name']??'')?></Data></Cell>
</Row>
<?php endforeach;?>
<Row></Row>
<Row><Cell><Data ss:Type="String">Total Records: <?=count($logs)?></Data></Cell></Row>
</Table>
</Worksheet>
</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: ' . strlen($xml));
echo $xml;
exit;
