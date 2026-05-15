<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$filterFrom = $_GET['from'] ?? date('Y-m-01');
$filterTo   = $_GET['to'] ?? date('Y-m-t');
$filterUser = (int)($_GET['user_id'] ?? 0);
$filterRole = $_GET['role'] ?? '';

// Determine month/year from the from date
$month = (int)date('n', strtotime($filterFrom));
$year  = (int)date('Y', strtotime($filterFrom));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get employees
$empWhere = ["u.role IN ('employee','manager')","u.status='active'"];
$empParams = [];
if ($filterUser) { $empWhere[] = "u.id=?"; $empParams[] = $filterUser; }
if ($filterRole) { $empWhere[] = "u.role=?"; $empParams[] = $filterRole; }

$employees = $db->prepare("
    SELECT u.id AS user_id, u.name, u.role, e.employee_id, e.employee_type, d.name AS dept_name
    FROM users u
    JOIN employees e ON e.email = u.email
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE " . implode(' AND ', $empWhere) . "
    ORDER BY u.name
");
$employees->execute($empParams);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

if (empty($employees)) {
    $_SESSION['flash_error'] = "No employees found.";
    header("Location: attendance.php"); exit;
}

// Get holidays for the month
$holidays = [];
try {
    $hStmt = $db->prepare("SELECT holiday_date, title FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $hStmt->execute([$filterFrom, $filterTo]);
    foreach ($hStmt->fetchAll() as $h) $holidays[$h['holiday_date']] = $h['title'];
} catch (Exception $e) {}

// Get all attendance logs for the month
$allLogs = [];
$logStmt = $db->prepare("SELECT user_id, log_date, clock_in, clock_out, work_seconds, status FROM attendance_logs WHERE log_date BETWEEN ? AND ?");
$logStmt->execute([$filterFrom, $filterTo]);
foreach ($logStmt->fetchAll() as $l) { $allLogs[$l['user_id']][$l['log_date']] = $l; }

// Get all approved leaves for the month
$allLeaves = [];
try {
    $lvStmt = $db->prepare("SELECT la.user_id, la.from_date, la.to_date, lt.name AS leave_type FROM leave_applications la LEFT JOIN leave_types lt ON la.leave_type_id=lt.id WHERE la.status='approved' AND la.from_date<=? AND la.to_date>=?");
    $lvStmt->execute([$filterTo, $filterFrom]);
    foreach ($lvStmt->fetchAll() as $lv) {
        $d = new DateTime(max($filterFrom, $lv['from_date']));
        $e = new DateTime(min($filterTo, $lv['to_date']));
        while ($d <= $e) { $allLeaves[$lv['user_id']][$d->format('Y-m-d')] = $lv['leave_type'] ?? 'Leave'; $d->modify('+1 day'); }
    }
} catch (Exception $e) {}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$monthName = date('F Y', strtotime($filterFrom));
$fname = 'Attendance_' . date('M_Y', strtotime($filterFrom)) . '.xls';
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
  <Style ss:ID="d"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders><Alignment ss:Vertical="Center"/></Style>
  <Style ss:ID="dAlt"><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
  <Style ss:ID="present"><Font ss:Color="#059669" ss:Bold="1"/><Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="absent"><Font ss:Color="#DC2626" ss:Bold="1"/><Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="holiday"><Font ss:Color="#D97706" ss:Bold="1"/><Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="leave"><Font ss:Color="#7C3AED" ss:Bold="1"/><Interior ss:Color="#F3E8FF" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="weekend"><Font ss:Color="#6B7280"/><Interior ss:Color="#F3F4F6" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="sum"><Font ss:Bold="1" ss:Size="10"/><Interior ss:Color="#EDE9FE" ss:Pattern="Solid"/></Style>
</Styles>
<?php foreach($employees as $emp):
  $uid = $emp['user_id'];
  $presentDays=0;$absentDays=0;$leaveDays=0;$holidayDays=0;$weekendDays=0;$totalWorkSec=0;
?>
<Worksheet ss:Name="<?= e(substr($emp['name'],0,28)) ?>">
<Table>
<Column ss:Width="60"/><Column ss:Width="140"/><Column ss:Width="70"/><Column ss:Width="100"/><Column ss:Width="80"/><Column ss:Width="50"/><Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="70"/><Column ss:Width="100"/>

<Row><Cell ss:StyleID="title"><Data ss:Type="String">ATTENDANCE — <?= e($monthName) ?></Data></Cell></Row>
<Row>
  <Cell><Data ss:Type="String">Emp ID: <?= e($emp['employee_id']) ?></Data></Cell>
  <Cell><Data ss:Type="String">Name: <?= e($emp['name']) ?></Data></Cell>
  <Cell><Data ss:Type="String">Type: <?= e($emp['employee_type']) ?></Data></Cell>
  <Cell><Data ss:Type="String">Dept: <?= e($emp['dept_name']??'') ?></Data></Cell>
</Row>
<Row></Row>

<!-- Headers -->
<Row ss:Height="20">
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Emp ID</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Employee Name</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Type</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Department</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Date</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Day</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Clock In</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Clock Out</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Work Hrs</Data></Cell>
  <Cell ss:StyleID="hdr"><Data ss:Type="String">Status</Data></Cell>
</Row>

<?php for($day=1;$day<=$daysInMonth;$day++):
  $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
  $dow = (int)date('N', strtotime($dateStr));
  $dayName = date('D', strtotime($dateStr));
  $isSunday = ($dow === 7);
  $isSaturday = ($dow === 6);
  $isHoliday = isset($holidays[$dateStr]);
  $isLeave = isset($allLeaves[$uid][$dateStr]);
  $log = $allLogs[$uid][$dateStr] ?? null;

  $clockIn = $log ? ($log['clock_in'] ? date('h:i A', strtotime($log['clock_in'])) : '') : '';
  $clockOut = $log ? ($log['clock_out'] ? date('h:i A', strtotime($log['clock_out'])) : '') : '';
  $workHrs = $log && $log['work_seconds'] ? sprintf('%d:%02d', floor($log['work_seconds']/3600), floor(($log['work_seconds']%3600)/60)) : '';

  // Determine status
  $status = '';
  $statusStyle = 'd';
  if ($isSunday || $isSaturday) {
    $status = $isSunday ? 'Sunday' : 'Saturday';
    $statusStyle = 'weekend'; $weekendDays++;
  } elseif ($isHoliday) {
    $status = 'Holiday - ' . $holidays[$dateStr];
    $statusStyle = 'holiday'; $holidayDays++;
  } elseif ($isLeave) {
    $status = 'Leave - ' . $allLeaves[$uid][$dateStr];
    $statusStyle = 'leave'; $leaveDays++;
  } elseif ($log && $log['clock_in']) {
    $status = 'Present';
    if ($log['status'] === 'late') $status = 'Present (Late)';
    if ($log['status'] === 'remote') $status = 'Present (Remote)';
    $statusStyle = 'present'; $presentDays++;
    $totalWorkSec += (int)($log['work_seconds'] ?? 0);
  } else {
    // Only mark absent for past dates
    if ($dateStr <= date('Y-m-d')) { $status = 'Absent'; $statusStyle = 'absent'; $absentDays++; }
  }

  $rs = ($day % 2 === 0) ? 'dAlt' : 'd';
?>
<Row>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= e($emp['employee_id']) ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= e($emp['name']) ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= e($emp['employee_type']) ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= e($emp['dept_name']??'') ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= $dateStr ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= $dayName ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= $clockIn ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= $clockOut ?></Data></Cell>
  <Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?= $workHrs ?></Data></Cell>
  <Cell ss:StyleID="<?=$statusStyle?>"><Data ss:Type="String"><?= e($status) ?></Data></Cell>
</Row>
<?php endfor;?>

<!-- Summary -->
<Row></Row>
<Row><Cell ss:StyleID="sum"><Data ss:Type="String">SUMMARY</Data></Cell></Row>
<Row><Cell ss:StyleID="sum"><Data ss:Type="String">Present Days</Data></Cell><Cell ss:StyleID="sum"><Data ss:Type="Number"><?=$presentDays?></Data></Cell></Row>
<Row><Cell ss:StyleID="sum"><Data ss:Type="String">Absent Days</Data></Cell><Cell ss:StyleID="sum"><Data ss:Type="Number"><?=$absentDays?></Data></Cell></Row>
<Row><Cell ss:StyleID="sum"><Data ss:Type="String">Leave Days</Data></Cell><Cell ss:StyleID="sum"><Data ss:Type="Number"><?=$leaveDays?></Data></Cell></Row>
<Row><Cell ss:StyleID="sum"><Data ss:Type="String">Holidays</Data></Cell><Cell ss:StyleID="sum"><Data ss:Type="Number"><?=$holidayDays?></Data></Cell></Row>
<Row><Cell ss:StyleID="sum"><Data ss:Type="String">Weekends</Data></Cell><Cell ss:StyleID="sum"><Data ss:Type="Number"><?=$weekendDays?></Data></Cell></Row>
<Row><Cell ss:StyleID="sum"><Data ss:Type="String">Total Work Hours</Data></Cell><Cell ss:StyleID="sum"><Data ss:Type="String"><?=sprintf('%d:%02d',floor($totalWorkSec/3600),floor(($totalWorkSec%3600)/60))?></Data></Cell></Row>

</Table>
</Worksheet>
<?php endforeach;?>
</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: ' . strlen($xml));
echo $xml; exit;
