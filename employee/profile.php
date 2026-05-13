<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

// Load employee record by matching email to logged-in user
$user = $db->prepare("SELECT email FROM users WHERE id = ?");
$user->execute([$uid]);
$user = $user->fetch();

$emp = null;
$dept = null;
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
.info-item:nth-child(odd):last-child { grid-column: span 2; }
.info-label {
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em;
  color: var(--muted-light); margin-bottom: 4px;
}
.info-value {
  font-size: 13.5px; font-weight: 600; color: var(--text);
}
.info-value.empty { color: var(--muted-light); font-weight: 400; }
</style>
</head>
<body>
<div class="app-shell">

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark" style="background:linear-gradient(135deg,#059669,#10b981);">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <div class="logo-text"><strong>HRMS Portal</strong><span>My Workspace</span></div>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Main</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
    </nav>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">My Work</div>
    <nav class="sidebar-nav">
      <a href="apply_leave.php"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Apply Leave</a>
      <a href="my_leaves.php"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>My Leaves</a>
      <a href="my_tasks.php"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>My Tasks</a>
      <a href="attendance.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Attendance</a>
    </nav>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Account</div>
    <nav class="sidebar-nav">
      <a href="profile.php" class="active"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>My Profile</a>
      <a href="payslip.php"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Payslip</a>
    </nav>
  </div>
  <div class="sidebar-footer">
    <a href="../auth/logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out</a>
  </div>
</aside>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">My Profile</span>
      <span class="page-breadcrumb">View your personal details</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip" style="background:#d1fae5;color:#065f46;">Employee</span>
      <div class="topbar-avatar" style="background:linear-gradient(135deg,#059669,#10b981);"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
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
          <span class="hero-chip"><?= fv($emp,'employee_type') ?></span>
          <span class="hero-chip"><?= ucfirst(fv($emp,'status')) ?></span>
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
        <div class="info-item"><div class="info-label">Direct Manager</div><div class="info-value <?= !$emp['direct_manager_name']?'empty':'' ?>"><?= fv($emp,'direct_manager_name') ?></div></div>
        <div class="info-item"><div class="info-label">Location</div><div class="info-value <?= !$emp['location']?'empty':'' ?>"><?= fv($emp,'location') ?></div></div>
        <div class="info-item"><div class="info-label">Base Location</div><div class="info-value <?= !$emp['base_location']?'empty':'' ?>"><?= fv($emp,'base_location') ?></div></div>
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

    <!-- Passport -->
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
