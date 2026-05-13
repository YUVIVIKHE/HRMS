<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$id = (int)($_GET['id'] ?? $_POST['employee_id'] ?? 0);
if ($id <= 0) { header("Location: employees.php"); exit; }

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Handle Save ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_employee') {
    try {
        // All editable columns (excluding id, created_at)
        $allCols = $db->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
        $skip    = ['id', 'created_at'];

        $sets   = [];
        $params = [];
        foreach ($allCols as $col) {
            if (in_array($col, $skip)) continue;
            if (!array_key_exists($col, $_POST)) continue;
            $sets[]   = "`$col` = ?";
            $val      = trim($_POST[$col]);
            $params[] = ($val === '') ? null : $val;
        }
        // exempt_from_tax is a checkbox — not in $_POST when unchecked
        if (in_array('exempt_from_tax', $allCols) && !array_key_exists('exempt_from_tax', $_POST)) {
            $sets[]   = "`exempt_from_tax` = ?";
            $params[] = 0;
        }

        $params[] = $id;
        $db->prepare("UPDATE employees SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        $_SESSION['flash_success'] = "Employee updated successfully.";
        header("Location: edit_employee.php?id=$id"); exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
        header("Location: edit_employee.php?id=$id"); exit;
    }
}

// ── Load employee ────────────────────────────────────────────
$emp = $db->prepare("SELECT * FROM employees WHERE id = ?");
$emp->execute([$id]);
$emp = $emp->fetch(PDO::FETCH_ASSOC);
if (!$emp) { header("Location: employees.php"); exit; }

// Custom columns
$allCols     = $db->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
$baseColumns = ['id','first_name','last_name','email','phone','job_title','date_of_birth','gender','marital_status','employee_id','department_id','employee_type','date_of_joining','date_of_exit','date_of_confirmation','direct_manager_name','location','base_location','user_code','address_line1','address_line2','city','state','zip_code','country','permanent_address_line1','permanent_address_line2','permanent_city','permanent_state','permanent_zip_code','account_type','account_number','ifsc_code','pan','aadhar_no','uan_number','pf_account_number','employee_provident_fund','professional_tax','esi_number','exempt_from_tax','passport_no','place_of_issue','passport_date_of_issue','passport_date_of_expiry','place_of_birth','nationality','blood_group','personal_email','emergency_contact_no','country_code_phone','status','created_at','gross_salary'];
$customCols  = array_diff($allCols, $baseColumns);

$deptList = $db->query("SELECT id, name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$fullName = htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']);

// Helper: field value
function val($emp, $key) { return htmlspecialchars($emp[$key] ?? ''); }
function sel($emp, $key, $option) { return ($emp[$key] ?? '') == $option ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Employee – <?= $fullName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Edit Employee</span>
      <span class="page-breadcrumb">
        <a href="employees.php" style="color:var(--muted);text-decoration:none;">Employees</a>
        &rsaquo; <?= $fullName ?>
      </span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?>
      <div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
      <div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Profile header -->
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding:20px 24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);">
      <div style="width:52px;height:52px;border-radius:50%;background:var(--brand-light);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;flex-shrink:0;">
        <?= strtoupper(substr($emp['first_name'],0,1)) ?>
      </div>
      <div>
        <div style="font-size:17px;font-weight:800;color:var(--text);"><?= $fullName ?></div>
        <div style="font-size:13px;color:var(--muted);margin-top:2px;"><?= val($emp,'email') ?> <?= $emp['employee_id'] ? '· '.$emp['employee_id'] : '' ?></div>
      </div>
      <div style="margin-left:auto;display:flex;gap:10px;">
        <a href="employees.php" class="btn btn-secondary">← Back to List</a>
      </div>
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="edit_employee">
      <input type="hidden" name="employee_id" value="<?= $id ?>">

      <?php if(!empty($customCols)): ?>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Custom Fields</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <?php foreach($customCols as $col): ?>
            <div class="form-group">
              <label><?= htmlspecialchars(ucwords(str_replace('_',' ',$col))) ?></label>
              <input type="text" name="<?= htmlspecialchars($col) ?>" class="form-control" value="<?= val($emp,$col) ?>">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Personal Information -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Personal Information</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>First Name <span class="req">*</span></label><input type="text" name="first_name" class="form-control" value="<?= val($emp,'first_name') ?>" required></div>
            <div class="form-group"><label>Last Name <span class="req">*</span></label><input type="text" name="last_name" class="form-control" value="<?= val($emp,'last_name') ?>" required></div>
            <div class="form-group"><label>Work Email <span class="req">*</span></label><input type="email" name="email" class="form-control" value="<?= val($emp,'email') ?>" required></div>
            <div class="form-group"><label>Personal Email</label><input type="email" name="personal_email" class="form-control" value="<?= val($emp,'personal_email') ?>"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= val($emp,'phone') ?>"></div>
            <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= val($emp,'date_of_birth') ?>"></div>
            <div class="form-group"><label>Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select</option>
                <option <?= sel($emp,'gender','Male') ?>>Male</option>
                <option <?= sel($emp,'gender','Female') ?>>Female</option>
                <option <?= sel($emp,'gender','Other') ?>>Other</option>
              </select>
            </div>
            <div class="form-group"><label>Marital Status</label>
              <select name="marital_status" class="form-control">
                <option value="">Select</option>
                <option <?= sel($emp,'marital_status','Single') ?>>Single</option>
                <option <?= sel($emp,'marital_status','Married') ?>>Married</option>
                <option <?= sel($emp,'marital_status','Divorced') ?>>Divorced</option>
                <option <?= sel($emp,'marital_status','Widowed') ?>>Widowed</option>
              </select>
            </div>
            <div class="form-group"><label>Blood Group</label><input type="text" name="blood_group" class="form-control" value="<?= val($emp,'blood_group') ?>"></div>
            <div class="form-group"><label>Nationality</label><input type="text" name="nationality" class="form-control" value="<?= val($emp,'nationality') ?>"></div>
            <div class="form-group"><label>Place of Birth</label><input type="text" name="place_of_birth" class="form-control" value="<?= val($emp,'place_of_birth') ?>"></div>
            <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact_no" class="form-control" value="<?= val($emp,'emergency_contact_no') ?>"></div>
          </div>
        </div>
      </div>

      <!-- Employment Details -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Employment Details</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_id" class="form-control" value="<?= val($emp,'employee_id') ?>"></div>
            <div class="form-group"><label>User Code</label><input type="text" name="user_code" class="form-control" value="<?= val($emp,'user_code') ?>"></div>
            <div class="form-group"><label>Job Title</label><input type="text" name="job_title" class="form-control" value="<?= val($emp,'job_title') ?>"></div>
            <div class="form-group"><label>Department</label>
              <select name="department_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach($deptList as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= sel($emp,'department_id',$d['id']) ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Employee Type</label>
              <select name="employee_type" class="form-control">
                <option <?= sel($emp,'employee_type','FTE') ?>>FTE</option>
                <option <?= sel($emp,'employee_type','External') ?>>External</option>
              </select>
            </div>
            <div class="form-group"><label>Status</label>
              <select name="status" class="form-control">
                <option value="active" <?= sel($emp,'status','active') ?>>Active</option>
                <option value="inactive" <?= sel($emp,'status','inactive') ?>>Inactive</option>
                <option value="terminated" <?= sel($emp,'status','terminated') ?>>Terminated</option>
              </select>
            </div>
            <div class="form-group"><label>Date of Joining</label><input type="date" name="date_of_joining" class="form-control" value="<?= val($emp,'date_of_joining') ?>"></div>
            <div class="form-group"><label>Date of Confirmation</label><input type="date" name="date_of_confirmation" class="form-control" value="<?= val($emp,'date_of_confirmation') ?>"></div>
            <div class="form-group"><label>Date of Exit</label><input type="date" name="date_of_exit" class="form-control" value="<?= val($emp,'date_of_exit') ?>"></div>
            <div class="form-group"><label>Direct Manager</label><input type="text" name="direct_manager_name" class="form-control" value="<?= val($emp,'direct_manager_name') ?>"></div>
            <div class="form-group"><label>Location</label><input type="text" name="location" class="form-control" value="<?= val($emp,'location') ?>"></div>
            <div class="form-group"><label>Base Location</label><input type="text" name="base_location" class="form-control" value="<?= val($emp,'base_location') ?>"></div>
          </div>
        </div>
      </div>

      <!-- Address -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Address</h2></div></div>
        <div class="card-body">
          <div class="form-section-title">Current Address</div>
          <div class="form-grid">
            <div class="form-group"><label>Address Line 1</label><input type="text" name="address_line1" class="form-control" value="<?= val($emp,'address_line1') ?>"></div>
            <div class="form-group"><label>Address Line 2</label><input type="text" name="address_line2" class="form-control" value="<?= val($emp,'address_line2') ?>"></div>
            <div class="form-group"><label>City</label><input type="text" name="city" class="form-control" value="<?= val($emp,'city') ?>"></div>
            <div class="form-group"><label>State</label><input type="text" name="state" class="form-control" value="<?= val($emp,'state') ?>"></div>
            <div class="form-group"><label>Zip Code</label><input type="text" name="zip_code" class="form-control" value="<?= val($emp,'zip_code') ?>"></div>
            <div class="form-group"><label>Country</label><input type="text" name="country" class="form-control" value="<?= val($emp,'country') ?>"></div>
          </div>
          <div class="divider"></div>
          <div class="form-section-title">Permanent Address</div>
          <div class="form-grid">
            <div class="form-group"><label>Address Line 1</label><input type="text" name="permanent_address_line1" class="form-control" value="<?= val($emp,'permanent_address_line1') ?>"></div>
            <div class="form-group"><label>Address Line 2</label><input type="text" name="permanent_address_line2" class="form-control" value="<?= val($emp,'permanent_address_line2') ?>"></div>
            <div class="form-group"><label>City</label><input type="text" name="permanent_city" class="form-control" value="<?= val($emp,'permanent_city') ?>"></div>
            <div class="form-group"><label>State</label><input type="text" name="permanent_state" class="form-control" value="<?= val($emp,'permanent_state') ?>"></div>
            <div class="form-group"><label>Zip Code</label><input type="text" name="permanent_zip_code" class="form-control" value="<?= val($emp,'permanent_zip_code') ?>"></div>
          </div>
        </div>
      </div>

      <!-- Financial & Statutory -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Financial & Statutory</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Gross Salary</label><input type="number" step="0.01" name="gross_salary" class="form-control" value="<?= val($emp,'gross_salary') ?>"></div>
            <div class="form-group"><label>Account Type</label>
              <select name="account_type" class="form-control">
                <option value="">Select</option>
                <option <?= sel($emp,'account_type','Savings') ?>>Savings</option>
                <option <?= sel($emp,'account_type','Current') ?>>Current</option>
              </select>
            </div>
            <div class="form-group"><label>Account Number</label><input type="text" name="account_number" class="form-control" value="<?= val($emp,'account_number') ?>"></div>
            <div class="form-group"><label>IFSC Code</label><input type="text" name="ifsc_code" class="form-control" value="<?= val($emp,'ifsc_code') ?>"></div>
            <div class="form-group"><label>PAN Number</label><input type="text" name="pan" class="form-control" value="<?= val($emp,'pan') ?>"></div>
            <div class="form-group"><label>Aadhar Number</label><input type="text" name="aadhar_no" class="form-control" value="<?= val($emp,'aadhar_no') ?>"></div>
            <div class="form-group"><label>UAN Number</label><input type="text" name="uan_number" class="form-control" value="<?= val($emp,'uan_number') ?>"></div>
            <div class="form-group"><label>PF Account Number</label><input type="text" name="pf_account_number" class="form-control" value="<?= val($emp,'pf_account_number') ?>"></div>
            <div class="form-group"><label>Employee PF</label><input type="text" name="employee_provident_fund" class="form-control" value="<?= val($emp,'employee_provident_fund') ?>"></div>
            <div class="form-group"><label>Professional Tax</label><input type="text" name="professional_tax" class="form-control" value="<?= val($emp,'professional_tax') ?>"></div>
            <div class="form-group"><label>ESI Number</label><input type="text" name="esi_number" class="form-control" value="<?= val($emp,'esi_number') ?>"></div>
            <div class="form-group" style="justify-content:flex-end;padding-top:20px;">
              <div class="form-check">
                <input type="checkbox" name="exempt_from_tax" id="exempt_from_tax" value="1" <?= !empty($emp['exempt_from_tax']) ? 'checked' : '' ?>>
                <label for="exempt_from_tax">Exempt from Tax</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Passport -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Passport Details</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Passport Number</label><input type="text" name="passport_no" class="form-control" value="<?= val($emp,'passport_no') ?>"></div>
            <div class="form-group"><label>Place of Issue</label><input type="text" name="place_of_issue" class="form-control" value="<?= val($emp,'place_of_issue') ?>"></div>
            <div class="form-group"><label>Date of Issue</label><input type="date" name="passport_date_of_issue" class="form-control" value="<?= val($emp,'passport_date_of_issue') ?>"></div>
            <div class="form-group"><label>Date of Expiry</label><input type="date" name="passport_date_of_expiry" class="form-control" value="<?= val($emp,'passport_date_of_expiry') ?>"></div>
          </div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:4px;padding-bottom:32px;">
        <a href="employees.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Save Changes
        </button>
      </div>
    </form>

  </div>
</div>
</div>
</body>
</html>
