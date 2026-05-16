<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

$user = $db->prepare("SELECT email FROM users WHERE id=?");
$user->execute([$uid]); $user = $user->fetch();
$emp = null;
if ($user) {
    $stmt = $db->prepare("SELECT e.*, d.name AS department_name FROM employees e LEFT JOIN departments d ON e.department_id=d.id WHERE e.email=?");
    $stmt->execute([$user['email']]); $emp = $stmt->fetch();
}

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$isEdit = isset($_GET['edit']);

// POST: Save profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'save_profile' && $emp) {
    $fields = ['phone','date_of_birth','gender','marital_status','blood_group','nationality',
               'place_of_birth','emergency_contact_no','personal_email',
               'address_line1','address_line2','city','state','zip_code','country',
               'permanent_address_line1','permanent_address_line2','permanent_city','permanent_state','permanent_zip_code',
               'passport_no','place_of_issue','passport_date_of_issue','passport_date_of_expiry'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        $v = trim($_POST[$f] ?? '');
        $sets[] = "$f=?"; $vals[] = $v ?: null;
    }
    $vals[] = $emp['id'];
    $db->prepare("UPDATE employees SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);

    // Update name in users table
    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    if ($fn) {
        $db->prepare("UPDATE employees SET first_name=?, last_name=? WHERE id=?")->execute([$fn, $ln, $emp['id']]);
        $fullName = trim("$fn $ln");
        $db->prepare("UPDATE users SET name=? WHERE id=?")->execute([$fullName, $uid]);
        $_SESSION['user_name'] = $fullName;
    }

    $_SESSION['flash_success'] = "Profile updated.";
    header("Location: profile.php"); exit;
}

function val($emp, $key) { return htmlspecialchars($emp[$key] ?? ''); }

// Custom fields
$customCols = [];
$customMeta = []; // col => {type, options, label, required}
try {
    $allColsFull = $db->query("SHOW FULL COLUMNS FROM employees")->fetchAll(PDO::FETCH_ASSOC);
    $baseCols = ['id','first_name','last_name','email','phone','job_title','date_of_birth','gender','marital_status','employee_id','department_id','employee_type','date_of_joining','date_of_exit','date_of_confirmation','direct_manager_name','location','base_location','user_code','address_line1','address_line2','city','state','zip_code','country','permanent_address_line1','permanent_address_line2','permanent_city','permanent_state','permanent_zip_code','account_type','account_number','ifsc_code','pan','aadhar_no','uan_number','pf_account_number','employee_provident_fund','professional_tax','esi_number','exempt_from_tax','passport_no','place_of_issue','passport_date_of_issue','passport_date_of_expiry','place_of_birth','nationality','blood_group','personal_email','emergency_contact_no','country_code_phone','status','created_at','gross_salary'];
    foreach ($allColsFull as $c) {
        if (!in_array($c['Field'], $baseCols)) {
            $customCols[] = $c['Field'];
            $meta = json_decode($c['Comment'] ?? '{}', true) ?: [];
            $customMeta[$c['Field']] = $meta;
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left"><span class="page-title">My Profile</span><span class="page-breadcrumb"><?= $isEdit ? 'Edit Details' : 'View Details' ?></span></div>
    <div class="topbar-right"><span class="role-chip">Employee</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">
    <?php if($successMsg):?><div class="alert alert-success"><?=htmlspecialchars($successMsg)?></div><?php endif;?>
    <?php if($errorMsg):?><div class="alert alert-error"><?=htmlspecialchars($errorMsg)?></div><?php endif;?>

    <?php if(!$emp):?>
      <div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">Profile not set up yet. Contact HR.</div>
    <?php elseif($isEdit):?>
    <!-- EDIT MODE -->
    <form method="POST">
      <input type="hidden" name="action" value="save_profile">

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="font-size:18px;font-weight:800;">Edit My Profile</h2>
        <div style="display:flex;gap:8px;"><a href="profile.php" class="btn btn-secondary btn-sm">Cancel</a><button type="submit" class="btn btn-primary btn-sm">Save Changes</button></div>
      </div>

      <!-- Personal Information -->
      <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Personal Information</h2></div><div class="card-body">
        <div class="form-grid">
          <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?=val($emp,'first_name')?>"></div>
          <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-control" value="<?=val($emp,'last_name')?>"></div>
          <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?=val($emp,'phone')?>"></div>
          <div class="form-group"><label>Personal Email</label><input type="email" name="personal_email" class="form-control" value="<?=val($emp,'personal_email')?>"></div>
          <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?=val($emp,'date_of_birth')?>"></div>
          <div class="form-group"><label>Gender</label><select name="gender" class="form-control"><option value="">Select</option><option <?=($emp['gender']??'')==='Male'?'selected':''?>>Male</option><option <?=($emp['gender']??'')==='Female'?'selected':''?>>Female</option><option <?=($emp['gender']??'')==='Other'?'selected':''?>>Other</option></select></div>
          <div class="form-group"><label>Marital Status</label><select name="marital_status" class="form-control"><option value="">Select</option><option <?=($emp['marital_status']??'')==='Single'?'selected':''?>>Single</option><option <?=($emp['marital_status']??'')==='Married'?'selected':''?>>Married</option><option <?=($emp['marital_status']??'')==='Divorced'?'selected':''?>>Divorced</option><option <?=($emp['marital_status']??'')==='Widowed'?'selected':''?>>Widowed</option></select></div>
          <div class="form-group"><label>Blood Group</label><input type="text" name="blood_group" class="form-control" value="<?=val($emp,'blood_group')?>" placeholder="e.g. O+"></div>
          <div class="form-group"><label>Nationality</label><input type="text" name="nationality" class="form-control" value="<?=val($emp,'nationality')?>"></div>
          <div class="form-group"><label>Place of Birth</label><input type="text" name="place_of_birth" class="form-control" value="<?=val($emp,'place_of_birth')?>"></div>
          <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact_no" class="form-control" value="<?=val($emp,'emergency_contact_no')?>"></div>
        </div>
      </div></div>

      <!-- Address -->
      <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Address</h2></div><div class="card-body">
        <div style="font-size:13px;font-weight:700;margin-bottom:10px;">Current Address</div>
        <div class="form-grid">
          <div class="form-group"><label>Address Line 1</label><input type="text" name="address_line1" class="form-control" value="<?=val($emp,'address_line1')?>"></div>
          <div class="form-group"><label>Address Line 2</label><input type="text" name="address_line2" class="form-control" value="<?=val($emp,'address_line2')?>"></div>
          <div class="form-group"><label>City</label><input type="text" name="city" class="form-control" value="<?=val($emp,'city')?>"></div>
          <div class="form-group"><label>State</label><input type="text" name="state" class="form-control" value="<?=val($emp,'state')?>"></div>
          <div class="form-group"><label>Zip Code</label><input type="text" name="zip_code" class="form-control" value="<?=val($emp,'zip_code')?>"></div>
          <div class="form-group"><label>Country</label><input type="text" name="country" class="form-control" value="<?=val($emp,'country')?>"></div>
        </div>
        <div style="font-size:13px;font-weight:700;margin:16px 0 10px;">Permanent Address</div>
        <div class="form-grid">
          <div class="form-group"><label>Address Line 1</label><input type="text" name="permanent_address_line1" class="form-control" value="<?=val($emp,'permanent_address_line1')?>"></div>
          <div class="form-group"><label>Address Line 2</label><input type="text" name="permanent_address_line2" class="form-control" value="<?=val($emp,'permanent_address_line2')?>"></div>
          <div class="form-group"><label>City</label><input type="text" name="permanent_city" class="form-control" value="<?=val($emp,'permanent_city')?>"></div>
          <div class="form-group"><label>State</label><input type="text" name="permanent_state" class="form-control" value="<?=val($emp,'permanent_state')?>"></div>
          <div class="form-group"><label>Zip Code</label><input type="text" name="permanent_zip_code" class="form-control" value="<?=val($emp,'permanent_zip_code')?>"></div>
        </div>
      </div></div>

      <!-- Passport -->
      <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Passport Details</h2></div><div class="card-body">
        <div class="form-grid">
          <div class="form-group"><label>Passport No</label><input type="text" name="passport_no" class="form-control" value="<?=val($emp,'passport_no')?>"></div>
          <div class="form-group"><label>Place of Issue</label><input type="text" name="place_of_issue" class="form-control" value="<?=val($emp,'place_of_issue')?>"></div>
          <div class="form-group"><label>Date of Issue</label><input type="date" name="passport_date_of_issue" class="form-control" value="<?=val($emp,'passport_date_of_issue')?>"></div>
          <div class="form-group"><label>Date of Expiry</label><input type="date" name="passport_date_of_expiry" class="form-control" value="<?=val($emp,'passport_date_of_expiry')?>"></div>
        </div>
      </div></div>

      <!-- Custom Fields -->
      <?php if(!empty($customCols)):?>
      <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Additional Information</h2></div><div class="card-body">
        <div class="form-grid">
          <?php foreach($customCols as $col):
            $meta = $customMeta[$col] ?? [];
            $label = $meta['label'] ?? ucwords(str_replace('_',' ',$col));
            $fieldType = $meta['type'] ?? 'text';
            $options = $meta['options'] ?? [];
          ?>
          <div class="form-group">
            <label><?= htmlspecialchars($label) ?></label>
            <?php if($fieldType === 'dropdown' && !empty($options)): ?>
              <select name="custom_<?=$col?>" class="form-control">
                <option value="">Select…</option>
                <?php foreach($options as $opt): ?>
                  <option value="<?=htmlspecialchars($opt)?>" <?=($emp[$col]??'')===$opt?'selected':''?>><?=htmlspecialchars($opt)?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif($fieldType === 'yes_no'): ?>
              <select name="custom_<?=$col?>" class="form-control">
                <option value="">Select…</option>
                <option value="Yes" <?=($emp[$col]??'')==='Yes'?'selected':''?>>Yes</option>
                <option value="No" <?=($emp[$col]??'')==='No'?'selected':''?>>No</option>
              </select>
            <?php elseif($fieldType === 'date'): ?>
              <input type="date" name="custom_<?=$col?>" class="form-control" value="<?=val($emp,$col)?>">
            <?php elseif($fieldType === 'number'): ?>
              <input type="number" name="custom_<?=$col?>" class="form-control" step="0.01" value="<?=val($emp,$col)?>">
            <?php elseif($fieldType === 'email'): ?>
              <input type="email" name="custom_<?=$col?>" class="form-control" value="<?=val($emp,$col)?>">
            <?php elseif($fieldType === 'phone'): ?>
              <input type="tel" name="custom_<?=$col?>" class="form-control" value="<?=val($emp,$col)?>">
            <?php elseif($fieldType === 'textarea'): ?>
              <textarea name="custom_<?=$col?>" class="form-control" rows="3"><?=val($emp,$col)?></textarea>
            <?php elseif($fieldType === 'url'): ?>
              <input type="url" name="custom_<?=$col?>" class="form-control" value="<?=val($emp,$col)?>">
            <?php else: ?>
              <input type="text" name="custom_<?=$col?>" class="form-control" value="<?=val($emp,$col)?>">
            <?php endif; ?>
          </div>
          <?php endforeach;?>
        </div>
      </div></div>
      <?php endif;?>

      <div style="display:flex;gap:10px;justify-content:flex-end;padding-bottom:32px;">
        <a href="profile.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>

    <?php else:?>
    <!-- VIEW MODE -->
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
      <a href="profile.php?edit=1" class="btn btn-primary btn-sm">Edit Profile</a>
    </div>

    <div style="background:linear-gradient(135deg,var(--brand),var(--brand-mid));border-radius:12px;padding:24px;color:#fff;margin-bottom:20px;display:flex;align-items:center;gap:20px;">
      <div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;"><?=strtoupper(substr($emp['first_name'],0,1))?></div>
      <div><div style="font-size:20px;font-weight:800;"><?=htmlspecialchars($emp['first_name'].' '.$emp['last_name'])?></div>
      <div style="font-size:13px;opacity:.85;"><?=htmlspecialchars($emp['job_title']??'')?> · <?=htmlspecialchars($emp['department_name']??'')?> · <?=htmlspecialchars($emp['employee_id']??'')?></div></div>
    </div>

    <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Personal Information</h2></div><div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;">
        <?php $pFields=['phone'=>'Phone','personal_email'=>'Personal Email','date_of_birth'=>'Date of Birth','gender'=>'Gender','marital_status'=>'Marital Status','blood_group'=>'Blood Group','nationality'=>'Nationality','place_of_birth'=>'Place of Birth','emergency_contact_no'=>'Emergency Contact'];
        foreach($pFields as $k=>$l):$v=$emp[$k]??'';if($k==='date_of_birth'&&$v)$v=date('d M Y',strtotime($v));?>
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:3px;"><?=$l?></div><div style="font-size:13px;font-weight:600;color:<?=$v?'var(--text)':'var(--muted-light)'?>;"><?=htmlspecialchars($v?:'—')?></div></div>
        <?php endforeach;?>
      </div>
    </div></div>

    <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Address</h2></div><div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div><div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:8px;">CURRENT</div>
          <div style="font-size:13px;color:var(--text);line-height:1.6;"><?=htmlspecialchars(implode(', ',array_filter([$emp['address_line1']??'',$emp['address_line2']??'',$emp['city']??'',$emp['state']??'',$emp['zip_code']??'',$emp['country']??''])))?:'—'?></div></div>
        <div><div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:8px;">PERMANENT</div>
          <div style="font-size:13px;color:var(--text);line-height:1.6;"><?=htmlspecialchars(implode(', ',array_filter([$emp['permanent_address_line1']??'',$emp['permanent_address_line2']??'',$emp['permanent_city']??'',$emp['permanent_state']??'',$emp['permanent_zip_code']??''])))?:'—'?></div></div>
      </div>
    </div></div>

    <?php if($emp['passport_no']):?>
    <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Passport Details</h2></div><div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:3px;">Passport No</div><div style="font-size:13px;font-weight:600;"><?=htmlspecialchars($emp['passport_no'])?></div></div>
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:3px;">Place of Issue</div><div style="font-size:13px;font-weight:600;"><?=htmlspecialchars($emp['place_of_issue']??'—')?></div></div>
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:3px;">Issue Date</div><div style="font-size:13px;font-weight:600;"><?=$emp['passport_date_of_issue']?date('d M Y',strtotime($emp['passport_date_of_issue'])):'—'?></div></div>
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:3px;">Expiry Date</div><div style="font-size:13px;font-weight:600;"><?=$emp['passport_date_of_expiry']?date('d M Y',strtotime($emp['passport_date_of_expiry'])):'—'?></div></div>
      </div>
    </div></div>
    <?php endif;?>

    <!-- Custom Fields View -->
    <?php if(!empty($customCols)):?>
    <div class="card" style="margin-bottom:16px;"><div class="card-header"><h2>Additional Information</h2></div><div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;">
        <?php foreach($customCols as $col):$v=$emp[$col]??'';$label=ucwords(str_replace('_',' ',$col));?>
        <div><div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:3px;"><?=$label?></div><div style="font-size:13px;font-weight:600;color:<?=$v?'var(--text)':'var(--muted-light)'?>;"><?=htmlspecialchars($v?:'—')?></div></div>
        <?php endforeach;?>
      </div>
    </div></div>
    <?php endif;?>

    <?php endif;?>
  </div>
</div>
</div>
</body>
</html>
