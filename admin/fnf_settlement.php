<?php
ini_set('display_errors',1);error_reporting(E_ALL);
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../auth/db.php';
guardRole('admin');
$db=getDB();

try{$db->exec("CREATE TABLE IF NOT EXISTS fnf_settlements(id INT UNSIGNED NOT NULL AUTO_INCREMENT,user_id INT UNSIGNED NOT NULL,last_working_day DATE NOT NULL,days_worked INT NOT NULL DEFAULT 0,working_days_month INT NOT NULL DEFAULT 0,pl_days DECIMAL(5,1) NOT NULL DEFAULT 0,pl_encashment DECIMAL(12,2) NOT NULL DEFAULT 0,bonus DECIMAL(12,2) NOT NULL DEFAULT 0,notice_period INT NOT NULL DEFAULT 90,notice_served INT NOT NULL DEFAULT 0,notice_shortfall_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,outstanding_recovery DECIMAL(12,2) NOT NULL DEFAULT 0,custom_items JSON NULL,total_earnings DECIMAL(12,2) NOT NULL DEFAULT 0,total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,net_settlement DECIMAL(12,2) NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),INDEX(user_id))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Exception $e){}

$successMsg=$_SESSION['flash_success']??'';$errorMsg=$_SESSION['flash_error']??'';
unset($_SESSION['flash_success'],$_SESSION['flash_error']);

// Get inactive employees (with date_of_exit)
$employees=$db->query("SELECT e.id,e.first_name,e.last_name,e.email,e.employee_id,e.date_of_exit,u.id AS user_id,u.name FROM employees e JOIN users u ON e.email=u.email WHERE e.date_of_exit IS NOT NULL ORDER BY e.date_of_exit DESC")->fetchAll();

$selUserId=(int)($_GET['user_id']??$_POST['user_id']??0);
$empData=null;$salaryData=null;$calcData=[];

if($selUserId){
  $empData=$db->prepare("SELECT e.*,u.name AS full_name,u.id AS uid,d.name AS dept_name FROM employees e JOIN users u ON e.email=u.email LEFT JOIN departments d ON e.department_id=d.id WHERE u.id=?");
  $empData->execute([$selUserId]);$empData=$empData->fetch();

  if($empData){
    $salaryData=$db->prepare("SELECT * FROM salary_structures WHERE user_id=?");
    $salaryData->execute([$selUserId]);$salaryData=$salaryData->fetch();

    $lwd=$empData['date_of_exit'];
    $lwdMonth=(int)date('n',strtotime($lwd));
    $lwdYear=(int)date('Y',strtotime($lwd));
    $daysInMonth=cal_days_in_month(CAL_GREGORIAN,$lwdMonth,$lwdYear);

    // Days worked in last month from attendance
    $att=$db->prepare("SELECT COUNT(*) FROM attendance_logs WHERE user_id=? AND DATE_FORMAT(log_date,'%Y-%m')=? AND status IN('present','remote','late')");
    $att->execute([$selUserId,date('Y-m',strtotime($lwd))]);
    $daysWorked=(int)$att->fetchColumn();

    // Working days in that month (exclude Sundays)
    $workDays=0;
    for($d=1;$d<=$daysInMonth;$d++){if(date('N',mktime(0,0,0,$lwdMonth,$d,$lwdYear))<7)$workDays++;}

    // PL balance (Privilege Leave)
    $plBal=0;
    try{
      $pl=$db->prepare("SELECT COALESCE(SUM(lb.balance),0) FROM leave_balances lb JOIN leave_types lt ON lb.leave_type_id=lt.id WHERE lb.user_id=? AND lt.name LIKE '%Privilege%'");
      $pl->execute([$selUserId]);$plBal=(float)$pl->fetchColumn();
    }catch(Exception $e){}

    $basicSalary=$salaryData?(float)$salaryData['basic_salary']:0;
    $plEncashment=($plBal>=60)?round(($basicSalary/30)*$plBal,2):0;

    $calcData=['lwd'=>$lwd,'days_worked'=>$daysWorked,'working_days'=>$workDays,'pl_days'=>$plBal,'pl_encashment'=>$plEncashment,'basic'=>$basicSalary];
  }
}

// POST: Save settlement
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save_fnf'){
  $uid=(int)$_POST['user_id'];
  $bonus=(float)($_POST['bonus']??0);
  $noticePeriod=(int)($_POST['notice_period']??90);
  $noticeServed=(int)($_POST['notice_served']??0);
  $recovery=(float)($_POST['outstanding_recovery']??0);
  $plEnc=(float)($_POST['pl_encashment']??0);
  $daysW=(int)($_POST['days_worked']??0);
  $workD=(int)($_POST['working_days']??0);
  $plD=(float)($_POST['pl_days']??0);
  $lwd=$_POST['last_working_day']??'';

  // Notice shortfall
  $shortfall=max(0,$noticePeriod-$noticeServed);
  $basicM=$salaryData?(float)$salaryData['basic_salary']:0;
  $noticeDeduction=round(($basicM/30)*$shortfall,2);

  // Custom items
  $customItems=[];$customEarn=0;$customDed=0;
  if(!empty($_POST['custom_name'])){
    foreach($_POST['custom_name'] as $i=>$name){
      $name=trim($name);$type=$_POST['custom_type'][$i]??'addition';$amt=(float)($_POST['custom_amount'][$i]??0);
      if($name&&$amt>0){$customItems[]=['name'=>$name,'type'=>$type,'amount'=>$amt];
        if($type==='addition')$customEarn+=$amt;else $customDed+=$amt;}
    }
  }

  // Pro-rata salary for days worked
  $proRata=$basicM>0?round(($basicM*2/$workD)*$daysW,2):0; // basic*2 approx gross monthly

  $totalEarnings=$proRata+$plEnc+$bonus+$customEarn;
  $totalDeductions=$noticeDeduction+$recovery+$customDed;
  $netSettlement=$totalEarnings-$totalDeductions;

  $db->prepare("INSERT INTO fnf_settlements(user_id,last_working_day,days_worked,working_days_month,pl_days,pl_encashment,bonus,notice_period,notice_served,notice_shortfall_deduction,outstanding_recovery,custom_items,total_earnings,total_deductions,net_settlement)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$uid,$lwd,$daysW,$workD,$plD,$plEnc,$bonus,$noticePeriod,$noticeServed,$noticeDeduction,$recovery,json_encode($customItems),$totalEarnings,$totalDeductions,$netSettlement]);
  $_SESSION['flash_success']="F&F Settlement saved. Net: ₹".number_format($netSettlement,0);
  header("Location:fnf_settlement.php");exit;
}

// Existing settlements
$settlements=$db->query("SELECT f.*,u.name AS emp_name,e.employee_id AS emp_code FROM fnf_settlements f JOIN users u ON f.user_id=u.id JOIN employees e ON e.email=u.email ORDER BY f.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>F&F Settlement – HRMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__.'/sidebar.php';?>
<div class="main-content">
<header class="topbar"><div class="topbar-left"><span class="page-title">F&F Settlement</span><span class="page-breadcrumb">Full & Final Settlement</span></div>
<div class="topbar-right"><span class="role-chip">Admin</span><div class="topbar-avatar"><?=strtoupper(substr($_SESSION['user_name'],0,1))?></div><span class="topbar-name"><?=htmlspecialchars($_SESSION['user_name'])?></span></div></header>
<div class="page-body">
<?php if($successMsg):?><div class="alert alert-success"><?=htmlspecialchars($successMsg)?></div><?php endif;?>
<?php if($errorMsg):?><div class="alert alert-error"><?=htmlspecialchars($errorMsg)?></div><?php endif;?>

<!-- Select Employee -->
<div class="card" style="margin-bottom:20px">
<div class="card-header"><h2>Create F&F Settlement</h2></div>
<div class="card-body">
<form method="GET" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;">
  <div class="form-group" style="min-width:300px;"><label>Select Employee (Exited)</label>
  <select name="user_id" class="form-control" onchange="this.form.submit()"><option value="">— Select —</option>
  <?php foreach($employees as $emp):?><option value="<?=$emp['user_id']?>" <?=$selUserId==$emp['user_id']?'selected':''?>><?=htmlspecialchars($emp['name'])?> (<?=$emp['employee_id']?>) — Exit: <?=$emp['date_of_exit']?></option><?php endforeach;?></select></div>
</form>

<?php if($empData&&$salaryData):?>
<form method="POST">
<input type="hidden" name="action" value="save_fnf">
<input type="hidden" name="user_id" value="<?=$selUserId?>">
<input type="hidden" name="last_working_day" value="<?=$calcData['lwd']?>">
<input type="hidden" name="days_worked" value="<?=$calcData['days_worked']?>">
<input type="hidden" name="working_days" value="<?=$calcData['working_days']?>">
<input type="hidden" name="pl_days" value="<?=$calcData['pl_days']?>">
<input type="hidden" name="pl_encashment" value="<?=$calcData['pl_encashment']?>">

<!-- Employee Info -->
<div style="background:var(--surface-2);border-radius:8px;padding:14px 16px;margin-bottom:16px;">
  <div style="font-size:15px;font-weight:700;color:var(--text);"><?=htmlspecialchars($empData['full_name'])?></div>
  <div style="font-size:12.5px;color:var(--muted);margin-top:4px;"><?=htmlspecialchars($empData['employee_id']??'')?> · <?=htmlspecialchars($empData['job_title']??'')?> · <?=htmlspecialchars($empData['dept_name']??'')?></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
  <!-- Left: Auto-calculated -->
  <div>
    <div style="font-size:13px;font-weight:700;margin-bottom:10px;color:var(--text);">Auto-Calculated</div>
    <table style="width:100%;font-size:13px;">
      <tr><td style="padding:7px 0;color:var(--muted);">Last Working Day</td><td style="text-align:right;font-weight:700;"><?=date('d M Y',strtotime($calcData['lwd']))?></td></tr>
      <tr><td style="padding:7px 0;color:var(--muted);">Days Worked (Last Month)</td><td style="text-align:right;font-weight:700;"><?=$calcData['days_worked']?></td></tr>
      <tr><td style="padding:7px 0;color:var(--muted);">Working Days (Month)</td><td style="text-align:right;font-weight:700;"><?=$calcData['working_days']?></td></tr>
      <tr><td style="padding:7px 0;color:var(--muted);">Basic Salary (Monthly)</td><td style="text-align:right;font-weight:700;color:var(--brand);">₹<?=number_format($calcData['basic'],0)?></td></tr>
      <tr><td style="padding:7px 0;color:var(--muted);">Unused PL Days</td><td style="text-align:right;font-weight:700;"><?=$calcData['pl_days']?> <?=$calcData['pl_days']>=60?'✓':'(< 60, no encashment)'?></td></tr>
      <tr><td style="padding:7px 0;color:var(--muted);">PL Encashment (Basic/30 × PL)</td><td style="text-align:right;font-weight:700;color:var(--green-text);">₹<?=number_format($calcData['pl_encashment'],0)?></td></tr>
    </table>
  </div>
  <!-- Right: Editable -->
  <div>
    <div style="font-size:13px;font-weight:700;margin-bottom:10px;color:var(--text);">Manual Entries</div>
    <div class="form-group" style="margin-bottom:10px;"><label>Bonus (₹)</label><input type="number" name="bonus" class="form-control" value="0" min="0" step="1"></div>
    <div class="form-group" style="margin-bottom:10px;"><label>Notice Period (days)</label><input type="number" name="notice_period" class="form-control" value="90" min="0"></div>
    <div class="form-group" style="margin-bottom:10px;"><label>Notice Served (days)</label><input type="number" name="notice_served" class="form-control" value="0" min="0"></div>
    <div class="form-group" style="margin-bottom:10px;"><label>Outstanding Recovery / Loans (₹)</label><input type="number" name="outstanding_recovery" class="form-control" value="0" min="0" step="1"></div>
  </div>
</div>

<!-- Custom Items -->
<div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <label style="font-weight:700;font-size:13px;">Custom Items (Addition/Deduction)</label>
    <button type="button" class="btn btn-ghost btn-sm" onclick="addCustomFnF()">+ Add</button>
  </div>
  <div id="customFnFList"></div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
  <button type="submit" class="btn btn-primary">Calculate & Save Settlement</button>
</div>
</form>
<?php elseif($selUserId&&!$salaryData):?>
<div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">No salary structure found for this employee. Add salary structure first.</div>
<?php endif;?>
</div></div>

<!-- Existing Settlements -->
<?php if(!empty($settlements)):?>
<div class="table-wrap">
<div class="table-toolbar"><h2>Settlements (<?=count($settlements)?>)</h2></div>
<table><thead><tr><th>Employee</th><th>LWD</th><th style="text-align:right">Earnings</th><th style="text-align:right">Deductions</th><th style="text-align:right">Net Settlement</th></tr></thead><tbody>
<?php foreach($settlements as $s):?>
<tr><td class="font-semibold"><?=htmlspecialchars($s['emp_name'])?> <span class="text-sm text-muted">(<?=$s['emp_code']?>)</span></td>
<td class="text-sm"><?=date('d M Y',strtotime($s['last_working_day']))?></td>
<td style="text-align:right;font-weight:700;color:var(--green-text)">₹<?=number_format($s['total_earnings'],0)?></td>
<td style="text-align:right;font-weight:700;color:var(--red)">₹<?=number_format($s['total_deductions'],0)?></td>
<td style="text-align:right;font-weight:800;color:var(--brand)">₹<?=number_format($s['net_settlement'],0)?></td></tr>
<?php endforeach;?></tbody></table></div>
<?php endif;?>

</div></div></div>
<script>
function addCustomFnF(){
  const list=document.getElementById('customFnFList');
  list.innerHTML+=`<div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
    <input type="text" name="custom_name[]" class="form-control" placeholder="Item name" style="flex:1;font-size:12.5px;">
    <select name="custom_type[]" class="form-control" style="width:auto;font-size:12.5px;"><option value="addition">Addition</option><option value="deduction">Deduction</option></select>
    <input type="number" name="custom_amount[]" class="form-control" placeholder="₹" min="0" style="width:100px;font-size:12.5px;">
    <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;padding:4px 8px;" onclick="this.parentElement.remove()">✕</button>
  </div>`;
}
</script>
</body></html>
