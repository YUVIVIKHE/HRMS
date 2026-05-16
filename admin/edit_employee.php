<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../auth/provision_user.php';
guardRole('admin');
$db = getDB();

$id = (int)($_GET['id'] ?? $_POST['employee_id'] ?? 0);
if ($id <= 0) { header("Location: employees.php"); exit; }

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Handle Save ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // ── Promote to Manager ──────────────────────────────────
    if ($postAction === 'promote_to_manager') {
        $empData = $db->prepare("SELECT e.*, d.name AS dept_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.id = ?");
        $empData->execute([$id]);
        $empData = $empData->fetch();

        if ($empData) {
            $fullName = trim($empData['first_name'].' '.$empData['last_name']);

            // Single atomic operation: update role if user exists, insert only if not
            // ON DUPLICATE KEY UPDATE prevents any possibility of creating a second row
            require_once __DIR__ . '/../auth/mailer.php';
            $plain  = generatePassword(10);
            $hashed = password_hash($plain, PASSWORD_BCRYPT);

            $db->prepare("
                INSERT INTO users (name, email, password, role, status)
                VALUES (?, ?, ?, 'manager', 'active')
                ON DUPLICATE KEY UPDATE role = 'manager', name = VALUES(name)
            ")->execute([$fullName, $empData['email'], $hashed]);
            // Note: password is only set on INSERT (new account). Existing users keep their password.

            // 2. Get the new manager's user id (for reference only)
            $managerUser = $db->prepare("SELECT id FROM users WHERE email = ?");
            $managerUser->execute([$empData['email']]);
            $managerUserId = $managerUser->fetchColumn();

            // Note: team membership is now determined by department (employees table)
            // No manager_id column in users table — relationship is dept-based

            // 4. Send congratulation email
            require_once __DIR__ . '/../auth/mailer.php';
            $sent = sendPromotionEmail(
                $empData['email'],
                trim($empData['first_name'].' '.$empData['last_name']),
                $empData['dept_name'] ?? ''
            );

            $_SESSION['flash_success'] = trim($empData['first_name'].' '.$empData['last_name'])
                . " has been promoted to Manager."
                . ($sent ? " Congratulation email sent." : " (Email delivery failed.)");
        }
        header("Location: edit_employee.php?id=$id"); exit;
    }

    // Resend / create login account
    if ($postAction === 'provision_user') {        $empData = $db->prepare("SELECT email, first_name, last_name FROM employees WHERE id = ?");
        $empData->execute([$id]);
        $empData = $empData->fetch();
        if ($empData) {
            $provision = provisionEmployeeUser($db, $empData['email'], $empData['first_name'], $empData['last_name']);
            if ($provision['success']) {
                $_SESSION['flash_success'] = $provision['message'];
            } else {
                // Already exists — reset password and resend
                require_once __DIR__ . '/../auth/mailer.php';
                $newPass = generatePassword(10);
                $db->prepare("UPDATE users SET password = ? WHERE email = ?")
                   ->execute([password_hash($newPass, PASSWORD_BCRYPT), $empData['email']]);
                $sent = sendWelcomeEmail($empData['email'], trim($empData['first_name'].' '.$empData['last_name']), $newPass);
                $_SESSION['flash_success'] = "Password reset and " . ($sent ? "email sent to {$empData['email']}." : "email delivery failed.");
            }
        }
        header("Location: edit_employee.php?id=$id"); exit;
    }

    if ($postAction === 'edit_employee') {
        try {
        // All editable columns (excluding id, created_at)
        $allCols = $db->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
        $skip    = ['id', 'created_at', 'acl_eligible'];

        $sets   = [];
        $params = [];
        // Fields that should NOT be blanked out if left empty in the form
        $keepIfEmpty = ['personal_email', 'phone', 'emergency_contact_no', 'blood_group',
                        'nationality', 'place_of_birth', 'passport_no', 'place_of_issue',
                        'uan_number', 'pf_account_number', 'esi_number', 'pan', 'aadhar_no'];
        foreach ($allCols as $col) {
            if (in_array($col, $skip)) continue;
            if (!array_key_exists($col, $_POST)) continue;
            $val = trim($_POST[$col]);
            // If field is empty and it's a "keep if empty" field, skip — don't overwrite existing value
            if ($val === '' && in_array($col, $keepIfEmpty)) continue;
            $sets[]   = "`$col` = ?";
            $params[] = ($val === '') ? null : $val;
        }
        // exempt_from_tax is a checkbox — not in $_POST when unchecked
        if (in_array('exempt_from_tax', $allCols) && !array_key_exists('exempt_from_tax', $_POST)) {
            $sets[]   = "`exempt_from_tax` = ?";
            $params[] = 0;
        }
        // acl_eligible checkbox
        if (in_array('acl_eligible', $allCols)) {
            $sets[]   = "`acl_eligible` = ?";
            $params[] = isset($_POST['acl_eligible']) ? 1 : 0;
        }

        // ── Email uniqueness check (exclude current employee) ──
        $newEmail = strtolower(trim($_POST['email'] ?? ''));
        // Capture current email BEFORE any update (needed for users table sync)
        $currentEmpEmailStmt = $db->prepare("SELECT email FROM employees WHERE id=?");
        $currentEmpEmailStmt->execute([$id]);
        $currentEmpEmail = $currentEmpEmailStmt->fetchColumn() ?: '';

        if ($newEmail) {
            $dupEmp = $db->prepare("SELECT id FROM employees WHERE LOWER(email)=? AND id!=?");
            $dupEmp->execute([$newEmail, $id]);
            if ($dupEmp->fetch()) {
                $_SESSION['flash_error'] = "Work email '$newEmail' is already registered to another employee.";
                header("Location: edit_employee.php?id=$id"); exit;
            }
            // Check against other employees' personal emails
            $dupAsPersonal = $db->prepare("SELECT id FROM employees WHERE LOWER(personal_email)=? AND id!=?");
            $dupAsPersonal->execute([$newEmail, $id]);
            if ($dupAsPersonal->fetch()) {
                $_SESSION['flash_error'] = "The email '$newEmail' is already in use as a personal email by another employee.";
                header("Location: edit_employee.php?id=$id"); exit;
            }
            // Check users table (excluding current employee's own account)
            $dupUser = $db->prepare("SELECT id FROM users WHERE LOWER(email)=? AND LOWER(email)!=?");
            $dupUser->execute([$newEmail, strtolower($currentEmpEmail)]);
            if ($dupUser->fetch()) {
                $_SESSION['flash_error'] = "Work email '$newEmail' is already in use by another user account.";
                header("Location: edit_employee.php?id=$id"); exit;
            }
        }

        // ── Personal email uniqueness check (exclude current employee) ──
        $personalEmail = strtolower(trim($_POST['personal_email'] ?? ''));
        if ($personalEmail) {
            // Rule 1: personal email must not be the same as THIS employee's own work email
            if ($personalEmail === $newEmail) {
                $_SESSION['flash_error'] = "Personal email cannot be the same as the work email. Please use a different email address.";
                header("Location: edit_employee.php?id=$id"); exit;
            }
            // Rule 2: personal email must not be used as work email by ANY other employee
            $dupWorkEmail = $db->prepare("SELECT id FROM employees WHERE LOWER(email)=? AND id!=?");
            $dupWorkEmail->execute([$personalEmail, $id]);
            if ($dupWorkEmail->fetch()) {
                $_SESSION['flash_error'] = "The email '$personalEmail' is already in use as a work email by another employee.";
                header("Location: edit_employee.php?id=$id"); exit;
            }
            // Rule 3: personal email must not be used as personal email by ANY other employee
            $dupPersonalEmail = $db->prepare("SELECT id FROM employees WHERE LOWER(personal_email)=? AND id!=?");
            $dupPersonalEmail->execute([$personalEmail, $id]);
            if ($dupPersonalEmail->fetch()) {
                $_SESSION['flash_error'] = "The email '$personalEmail' is already registered as a personal email by another employee.";
                header("Location: edit_employee.php?id=$id"); exit;
            }
        }

        // ── Phone uniqueness check (exclude current employee) ──
        $newPhone = trim($_POST['phone'] ?? '');
        if ($newPhone) {
            // Normalise: strip spaces, dashes, brackets for comparison
            $normPhone = preg_replace('/[\s\-\(\)\+]/', '', $newPhone);
            $dupPhone  = $db->prepare("SELECT id FROM employees WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','')=? AND id!=?");
            $dupPhone->execute([$normPhone, $id]);
            if ($dupPhone->fetch()) {
                $_SESSION['flash_error'] = "Phone number '$newPhone' is already registered to another employee.";
                header("Location: edit_employee.php?id=$id"); exit;
            }
        }

        $params[] = $id;
        $db->prepare("UPDATE employees SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

        // ── Sync users.email if work email changed ─────────────
        $updatedEmail = trim($_POST['email'] ?? '');
        if ($updatedEmail && $currentEmpEmail && strtolower($currentEmpEmail) !== strtolower($updatedEmail)) {
            $db->prepare("UPDATE users SET email=? WHERE LOWER(email)=?")
               ->execute([$updatedEmail, strtolower($currentEmpEmail)]);
        }

        // ── Sync users.name from first_name + last_name ─────────
        $updatedFirst = trim($_POST['first_name'] ?? '');
        $updatedLast  = trim($_POST['last_name']  ?? '');
        if ($updatedFirst || $updatedLast) {
            $fullName    = trim("$updatedFirst $updatedLast");
            $syncEmail   = $updatedEmail ?: $currentEmpEmail;
            if ($fullName && $syncEmail) {
                $db->prepare("UPDATE users SET name=? WHERE LOWER(email)=?")
                   ->execute([$fullName, strtolower($syncEmail)]);
            }
        }

        // ── Department change: no users.manager_id to sync (dept-based relationship) ──
        // The employee's department is already updated in the employees table above.

        // Sync status to users table
        $empStatus = trim($_POST['status'] ?? 'Active');
        $userStatus = (strtolower($empStatus) === 'active') ? 'active' : 'inactive';
        $syncEmail2 = $updatedEmail ?: $currentEmpEmail;
        if ($syncEmail2) {
            $db->prepare("UPDATE users SET status=? WHERE LOWER(email)=?")->execute([$userStatus, strtolower($syncEmail2)]);
        }

        $_SESSION['flash_success'] = "Employee updated successfully.";
        header("Location: edit_employee.php?id=$id"); exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
        header("Location: edit_employee.php?id=$id"); exit;
    }
    } // end if edit_employee
} // end POST

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

// Check if login account exists
$hasAccount = $db->prepare("SELECT id, role FROM users WHERE email = ?");
$hasAccount->execute([$emp['email']]);
$userRow    = $hasAccount->fetch();
$hasAccount = (bool)$userRow;
$isManager  = $userRow && $userRow['role'] === 'manager';

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
        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
          <?php if($isManager): ?>
            <span class="badge badge-brand">Manager</span>
          <?php elseif($hasAccount): ?>
            <span class="badge badge-green">Login Account Active</span>
          <?php else: ?>
            <span class="badge badge-red">No Login Account</span>
          <?php endif; ?>
        </div>
      </div>
      <div style="margin-left:auto;display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
        <?php if(!$isManager): ?>
        <form method="POST" style="display:inline;"
          onsubmit="return confirm('Promote <?= addslashes($emp['first_name'].' '.$emp['last_name']) ?> to Manager?\n\nThey will get manager access and all employees in their department will be assigned to their team. A congratulation email will be sent.')">
          <input type="hidden" name="action" value="promote_to_manager">
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Promote to Manager
          </button>
        </form>
        <?php else: ?>
          <span style="font-size:13px;color:var(--muted);align-self:center;">Already a Manager</span>
        <?php endif; ?>
        <form method="POST" style="display:inline;"
          onsubmit="return confirm('This will <?= $hasAccount ? 'reset the password and send' : 'create an account and send' ?> a login email to <?= htmlspecialchars($emp['email']) ?>. Continue?')">
          <input type="hidden" name="action" value="provision_user">
          <button type="submit" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <?= $hasAccount ? 'Resend Credentials' : 'Send Login Credentials' ?>
          </button>
        </form>
        <a href="employees.php" class="btn btn-secondary">← Back</a>
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
            <div class="form-group"><label>Employee ID</label>
              <input type="text" class="form-control" value="<?= val($emp,'employee_id') ?>" readonly style="background:var(--surface-2);color:var(--muted);cursor:not-allowed;">
              <span style="font-size:11.5px;color:var(--muted-light);margin-top:3px;">Auto-generated, cannot be changed</span>
            </div>
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
              <select name="status" id="empStatus" class="form-control" onchange="onStatusChange(this.value)">
                <option value="active" <?= sel($emp,'status','active') ?>>Active</option>
                <option value="inactive" <?= sel($emp,'status','inactive') ?>>Inactive</option>
              </select>
            </div>
            <div class="form-group">
              <label>ACL Eligible</label>
              <div class="form-check" style="margin-top:6px;">
                <input type="checkbox" name="acl_eligible" id="aclEligible" value="1" <?= ($emp['acl_eligible'] ?? 1) ? 'checked' : '' ?>>
                <label for="aclEligible">Allow ACL (Compensatory Leave) for this employee</label>
              </div>
            </div>
            <div class="form-group"><label>Date of Joining</label><input type="date" name="date_of_joining" class="form-control" value="<?= val($emp,'date_of_joining') ?>"></div>
            <div class="form-group"><label>Date of Confirmation</label><input type="date" name="date_of_confirmation" class="form-control" value="<?= val($emp,'date_of_confirmation') ?>"></div>
            <div class="form-group" id="exitDateGroup"><label>Date of Exit <span id="exitReq" style="display:none;color:var(--red);">*</span></label><input type="date" name="date_of_exit" id="dateOfExit" class="form-control" value="<?= val($emp,'date_of_exit') ?>"></div>
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
<script>
function onStatusChange(val) {
  const exitGroup = document.getElementById('exitDateGroup');
  const exitInput = document.getElementById('dateOfExit');
  const exitReq = document.getElementById('exitReq');
  if (val === 'inactive') {
    exitGroup.style.border = '2px solid var(--red)';
    exitGroup.style.borderRadius = '8px';
    exitGroup.style.padding = '8px';
    exitGroup.style.background = '#fef2f2';
    exitReq.style.display = 'inline';
    exitInput.required = true;
    exitInput.focus();
    exitInput.scrollIntoView({behavior:'smooth', block:'center'});
  } else {
    exitGroup.style.border = '';
    exitGroup.style.borderRadius = '';
    exitGroup.style.padding = '';
    exitGroup.style.background = '';
    exitReq.style.display = 'none';
    exitInput.required = false;
  }
}
// On form submit validate
document.querySelector('form')?.addEventListener('submit', function(e) {
  const status = document.getElementById('empStatus').value;
  const exitDate = document.getElementById('dateOfExit').value;
  if (status === 'inactive' && !exitDate) {
    e.preventDefault();
    alert('Date of Exit is required when setting status to Inactive.');
    document.getElementById('dateOfExit').focus();
  }
});
// Init on load
if (document.getElementById('empStatus').value === 'inactive') onStatusChange('inactive');
</script>
</body>
</html>
