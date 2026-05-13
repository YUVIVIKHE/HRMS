<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

// Get manager's email → find their employee record → get department
$managerUser = $db->prepare("SELECT email FROM users WHERE id = ?");
$managerUser->execute([$uid]);
$managerUser = $managerUser->fetch();

$managerEmp  = null;
$deptId      = null;
$deptName    = null;
$teamMembers = [];

if ($managerUser) {
    $stmt = $db->prepare("
        SELECT e.*, d.name AS department_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.email = ?
    ");
    $stmt->execute([$managerUser['email']]);
    $managerEmp = $stmt->fetch();

    if ($managerEmp) {
        $deptId   = $managerEmp['department_id'];
        $deptName = $managerEmp['department_name'];

        // All employees in the same department (excluding the manager themselves)
        $stmt = $db->prepare("
            SELECT e.id, e.first_name, e.last_name, e.email, e.phone,
                   e.job_title, e.employee_id, e.employee_type,
                   e.status, e.date_of_joining, e.location,
                   d.name AS department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.department_id = ? AND e.email != ?
            ORDER BY e.first_name ASC
        ");
        $stmt->execute([$deptId, $managerUser['email']]);
        $teamMembers = $stmt->fetchAll();
    }
}

$firstName = explode(' ', $_SESSION['user_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Team – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
</style>
</head>
<body>
<div class="app-shell">

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div class="logo-text"><strong>HRMS Portal</strong><span>Manager Panel</span></div>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Main</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
    </nav>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Team</div>
    <nav class="sidebar-nav">
      <a href="my_team.php" class="active"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>My Team</a>
      <a href="leaves.php"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Leave Requests</a>
      <a href="attendance.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Attendance</a>
      <a href="tasks.php"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Tasks</a>
    </nav>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Account</div>
    <nav class="sidebar-nav">
      <a href="profile.php"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>My Profile</a>
    </nav>
  </div>
  <div class="sidebar-footer">
    <a href="../auth/logout.php"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out</a>
  </div>
</aside>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">My Team</span>
      <span class="page-breadcrumb"><?= $deptName ? htmlspecialchars($deptName).' Department' : 'Your department members' ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if(!$managerEmp): ?>
      <div class="alert alert-warning">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Your employee profile or department has not been configured. Contact your HR administrator.
      </div>
    <?php else: ?>

    <!-- Header stats -->
    <div class="page-header">
      <div class="page-header-text">
        <h1><?= htmlspecialchars($deptName ?? 'My Team') ?></h1>
        <p><?= count($teamMembers) ?> team member<?= count($teamMembers) !== 1 ? 's' : '' ?> in your department</p>
      </div>
      <div class="page-header-actions">
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="teamSearch" placeholder="Search members…" oninput="filterCards()">
        </div>
      </div>
    </div>

    <!-- Stats row -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count($teamMembers) ?></div>
          <div class="stat-label">Total Members</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count(array_filter($teamMembers, fn($m) => strtolower($m['status']) === 'active')) ?></div>
          <div class="stat-label">Active</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue);">
          <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count(array_filter($teamMembers, fn($m) => $m['employee_type'] === 'FTE')) ?></div>
          <div class="stat-label">FTE</div>
        </div>
      </div>
    </div>

    <?php if(empty($teamMembers)): ?>
      <div class="table-wrap">
        <div style="text-align:center;padding:60px 20px;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--muted-light)" stroke-width="1.5" style="display:block;margin:0 auto 12px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;">No team members yet</div>
          <div style="font-size:13px;color:var(--muted);">Employees in the <?= htmlspecialchars($deptName ?? '') ?> department will appear here.</div>
        </div>
      </div>
    <?php else: ?>

    <div class="table-wrap">
      <table id="teamTable">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>Employee</th>
            <th>Job Title</th>
            <th>Type</th>
            <th>Status</th>
            <th>Phone</th>
            <th>Location</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($teamMembers as $i => $m): ?>
          <tr class="team-row"
              data-search="<?= htmlspecialchars(strtolower($m['first_name'].' '.$m['last_name'].' '.($m['job_title']??'').' '.($m['email']??''))) ?>">
            <td class="text-muted text-sm"><?= $i + 1 ?></td>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($m['first_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($m['email']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($m['job_title'] ?: '—') ?></td>
            <td>
              <span class="badge <?= $m['employee_type']==='FTE'?'badge-blue':'badge-yellow' ?>">
                <?= htmlspecialchars($m['employee_type'] ?: '—') ?>
              </span>
            </td>
            <td>
              <span class="badge <?= strtolower($m['status'])==='active'?'badge-green':'badge-red' ?>">
                <?= ucfirst(htmlspecialchars($m['status'])) ?>
              </span>
            </td>
            <td class="text-muted text-sm"><?= htmlspecialchars($m['phone'] ?: '—') ?></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($m['location'] ?: '—') ?></td>
            <td class="text-muted text-sm"><?= $m['date_of_joining'] ? date('M d, Y', strtotime($m['date_of_joining'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div id="noResults" style="display:none;text-align:center;padding:40px;color:var(--muted);font-size:13.5px;">
      No members match your search.
    </div>

    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</div>

<script>
function filterCards() {
  const q    = document.getElementById('teamSearch').value.toLowerCase();
  const rows = document.querySelectorAll('.team-row');
  let visible = 0;
  rows.forEach(row => {
    const match = !q || row.dataset.search.includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  const noRes = document.getElementById('noResults');
  if (noRes) noRes.style.display = visible === 0 && q ? 'block' : 'none';
}
</script>
</body>
</html>
