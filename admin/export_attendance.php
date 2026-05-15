<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$filterFrom = $_GET['from'] ?? date('Y-m-01');
$filterTo   = $_GET['to'] ?? date('Y-m-t');
$filterUser = (int)($_GET['user_id'] ?? 0);
$filterRole = $_GET['role'] ?? '';

$month = (int)date('n', strtotime($filterFrom));
$year  = (int)date('Y', strtotime($filterFrom));
$daysInMonth = (int)((strtotime($filterTo) - strtotime($filterFrom)) / 86400) + 1;

// Get employees
$empWhere = ["u.role IN ('employee','manager')","u.status='active'"];
$empParams = [];
if ($filterUser) { $empWhere[] = "u.id=?"; $empParams[] = $filterUser; }
if ($filterRole) { $empWhere[] = "u.role=?"; $empParams[] = $filterRole; }
$employees = $db->prepare("SELECT u.id AS user_id,u.name,u.role,e.employee_id,e.employee_type,d.name AS dept_name FROM users u JOIN employees e ON e.email=u.email LEFT JOIN departments d ON e.department_id=d.id WHERE ".implode(' AND ',$empWhere)." ORDER BY u.name");
$employees->execute($empParams);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);
if (empty($employees)) { $_SESSION['flash_error']="No employees."; header("Location:attendance.php"); exit; }

$empCount = count(array_filter($employees, fn($e)=>$e['role']==='employee'));
$mgrCount = count(array_filter($employees, fn($e)=>$e['role']==='manager'));

// Holidays
$holidays = [];
try { $h=$db->prepare("SELECT holiday_date,title FROM holidays WHERE holiday_date BETWEEN ? AND ?"); $h->execute([$filterFrom,$filterTo]); foreach($h->fetchAll() as $r) $holidays[$r['holiday_date']]=$r['title']; } catch(Exception $e){}

// Attendance logs
$allLogs = [];
$l=$db->prepare("SELECT user_id,log_date,clock_in,clock_out,work_seconds,status FROM attendance_logs WHERE log_date BETWEEN ? AND ?");
$l->execute([$filterFrom,$filterTo]); foreach($l->fetchAll() as $r) $allLogs[$r['user_id']][$r['log_date']]=$r;

// Leaves
$allLeaves = [];
try { $lv=$db->prepare("SELECT la.user_id,la.from_date,la.to_date,lt.name AS leave_type FROM leave_applications la LEFT JOIN leave_types lt ON la.leave_type_id=lt.id WHERE la.status='approved' AND la.from_date<=? AND la.to_date>=?"); $lv->execute([$filterTo,$filterFrom]);
foreach($lv->fetchAll() as $r){ $d=new DateTime(max($filterFrom,$r['from_date']));$e=new DateTime(min($filterTo,$r['to_date'])); while($d<=$e){$allLeaves[$r['user_id']][$d->format('Y-m-d')]=$r['leave_type']??'Leave';$d->modify('+1 day');} }
} catch(Exception $e){}

function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$period = date('d M Y',strtotime($filterFrom)).' to '.date('d M Y',strtotime($filterTo))." ($daysInMonth days)";
$fname = 'Attendance_'.date('M_Y',strtotime($filterFrom)).'.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<?mso-application progid="Excel.Sheet"?>'."\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
<Style ss:ID="titleBar"><Font ss:Bold="1" ss:Size="11" ss:Color="#FFFFFF"/><Interior ss:Color="#1E40AF" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/></Style>
<Style ss:ID="info"><Font ss:Size="10"/><Interior ss:Color="#F0F4FF" ss:Pattern="Solid"/></Style>
<Style ss:ID="hdr"><Font ss:Bold="1" ss:Size="9" ss:Color="#FFFFFF"/><Interior ss:Color="#374151" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
<Style ss:ID="d"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
<Style ss:ID="dAlt"><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders></Style>
<Style ss:ID="present"><Font ss:Bold="1" ss:Color="#059669"/><Alignment ss:Horizontal="Center"/></Style>
<Style ss:ID="absent"><Font ss:Bold="1" ss:Color="#DC2626"/><Alignment ss:Horizontal="Center"/></Style>
<Style ss:ID="weekend"><Font ss:Bold="1" ss:Color="#9333EA"/><Alignment ss:Horizontal="Center"/></Style>
<Style ss:ID="holiday"><Font ss:Bold="1" ss:Color="#D97706"/><Alignment ss:Horizontal="Center"/></Style>
<Style ss:ID="onleave"><Font ss:Bold="1" ss:Color="#2563EB"/><Alignment ss:Horizontal="Center"/></Style>
<Style ss:ID="sumBar"><Font ss:Bold="1" ss:Size="9" ss:Color="#FFFFFF"/><Interior ss:Color="#059669" ss:Pattern="Solid"/></Style>
</Styles>
<Worksheet ss:Name="Attendance Report">
<Table>
<Column ss:Width="130"/><Column ss:Width="70"/><Column ss:Width="80"/><Column ss:Width="130"/><Column ss:Width="75"/><Column ss:Width="75"/><Column ss:Width="75"/><Column ss:Width="75"/><Column ss:Width="75"/><Column ss:Width="140"/>

<!-- Title -->
<Row ss:Height="22"><Cell ss:StyleID="titleBar" ss:MergeAcross="9"><Data ss:Type="String">All Attendance Report (Employees &amp; Managers)</Data></Cell></Row>
<Row><Cell ss:StyleID="info"><Data ss:Type="String">Period</Data></Cell><Cell ss:StyleID="info" ss:MergeAcross="4"><Data ss:Type="String"><?=e($period)?></Data></Cell></Row>
<Row><Cell ss:StyleID="info"><Data ss:Type="String">Total People</Data></Cell><Cell ss:StyleID="info" ss:MergeAcross="4"><Data ss:Type="String"><?=count($employees)?> (<?=$empCount?> Employees + <?=$mgrCount?> Managers)</Data></Cell></Row>

<!-- Headers -->
<Row ss:Height="20">
<Cell ss:StyleID="hdr"><Data ss:Type="String">Name</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Type</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Employee ID</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Department</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Date</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Day</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Clock In</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Clock Out</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Work Hours</Data></Cell>
<Cell ss:StyleID="hdr"><Data ss:Type="String">Status</Data></Cell>
</Row>

<?php
$rowIdx=0;
foreach($employees as $emp):
  $uid=$emp['user_id'];
  $presentDays=0;$absentDays=0;$leaveDays=0;$holidayDays=0;$weekendDays=0;$totalWorkSec=0;

  $curDate=new DateTime($filterFrom);
  $endDate=new DateTime($filterTo);
  while($curDate<=$endDate):
    $dateStr=$curDate->format('Y-m-d');
    $dow=(int)$curDate->format('N');
    $dayName=$curDate->format('l');
    $dateDisp=$curDate->format('j-M-y');
    $isSun=($dow===7);$isSat=($dow===6);
    $isHol=isset($holidays[$dateStr]);
    $isLv=isset($allLeaves[$uid][$dateStr]);
    $log=$allLogs[$uid][$dateStr]??null;

    $clockIn=$log&&$log['clock_in']?date('h:i A',strtotime($log['clock_in'])):'-';
    $clockOut=$log&&$log['clock_out']?date('h:i A',strtotime($log['clock_out'])):'-';
    $workH=$log&&$log['work_seconds']?sprintf('%d:%02d',floor($log['work_seconds']/3600),floor(($log['work_seconds']%3600)/60)):'-';

    $status='';$stStyle='absent';
    if($isSun||$isSat){
      $status='ABSENT (Weekend)';$stStyle='weekend';$weekendDays++;
      if($log&&$log['clock_in']){$status='PRESENT (Weekend)';$stStyle='present';$presentDays++;$totalWorkSec+=(int)($log['work_seconds']??0);$weekendDays--;}
    }elseif($isHol){
      $status='HOLIDAY ('.($holidays[$dateStr]).')';$stStyle='holiday';$holidayDays++;
    }elseif($isLv){
      $status='ON LEAVE ('.$allLeaves[$uid][$dateStr].')';$stStyle='onleave';$leaveDays++;
    }elseif($log&&$log['clock_in']){
      $status='PRESENT';
      if($log['status']==='late')$status='PRESENT (Late)';
      if($log['status']==='remote')$status='PRESENT (Remote)';
      $stStyle='present';$presentDays++;$totalWorkSec+=(int)($log['work_seconds']??0);
    }else{
      if($dateStr<=date('Y-m-d')){$status='ABSENT (Absent)';$stStyle='absent';$absentDays++;}
    }

    $rs=($rowIdx%2===0)?'d':'dAlt';$rowIdx++;
?>
<Row>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($emp['name'])?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=ucfirst($emp['role'])?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($emp['employee_id'])?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=e($emp['dept_name']??'')?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$dateDisp?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$dayName?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$clockIn?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$clockOut?></Data></Cell>
<Cell ss:StyleID="<?=$rs?>"><Data ss:Type="String"><?=$workH?></Data></Cell>
<Cell ss:StyleID="<?=$stStyle?>"><Data ss:Type="String"><?=e($status)?></Data></Cell>
</Row>
<?php $curDate->modify('+1 day'); endwhile;

  // Summary row for this employee
  $totalDaysWorked=$presentDays;
  $rate=$daysInMonth>0?round(($presentDays/($daysInMonth-$weekendDays-$holidayDays))*100):0;
?>
<Row>
<Cell ss:StyleID="sumBar"><Data ss:Type="String">SUMMARY: <?=e($emp['name'])?></Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String"></Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String"></Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String"></Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String">Present: <?=$presentDays?> days</Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String"></Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String">Absent: <?=$absentDays?> days</Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String"></Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String"></Data></Cell>
<Cell ss:StyleID="sumBar"><Data ss:Type="String">Rate: <?=$rate?>%</Data></Cell>
</Row>
<Row></Row>
<?php endforeach;?>

</Table>
</Worksheet>
</Workbook>
<?php
$xml=ob_get_clean();
header('Content-Length: '.strlen($xml));
echo $xml; exit;
