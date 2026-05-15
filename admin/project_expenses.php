<?php
ini_set('display_errors',1);error_reporting(E_ALL);
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../auth/db.php';
guardRole('admin');
$db=getDB();
try{$db->exec("CREATE TABLE IF NOT EXISTS project_expenses(id INT UNSIGNED NOT NULL AUTO_INCREMENT,project_id INT UNSIGNED NOT NULL,category ENUM('Travel','Food','Hotel','Other') NOT NULL DEFAULT 'Other',amount DECIMAL(12,2) NOT NULL DEFAULT 0,expense_date DATE NOT NULL,description VARCHAR(500) NULL,added_by INT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),INDEX(project_id),INDEX(expense_date))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Exception $e){}
$successMsg=$_SESSION['flash_success']??'';$errorMsg=$_SESSION['flash_error']??'';
unset($_SESSION['flash_success'],$_SESSION['flash_error']);
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='add_expense'){
  $projId=(int)($_POST['project_id']??0);$cat=$_POST['category']??'Other';
  $amount=(float)($_POST['amount']??0);$date=trim($_POST['expense_date']??'');
  $desc=trim($_POST['description']??'');
  if(!$projId||!$amount||!$date){$errorMsg='Project, amount, date required.';}
  elseif(!in_array($cat,['Travel','Food','Hotel','Other'])){$errorMsg='Invalid category.';}
  else{$db->prepare("INSERT INTO project_expenses(project_id,category,amount,expense_date,description,added_by)VALUES(?,?,?,?,?,?)")->execute([$projId,$cat,$amount,$date,$desc?:null,$_SESSION['user_id']]);
  $_SESSION['flash_success']='Expense added.';header("Location:project_expenses.php?project_id=$projId");exit;}
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='delete_expense'){
  $db->prepare("DELETE FROM project_expenses WHERE id=?")->execute([(int)$_POST['expense_id']]);
  $_SESSION['flash_success']='Deleted.';header("Location:project_expenses.php");exit;
}
$fp=(int)($_GET['project_id']??0);$fc=$_GET['category']??'';
$ff=trim($_GET['date_from']??'');$ft=trim($_GET['date_to']??'');
$projects=$db->query("SELECT id,project_name,project_code FROM projects ORDER BY project_name")->fetchAll();
$w=["1=1"];$pa=[];
if($fp){$w[]="pe.project_id=?";$pa[]=$fp;}
if($fc){$w[]="pe.category=?";$pa[]=$fc;}
if($ff){$w[]="pe.expense_date>=?";$pa[]=$ff;}
if($ft){$w[]="pe.expense_date<=?";$pa[]=$ft;}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$countSt = $db->prepare("SELECT COUNT(*) FROM project_expenses pe JOIN projects pr ON pe.project_id=pr.id WHERE ".implode(' AND ',$w));
$countSt->execute($pa); $totalRows = (int)$countSt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$st=$db->prepare("SELECT pe.*,pr.project_name,pr.project_code FROM project_expenses pe JOIN projects pr ON pe.project_id=pr.id WHERE ".implode(' AND ',$w)." ORDER BY pe.expense_date DESC LIMIT $perPage OFFSET $offset");
$st->execute($pa);$expenses=$st->fetchAll();
$totSt=$db->prepare("SELECT COALESCE(SUM(pe.amount),0) as total, pe.category FROM project_expenses pe JOIN projects pr ON pe.project_id=pr.id WHERE ".implode(' AND ',$w)." GROUP BY pe.category");
$totSt->execute($pa);$catRows=$totSt->fetchAll();
$tot=0;$ct=[];foreach($catRows as $r){$ct[$r['category']]=(float)$r['total'];$tot+=(float)$r['total'];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Expenses - HRMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__.'/sidebar.php';?>
<div class="main-content">
<header class="topbar"><div class="topbar-left"><span class="page-title">Project Expenses</span></div>
<div class="topbar-right"><span class="role-chip">Admin</span><div class="topbar-avatar"><?=strtoupper(substr($_SESSION['user_name'],0,1))?></div><span class="topbar-name"><?=htmlspecialchars($_SESSION['user_name'])?></span></div></header>
<div class="page-body">
<?php if($successMsg):?><div class="alert alert-success"><?=htmlspecialchars($successMsg)?></div><?php endif;?>
<?php if($errorMsg):?><div class="alert alert-error"><?=htmlspecialchars($errorMsg)?></div><?php endif;?>

<div class="card" style="margin-bottom:20px"><div class="card-header"><h2>Add Expense</h2></div><div class="card-body">
<form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
<input type="hidden" name="action" value="add_expense">
<div class="form-group" style="min-width:180px"><label>Project *</label><select name="project_id" class="form-control" required><option value="">Select</option><?php foreach($projects as $pr):?><option value="<?=$pr['id']?>" <?=$fp==$pr['id']?'selected':''?>><?=htmlspecialchars($pr['project_name'])?></option><?php endforeach;?></select></div>
<div class="form-group" style="min-width:120px"><label>Category *</label><select name="category" class="form-control" required><option>Travel</option><option>Food</option><option>Hotel</option><option>Other</option></select></div>
<div class="form-group" style="width:110px"><label>Amount *</label><input type="number" name="amount" class="form-control" min="1" step="0.01" required></div>
<div class="form-group" style="width:140px"><label>Date *</label><input type="date" name="expense_date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
<div class="form-group" style="min-width:180px;flex:1"><label>Description</label><input type="text" name="description" class="form-control" placeholder="Brief..."></div>
<button type="submit" class="btn btn-primary btn-sm" style="height:38px">+ Add</button></form></div></div>

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:16px">
<div class="stat-card" style="border-color:var(--brand);background:var(--brand-light)"><div class="stat-body"><div class="stat-value" style="color:var(--brand)">₹<?=number_format($tot,0)?></div><div class="stat-label">Total</div></div></div>
<div class="stat-card"><div class="stat-body"><div class="stat-value">₹<?=number_format($ct['Travel']??0,0)?></div><div class="stat-label">Travel</div></div></div>
<div class="stat-card"><div class="stat-body"><div class="stat-value">₹<?=number_format($ct['Food']??0,0)?></div><div class="stat-label">Food</div></div></div>
<div class="stat-card"><div class="stat-body"><div class="stat-value">₹<?=number_format($ct['Hotel']??0,0)?></div><div class="stat-label">Hotel</div></div></div>
<div class="stat-card"><div class="stat-body"><div class="stat-value">₹<?=number_format($ct['Other']??0,0)?></div><div class="stat-label">Other</div></div></div></div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;padding:12px 16px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius)">
<select name="project_id" class="form-control" style="font-size:12px;padding:7px 10px;width:auto;min-width:180px" onchange="this.form.submit()"><option value="">All Projects</option><?php foreach($projects as $pr):?><option value="<?=$pr['id']?>" <?=$fp==$pr['id']?'selected':''?>><?=htmlspecialchars($pr['project_name'])?></option><?php endforeach;?></select>
<select name="category" class="form-control" style="font-size:12px;padding:7px 10px;width:auto" onchange="this.form.submit()"><option value="">All</option><?php foreach(['Travel','Food','Hotel','Other'] as $c):?><option value="<?=$c?>" <?=$fc===$c?'selected':''?>><?=$c?></option><?php endforeach;?></select>
<input type="date" name="date_from" value="<?=htmlspecialchars($ff)?>" class="form-control" style="font-size:12px;padding:7px 10px;width:auto">
<span style="font-size:12px;color:var(--muted)">to</span>
<input type="date" name="date_to" value="<?=htmlspecialchars($ft)?>" class="form-control" style="font-size:12px;padding:7px 10px;width:auto">
<button type="submit" class="btn btn-primary btn-sm">Filter</button>
<a href="project_expenses.php" class="btn btn-ghost btn-sm">Reset</a>
<a href="export_expenses.php?project_id=<?=$fp?>&category=<?=urlencode($fc)?>&date_from=<?=$ff?>&date_to=<?=$ft?>" class="btn btn-sm" style="margin-left:auto;background:var(--green-bg);color:var(--green-text);border:1px solid #a7f3d0;font-weight:700">Export</a></form>

<div class="card" style="margin-bottom:16px;display:flex;flex-direction:column;height:calc(100vh - 520px);min-height:250px;overflow:hidden;"><div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--border);flex-shrink:0;"><h2 style="font-size:15px;">Expenses (<?=$totalRows?>)</h2></div>
<div style="flex:1;overflow-y:auto;">
<table><thead style="position:sticky;top:0;background:var(--surface);z-index:1;"><tr><th>Date</th><th>Project</th><th>Category</th><th>Description</th><th style="text-align:right">Amount</th><th style="width:60px"></th></tr></thead><tbody>
<?php if(empty($expenses)):?><tr class="empty-row"><td colspan="6">No expenses.</td></tr>
<?php else:foreach($expenses as $e):?>
<tr><td class="font-semibold text-sm"><?=date('d M Y',strtotime($e['expense_date']))?></td>
<td><code style="font-size:11px;background:var(--surface-2);padding:2px 6px;border-radius:4px"><?=htmlspecialchars($e['project_code'])?></code></td>
<td><span class="badge <?=$e['category']==='Travel'?'badge-blue':($e['category']==='Food'?'badge-green':($e['category']==='Hotel'?'badge-yellow':'badge-gray'))?>"><?=$e['category']?></span></td>
<td class="text-sm text-muted"><?=htmlspecialchars($e['description']?:'—')?></td>
<td style="text-align:right;font-weight:700;color:var(--brand)">₹<?=number_format($e['amount'],2)?></td>
<td><form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="expense_id" value="<?=$e['id']?>"><button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;font-size:11px">Del</button></form></td></tr>
<?php endforeach;endif;?></tbody></table></div></div>

<?php if($totalPages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:16px;align-items:center;">
  <?php if($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>" class="btn btn-ghost btn-sm">← Prev</a><?php endif; ?>
  <span style="font-size:12px;color:var(--muted);">Page <?=$page?> of <?=$totalPages?> (<?=$totalRows?> records)</span>
  <?php if($page < $totalPages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>" class="btn btn-ghost btn-sm">Next →</a><?php endif; ?>
</div>
<?php endif; ?>

</div></div></div></body></html>
