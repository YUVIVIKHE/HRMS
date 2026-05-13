<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

$leaveBalance  = 12;
$pendingLeaves = 1;
$myTasks       = 5;
$monthAttend   = 20;
$myLeaves      = [];
$myTaskList    = [];
$attendance    = [];
$firstName     = explode(' ', $_SESSION['user_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Dashboard – HRMS Portal</title>
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
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:1000px){.two-col{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="app-shell">

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">My Dashboard</span>
      <span class="page-breadcrumb"><?= date('l, F j, Y') ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Hello, <?= htmlspecialchars($firstName) ?> 👋</h1>
        <p>Here's your personal overview for today.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5;color:#059669;">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $leaveBalance ?></div>
          <div class="stat-label">Leave Balance</div>
          <div class="stat-sub">Days remaining</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $pendingLeaves ?></div>
          <div class="stat-label">Pending Leaves</div>
          <div class="stat-sub">Awaiting approval</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $myTasks ?></div>
          <div class="stat-label">Open Tasks</div>
          <div class="stat-sub">In progress</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $monthAttend ?></div>
          <div class="stat-label">Days Present</div>
          <div class="stat-sub">This month</div>
        </div>
      </div>
    </div>

    <div class="two-col">
      <!-- Leave History -->
      <div class="table-wrap">
        <div class="table-toolbar">
          <h2>Leave History</h2>
          <a href="my_leaves.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <table>
          <thead><tr><th>Type</th><th>From</th><th>To</th><th>Status</th></tr></thead>
          <tbody>
            <?php if(empty($myLeaves)): ?>
              <tr class="empty-row"><td colspan="4">No leave requests yet</td></tr>
            <?php else: foreach($myLeaves as $l): ?>
            <tr>
              <td class="font-semibold"><?= htmlspecialchars($l['leave_type']) ?></td>
              <td class="text-sm text-muted"><?= date('M d, Y',strtotime($l['start_date'])) ?></td>
              <td class="text-sm text-muted"><?= date('M d, Y',strtotime($l['end_date'])) ?></td>
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
              <div class="qi" style="background:#d1fae5;color:#059669;"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
              My Leaves
            </a>
            <a href="attendance.php?action=checkin" class="quick-btn">
              <div class="qi" style="background:var(--blue-bg);color:var(--blue);"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
              Check In
            </a>
            <a href="my_tasks.php" class="quick-btn">
              <div class="qi" style="background:#ede9fe;color:#7c3aed;"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
              My Tasks
            </a>
            <a href="payslip.php" class="quick-btn">
              <div class="qi" style="background:var(--yellow-bg);color:var(--yellow);"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
              Payslip
            </a>
          </div>
        </div>

        <!-- Tasks -->
        <div class="table-wrap">
          <div class="table-toolbar">
            <h2>My Tasks</h2>
            <a href="my_tasks.php" class="btn btn-secondary btn-sm">View All</a>
          </div>
          <table>
            <thead><tr><th>Task</th><th>Due</th><th>Priority</th></tr></thead>
            <tbody>
              <?php if(empty($myTaskList)): ?>
                <tr class="empty-row"><td colspan="3">No tasks assigned</td></tr>
              <?php else: foreach($myTaskList as $t): ?>
              <tr>
                <td class="font-semibold text-sm"><?= htmlspecialchars($t['title']) ?></td>
                <td class="text-sm text-muted"><?= date('M d',strtotime($t['due_date'])) ?></td>
                <td><span class="badge badge-<?= strtolower($t['priority'])==='high'?'red':(strtolower($t['priority'])==='medium'?'yellow':'green') ?>"><?= $t['priority'] ?></span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Attendance -->
    <div class="table-wrap" style="margin-top:16px;">
      <div class="table-toolbar">
        <h2>This Month's Attendance</h2>
        <a href="attendance.php" class="btn btn-secondary btn-sm">Full Log</a>
      </div>
      <table>
        <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th></tr></thead>
        <tbody>
          <?php if(empty($attendance)): ?>
            <tr class="empty-row"><td colspan="4">No attendance records this month</td></tr>
          <?php else: foreach($attendance as $a): ?>
          <tr>
            <td><?= date('D, M d',strtotime($a['day'])) ?></td>
            <td><?= $a['check_in']?date('h:i A',strtotime($a['check_in'])):'—' ?></td>
            <td><?= $a['check_out']?date('h:i A',strtotime($a['check_out'])):'<span style="color:var(--yellow)">Ongoing</span>' ?></td>
            <td><span class="badge badge-green">Present</span></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>
</body>
</html>
