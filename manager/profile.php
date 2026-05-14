<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$user = $db->prepare("SELECT email FROM users WHERE id = ?");
$user->execute([$uid]);
$user = $user->fetch();

$emp = null;
if ($user) {
    $stmt = $db->prepare("
        SELECT e.*, d.name AS department_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.email = ?
    ");
    $stmt->execute([$user['email']]);
    $emp = $stmt->fetch();
}

// Team size — dept-based
$mgrDeptStmt = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$mgrDeptStmt->execute([$uid]); $mgrDeptId2 = $mgrDeptStmt->fetchColumn();
$teamSizeStmt = $db->prepare("SELECT COUNT(*) FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? AND u.status='active'");
$teamSizeStmt->execute([$mgrDeptId2 ?: 0, $uid]);
$teamSize = (int)$teamSizeStmt->fetchColumn();

$firstName = explode(' ', $_SESSION['user_name'])[0];

function fv($emp, $key, $fallback = '—') {
    $v = $emp[$key] ?? '';
    return $v !== '' && $v !== null ? htmlspecialchars($v) : $fallback;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.profile-hero {
  background: linear-gradient(135deg, var(--brand) 0%, var(--brand-mid) 100%);
  border-radius: var(--radius-lg);
  padding: 32px 36px;
  display: flex;
  align-items: center;
  gap: 24px;
  margin-bottom: 24px;
  color: #fff;
  position: relative;
  overflow: hidden;
}
.profile-hero::after {
  content: '';
  position: absolute;
  right: -60px; top: -60px;
  width: 240px; height: 240px;
  border-radius: 50%;
  background: rgba(255,255,255,.08);
}
.profile-avatar {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: rgba(255,255,255,.25);
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; font-weight: 800;
  flex-shrink: 0;
  border: 3px solid rgba(255,255,255,.4);
  position: relative; z-index: 1;
}
.profile-hero-info { position: relative; z-index: 1; }
.profile-hero-info h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.profile-hero-info p  { font-size: 14px; opacity: .85; }
.profile-hero-chips { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.hero-chip {
  background: rgba(255,255,255,.2);
  border: 1px solid rgba(255,255,255,.3);
  border-radius: 20px;
  padding: 3px 12px;
  font-size: 12px; font-weight: 600;
}
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0;
}
.info-item {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border-light);
  border-right: 1px solid var(--border-light);
}
.info-label {
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em;
  color: var(--muted-light); margin-bottom: 4px;
}
.info-value { font-size: 13.5px; font-weight: 600; color: var(--text); }
.info-value.empty { color: var(--muted-light); font-weight: 400; }
</style>
</head>
<body>
<div class="app-shell">

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">My Profile</span>
      <span class="page-breadcrumb">View your details</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if(!$emp): ?>
      <div class="alert alert-warning">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Your employee profile has not been set up yet. Please contact your HR administrator.
      </div>
    <?php else: ?>

    <!-- Hero -->
    <div class="profile-hero">
      <div class="profile-avatar"><?= strtoupper(substr($emp['first_name'],0,1)) ?></div>
      <div class="profile-hero-info">
        <h1><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></h1>
        <p><?= fv($emp,'job_title') ?> <?= $emp['department_name'] ? '· '.htmlspecialchars($emp['department_name']) : '' ?></p>
        <div class="profile-hero-chips">
          <span class="hero-chip"><?= fv($emp,'employee_id') ?></span>
          <span class="hero-chip">Manager</span>
          <span class="hero-chip"><?= $teamSize ?> Team Member<?= $teamSize !== 1 ? 's' : '' ?></span>
          <?php if($emp['date_of_joining']): ?>
            <span class="hero-chip">Joined <?= date('M Y', strtotime($emp['date_of_joining'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Personal Information -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><div><h2>Personal Information</h2></div></div>
      <div class="info-grid">
        <div class="info-item"><div class="info-label">First Name</div><div class="info-value"><?= fv($emp,'first_name') ?></div></div>
        <div class="info-item"><div class="info-label">Last Name</div><div class="info-value"><?= fv($emp,'last_name') ?></div></div>
        <div class="info-item"><div class="info-label">Work Email</div><div class="info-value"><?= fv($emp,'email') ?></div></div>
        <div class="info-item"><div class="info-label">Personal Email</div><div class="info-value <?= !$emp['personal_email']?'empty':'' ?>"><?= fv($emp,'personal_email') ?></div></div>
        <div class="info-item"><div class="info-label">Phone</div><div class="info-value <?= !$emp['phone']?'empty':'' ?>"><?= fv($emp,'phone') ?></div></div>
        <div class="info-item"><div class="info-label">Date of Birth</div><div class="info-value <?= !$emp['date_of_birth']?'empty':'' ?>"><?= $emp['date_of_birth'] ? date('d M Y', strtotime($emp['date_of_birth'])) : '—' ?></div></div>
        <div class="info-item"><div class="info-label">Gender</div><div class="info-value <?= !$emp['gender']?'empty':'' ?>"><?= fv($emp,'gender') ?></div></div>
        <div class="info-item"><div class="info-label">Marital Status</div><div class="info-value <?= !$emp['marital_status']?'empty':'' ?>"><?= fv($emp,'marital_status') ?></div></div>
        <div class="info-item"><div class="info-label">Blood Group</div><div class="info-value <?= !$emp['blood_group']?'empty':'' ?>"><?= fv($emp,'blood_group') ?></div></div>
        <div class="info-item"><div class="info-label">Nationality</div><div class="info-value <?= !$emp['nationality']?'empty':'' ?>"><?= fv($emp,'nationality') ?></div></div>
        <div class="info-item"><div class="info-label">Emergency Contact</div><div class="info-value <?= !$emp['emergency_contact_no']?'empty':'' ?>"><?= fv($emp,'emergency_contact_no') ?></div></div>
        <div class="info-item"><div class="info-label">Place of Birth</div><div class="info-value <?= !$emp['place_of_birth']?'empty':'' ?>"><?= fv($emp,'place_of_birth') ?></div></div>
      </div>
    </div>

    <!-- Employment Details -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><div><h2>Employment Details</h2></div></div>
      <div class="info-grid">
        <div class="info-item"><div class="info-label">Employee ID</div><div class="info-value"><?= fv($emp,'employee_id') ?></div></div>
        <div class="info-item"><div class="info-label">User Code</div><div class="info-value <?= !$emp['user_code']?'empty':'' ?>"><?= fv($emp,'user_code') ?></div></div>
        <div class="info-item"><div class="info-label">Job Title</div><div class="info-value <?= !$emp['job_title']?'empty':'' ?>"><?= fv($emp,'job_title') ?></div></div>
        <div class="info-item"><div class="info-label">Department</div><div class="info-value <?= !$emp['department_name']?'empty':'' ?>"><?= fv($emp,'department_name') ?></div></div>
        <div class="info-item"><div class="info-label">Employee Type</div><div class="info-value"><?= fv($emp,'employee_type') ?></div></div>
        <div class="info-item"><div class="info-label">Status</div><div class="info-value"><?= ucfirst(fv($emp,'status')) ?></div></div>
        <div class="info-item"><div class="info-label">Date of Joining</div><div class="info-value <?= !$emp['date_of_joining']?'empty':'' ?>"><?= $emp['date_of_joining'] ? date('d M Y', strtotime($emp['date_of_joining'])) : '—' ?></div></div>
        <div class="info-item"><div class="info-label">Date of Confirmation</div><div class="info-value <?= !$emp['date_of_confirmation']?'empty':'' ?>"><?= $emp['date_of_confirmation'] ? date('d M Y', strtotime($emp['date_of_confirmation'])) : '—' ?></div></div>
        <div class="info-item"><div class="info-label">Location</div><div class="info-value <?= !$emp['location']?'empty':'' ?>"><?= fv($emp,'location') ?></div></div>
        <div class="info-item"><div class="info-label">Base Location</div><div class="info-value <?= !$emp['base_location']?'empty':'' ?>"><?= fv($emp,'base_location') ?></div></div>
        <div class="info-item"><div class="info-label">Team Size</div><div class="info-value"><?= $teamSize ?> direct report<?= $teamSize !== 1 ? 's' : '' ?></div></div>
        <div class="info-item"><div class="info-label">Gross Salary</div><div class="info-value <?= !$emp['gross_salary']?'empty':'' ?>"><?= $emp['gross_salary'] ? '₹ '.number_format($emp['gross_salary'],2) : '—' ?></div></div>
      </div>
    </div>

    <!-- Address -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><div><h2>Address</h2></div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
        <div style="border-right:1px solid var(--border-light);">
          <div style="padding:12px 18px;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border-light);">Current Address</div>
          <div class="info-grid" style="grid-template-columns:1fr;">
            <div class="info-item"><div class="info-label">Address Line 1</div><div class="info-value <?= !$emp['address_line1']?'empty':'' ?>"><?= fv($emp,'address_line1') ?></div></div>
            <div class="info-item"><div class="info-label">Address Line 2</div><div class="info-value <?= !$emp['address_line2']?'empty':'' ?>"><?= fv($emp,'address_line2') ?></div></div>
            <div class="info-item"><div class="info-label">City / State</div><div class="info-value <?= !$emp['city']?'empty':'' ?>"><?= trim(fv($emp,'city','').', '.fv($emp,'state',''), ', ') ?: '—' ?></div></div>
            <div class="info-item"><div class="info-label">Zip / Country</div><div class="info-value <?= !$emp['zip_code']?'empty':'' ?>"><?= trim(fv($emp,'zip_code','').', '.fv($emp,'country',''), ', ') ?: '—' ?></div></div>
          </div>
        </div>
        <div>
          <div style="padding:12px 18px;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border-light);">Permanent Address</div>
          <div class="info-grid" style="grid-template-columns:1fr;">
            <div class="info-item"><div class="info-label">Address Line 1</div><div class="info-value <?= !$emp['permanent_address_line1']?'empty':'' ?>"><?= fv($emp,'permanent_address_line1') ?></div></div>
            <div class="info-item"><div class="info-label">Address Line 2</div><div class="info-value <?= !$emp['permanent_address_line2']?'empty':'' ?>"><?= fv($emp,'permanent_address_line2') ?></div></div>
            <div class="info-item"><div class="info-label">City / State</div><div class="info-value <?= !$emp['permanent_city']?'empty':'' ?>"><?= trim(fv($emp,'permanent_city','').', '.fv($emp,'permanent_state',''), ', ') ?: '—' ?></div></div>
            <div class="info-item"><div class="info-label">Zip</div><div class="info-value <?= !$emp['permanent_zip_code']?'empty':'' ?>"><?= fv($emp,'permanent_zip_code') ?></div></div>
          </div>
        </div>
      </div>
    </div>

    <?php if($emp['passport_no']): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><div><h2>Passport Details</h2></div></div>
      <div class="info-grid">
        <div class="info-item"><div class="info-label">Passport No</div><div class="info-value"><?= fv($emp,'passport_no') ?></div></div>
        <div class="info-item"><div class="info-label">Place of Issue</div><div class="info-value"><?= fv($emp,'place_of_issue') ?></div></div>
        <div class="info-item"><div class="info-label">Date of Issue</div><div class="info-value"><?= $emp['passport_date_of_issue'] ? date('d M Y', strtotime($emp['passport_date_of_issue'])) : '—' ?></div></div>
        <div class="info-item"><div class="info-label">Date of Expiry</div><div class="info-value"><?= $emp['passport_date_of_expiry'] ? date('d M Y', strtotime($emp['passport_date_of_expiry'])) : '—' ?></div></div>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
