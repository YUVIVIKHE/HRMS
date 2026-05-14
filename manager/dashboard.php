<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$myTeam       = 0;
$pendingLvs   = 5;
$todayPresent = 12;
$openTasks    = 8;
$teamLeaves   = [];
$teamMembers  = [];
try {
    $myTeam      = (int)$db->query("SELECT COUNT(*) FROM users u INNER JOIN employees e ON e.email=u.email WHERE u.manager_id=$uid AND u.role='employee'")->fetchColumn();
    $teamMembers = $db->query("SELECT u.id, u.name, u.email, u.status FROM users u INNER JOIN employees e ON e.email=u.email WHERE u.manager_id=$uid AND u.role='employee' LIMIT 8")->fetchAll();
} catch(Exception $e) {}
$firstName = explode(' ', $_SESSION['user_name'])[0];

// Calendar data
$calMonth = (int)($_GET['cal_month'] ?? date('n'));
$calYear  = (int)($_GET['cal_year']  ?? date('Y'));
if ($calMonth < 1 || $calMonth > 12) $calMonth = (int)date('n');
if ($calYear < 2000 || $calYear > 2100) $calYear = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manager Dashboard – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.quick-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:16px;}
.quick-btn{
  display:flex;flex-direction:column;align-items:center;gap:8px;
  padding:18px 12px;border-radius:10px;
  border:1px solid var(--border);background:var(--surface-2);
  text-decoration:none;color:var(--text-2);font-size:13px;font-weight:600;
  text-align:center;transition:all .15s;
}
.quick-btn:hover{background:var(--brand-light);color:var(--brand);border-color:var(--brand);}
.quick-btn .qi{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;margin-bottom:2px;}
.quick-btn .qi svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;}
.two-col{display:grid;grid-template-columns:1.3fr 1fr;gap:16px;}
@media(max-width:1100px){.two-col{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="app-shell">

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Manager Dashboard</span>
      <span class="page-breadcrumb"><?= date('l, F j, Y') ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Welcome back, <?= htmlspecialchars($firstName) ?></h1>
        <p>Your team overview for today.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue);">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $myTeam ?></div>
          <div class="stat-label">Team Size</div>
          <div class="stat-sub">Direct reports</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow);">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $pendingLvs ?></div>
          <div class="stat-label">Pending Leaves</div>
          <div class="stat-sub">Action needed</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $todayPresent ?></div>
          <div class="stat-label">Present Today</div>
          <div class="stat-sub">Checked in</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $openTasks ?></div>
          <div class="stat-label">Open Tasks</div>
          <div class="stat-sub">In progress</div>
        </div>
      </div>
    </div>

    <div class="two-col">
      <!-- Team Leave Requests -->
      <div class="table-wrap">
        <div class="table-toolbar">
          <h2>Team Leave Requests</h2>
          <a href="my_leaves.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <table>
          <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Status</th></tr></thead>
          <tbody>
            <?php if(empty($teamLeaves)): ?>
              <tr class="empty-row"><td colspan="5">No pending leave requests</td></tr>
            <?php else: foreach($teamLeaves as $l): ?>
            <tr>
              <td class="font-semibold"><?= htmlspecialchars($l['name']) ?></td>
              <td class="text-muted text-sm"><?= htmlspecialchars($l['leave_type']) ?></td>
              <td class="text-sm"><?= date('M d',strtotime($l['start_date'])) ?></td>
              <td class="text-sm"><?= date('M d',strtotime($l['end_date'])) ?></td>
              <td><span class="badge badge-<?= $l['status']==='approved'?'green':($l['status']==='rejected'?'red':'yellow') ?>"><?= ucfirst($l['status']) ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px;">
        <!-- Quick Actions -->
        <div class="card">
          <div class="card-header"><h2>Quick Actions</h2></div>
          <div class="quick-grid">
            <a href="my_leaves.php" class="quick-btn">
              <div class="qi" style="background:var(--green-bg);color:var(--green);"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
              Approve Leaves
            </a>
            <a href="tasks.php?action=new" class="quick-btn">
              <div class="qi" style="background:#ede9fe;color:#7c3aed;"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
              Assign Task
            </a>
            <a href="attendance.php" class="quick-btn">
              <div class="qi" style="background:var(--blue-bg);color:var(--blue);"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
              Attendance
            </a>
            <a href="my_team.php" class="quick-btn">
              <div class="qi" style="background:var(--brand-light);color:var(--brand);"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
              View Team
            </a>
          </div>
        </div>

        <!-- Team Members -->
        <div class="table-wrap">
          <div class="table-toolbar">
            <h2>My Team</h2>
            <a href="my_team.php" class="btn btn-secondary btn-sm">View All</a>
          </div>
          <table>
            <thead><tr><th>Member</th><th>Status</th></tr></thead>
            <tbody>
              <?php if(empty($teamMembers)): ?>
                <tr class="empty-row"><td colspan="2">No team members assigned</td></tr>
              <?php else: foreach($teamMembers as $m): ?>
              <tr>
                <td>
                  <div class="td-user">
                    <div class="td-avatar"><?= strtoupper(substr($m['name'],0,1)) ?></div>
                    <div>
                      <div class="td-name"><?= htmlspecialchars($m['name']) ?></div>
                      <div class="td-sub"><?= htmlspecialchars($m['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="badge badge-<?= strtolower($m['status'])==='active'?'green':'red' ?>"><?= ucfirst($m['status']) ?></span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Calendar -->
    <div style="margin-top:20px;">
      <?php include __DIR__ . '/../shared/calendar_widget.php'; ?>
    </div>

  </div>
</div>
</div>
</body>
</html>
