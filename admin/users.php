<?php
ini_set('display_errors',1);error_reporting(E_ALL);
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../auth/db.php';
guardRole('admin');
$db=getDB();

$successMsg=$_SESSION['flash_success']??'';$errorMsg=$_SESSION['flash_error']??'';
unset($_SESSION['flash_success'],$_SESSION['flash_error']);

// POST: Add user
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='add_user'){
  $name=trim($_POST['name']??'');
  $email=strtolower(trim($_POST['email']??''));
  $role=$_POST['role']??'employee';
  if(!$name||!$email){$errorMsg='Name and email required.';}
  elseif(!in_array($role,['admin','manager','employee'])){$errorMsg='Invalid role.';}
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){$errorMsg='Invalid email.';}
  else{
    // Check duplicate
    $chk=$db->prepare("SELECT id FROM users WHERE email=?");$chk->execute([$email]);
    if($chk->fetch()){$errorMsg="User with email $email already exists.";}
    else{
      // Generate password
      $pass=substr(str_shuffle('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'),0,10);
      $hash=password_hash($pass,PASSWORD_BCRYPT);
      $db->prepare("INSERT INTO users(name,email,password,role,status)VALUES(?,?,?,?,?)")->execute([$name,$email,$hash,$role,'active']);
      $newUserId=$db->lastInsertId();

      // Create employee record with auto-generated employee_id
      $nameParts=explode(' ',$name,2);
      $firstName=$nameParts[0];$lastName=$nameParts[1]??'';
      
      // Generate employee_id from user_code pattern: EMP001, EMP002, etc.
      $lastCode=$db->query("SELECT employee_id FROM employees WHERE employee_id REGEXP '^EMP[0-9]+$' ORDER BY CAST(SUBSTRING(employee_id,4) AS UNSIGNED) DESC LIMIT 1")->fetchColumn();
      $nextNum=$lastCode?(int)substr($lastCode,3)+1:1;
      $empId='EMP'.str_pad($nextNum,3,'0',STR_PAD_LEFT);
      
      $db->prepare("INSERT IGNORE INTO employees(first_name,last_name,email,employee_id)VALUES(?,?,?,?)")->execute([$firstName,$lastName,$email,$empId]);

      // Send email
      try{
        require_once __DIR__.'/../auth/mailer.php';
        sendWelcomeEmail($email,$name,$pass);
        $_SESSION['flash_success']="User created. Password sent to $email.";
      }catch(Exception $e){
        $_SESSION['flash_success']="User created. Password: $pass (email failed — share manually).";
      }
      header("Location:users.php");exit;
    }
  }
}

// POST: Delete user
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='delete_user'){
  $uid=(int)$_POST['user_id'];
  if($uid!=$_SESSION['user_id']){
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
    $_SESSION['flash_success']="User deleted.";
  }else{$_SESSION['flash_error']="Cannot delete yourself.";}
  header("Location:users.php");exit;
}

// POST: Reset password
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='reset_pass'){
  $uid=(int)$_POST['user_id'];
  $pass=substr(str_shuffle('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'),0,10);
  $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pass,PASSWORD_BCRYPT),$uid]);
  $u=$db->prepare("SELECT email,name FROM users WHERE id=?");$u->execute([$uid]);$u=$u->fetch();
  try{require_once __DIR__.'/../auth/mailer.php';sendWelcomeEmail($u['email'],$u['name'],$pass);
    $_SESSION['flash_success']="Password reset. New password sent to ".$u['email'].".";
  }catch(Exception $e){$_SESSION['flash_success']="Password reset: $pass (email failed)";}
  header("Location:users.php");exit;
}

$search=trim($_GET['q']??'');
$filterRole=$_GET['role']??'';
$w=["1=1"];$p=[];
if($search){$w[]="(u.name LIKE ? OR u.email LIKE ?)";$p[]="%$search%";$p[]="%$search%";}
if($filterRole){$w[]="u.role=?";$p[]=$filterRole;}
$users=$db->prepare("SELECT u.* FROM users u WHERE ".implode(' AND ',$w)." ORDER BY u.name");
$users->execute($p);$users=$users->fetchAll();
$totalUsers=count($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__.'/sidebar.php';?>
<div class="main-content">
<header class="topbar"><div class="topbar-left"><span class="page-title">System Users</span><span class="page-breadcrumb">Manage login accounts</span></div>
<div class="topbar-right"><span class="role-chip">Admin</span><div class="topbar-avatar"><?=strtoupper(substr($_SESSION['user_name'],0,1))?></div><span class="topbar-name"><?=htmlspecialchars($_SESSION['user_name'])?></span></div></header>
<div class="page-body">
<?php if($successMsg):?><div class="alert alert-success"><?=htmlspecialchars($successMsg)?></div><?php endif;?>
<?php if($errorMsg):?><div class="alert alert-error"><?=htmlspecialchars($errorMsg)?></div><?php endif;?>

<!-- Add User -->
<div class="card" style="margin-bottom:20px"><div class="card-header"><h2>Add New User</h2></div><div class="card-body">
<form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
<input type="hidden" name="action" value="add_user">
<div class="form-group" style="min-width:180px"><label>Full Name *</label><input type="text" name="name" class="form-control" required placeholder="John Doe"></div>
<div class="form-group" style="min-width:220px"><label>Work Email *</label><input type="email" name="email" class="form-control" required placeholder="john@company.com"></div>
<div class="form-group" style="min-width:130px"><label>Role *</label><select name="role" class="form-control" required><option value="employee">Employee</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
<button type="submit" class="btn btn-primary btn-sm" style="height:38px">Create & Send Password</button>
</form>
<div style="font-size:11.5px;color:var(--muted);margin-top:8px;">A random password will be generated and emailed to the user. They can then login and fill their profile.</div>
</div></div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
<div class="search-box" style="min-width:200px;flex:0 1 280px;"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="q" value="<?=htmlspecialchars($search)?>" placeholder="Search name or email…" onchange="this.form.submit()"></div>
<select name="role" class="form-control" style="font-size:13px;padding:9px 12px;width:auto;" onchange="this.form.submit()"><option value="">All Roles</option><option value="admin" <?=$filterRole==='admin'?'selected':''?>>Admin</option><option value="manager" <?=$filterRole==='manager'?'selected':''?>>Manager</option><option value="employee" <?=$filterRole==='employee'?'selected':''?>>Employee</option></select>
<span style="font-size:13px;color:var(--muted);"><?=$totalUsers?> user(s)</span>
</form>

<!-- Users Table -->
<div class="table-wrap"><div class="table-toolbar"><h2>Users (<?=$totalUsers?>)</h2></div>
<table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th style="width:160px"></th></tr></thead><tbody>
<?php if(empty($users)):?><tr class="empty-row"><td colspan="6">No users found.</td></tr>
<?php else:foreach($users as $u):?>
<tr>
<td class="font-semibold"><?=htmlspecialchars($u['name'])?></td>
<td class="text-sm text-muted"><?=htmlspecialchars($u['email'])?></td>
<td><span class="badge <?=$u['role']==='admin'?'badge-red':($u['role']==='manager'?'badge-blue':'badge-green')?>"><?=ucfirst($u['role'])?></span></td>
<td><span class="badge <?=$u['status']==='active'?'badge-green':'badge-gray'?>"><?=ucfirst($u['status'])?></span></td>
<td class="text-sm text-muted"><?=date('d M Y',strtotime($u['created_at']))?></td>
<td><div style="display:flex;gap:6px;">
<form method="POST" style="display:inline" onsubmit="return confirm('Reset password?')"><input type="hidden" name="action" value="reset_pass"><input type="hidden" name="user_id" value="<?=$u['id']?>"><button type="submit" class="btn btn-ghost btn-sm" style="font-size:11px;">Reset Pass</button></form>
<?php if($u['id']!=$_SESSION['user_id']):?>
<form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=$u['id']?>"><button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;font-size:11px;">Del</button></form>
<?php endif;?>
</div></td></tr>
<?php endforeach;endif;?></tbody></table></div>
</div></div></div></body></html>
