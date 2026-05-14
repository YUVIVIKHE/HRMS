<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];
$firstName = explode(' ', $_SESSION['user_name'])[0];
$today = date('Y-m-d');
$month = date('Y-m');

// ── Leave Balance ────────────────────────────────────────────
$leaveBalance = 0;
try {
    $lb = $db->prepare("SELECT COALESCE(SUM(lb.balance),0) FROM leave_balances lb WHERE lb.user_id=?");
    $lb->execute([$uid]);
    $leaveBalance = (float)$lb->fetchColumn();
} catch (Exception $e) { $leaveBalance = 0; }

// ── Pending Leaves ───────────────────────────────────────────
$pendingLeaves = 0;
try {
    $pl = $db->prepare("SELECT COUNT(*) FROM leave_applications WHERE user_id=? AND status='pending'");
    $pl->execute([$uid]);
    $pendingLeaves = (int)$pl->fetchColumn();
} catch (Exception $e) { $pendingLeaves = 0; }

// ── Open Tasks ───────────────────────────────────────────────
$myTasks = 0;
try {
    $mt = $db->prepare("SELECT COUNT(*) FROM task_assignments WHERE assigned_to=? AND status IN ('Pending','In Progress')");
    $mt->execute([$uid]);
    $myTasks = (int)$mt->fetchColumn();
} catch (Exception $e) { $myTasks = 0; }

// ── Days Present This Month ──────────────────────────────────
$monthAttend = 0;
try {
    $ma = $db->prepare("SELECT COUNT(*) FROM attendance_logs WHERE user_id=? AND DATE_FORMAT(log_date,'%Y-%m')=? AND status IN ('present','remote','late')");
    $ma->execute([$uid, $month]);
    $monthAttend = (int)$ma->fetchColumn();
} catch (Exception $e) { $monthAttend = 0; }

// ── Recent Leaves (last 5) ───────────────────────────────────
$myLeaves = [];
try {
    $rl = $db->prepare("
        SELECT la.*, lt.name AS leave_type
        FROM leave_applications la
        LEFT JOIN leave_types lt ON la.leave_type_id = lt.id
        WHERE la.user_id=?
        ORDER BY la.created_at DESC LIMIT 5
    ");
    $rl->execute([$uid]);
    $myLeaves = $rl->fetchAll();
} catch (Exception $e) { $myLeaves = []; }

// ── Recent Tasks (last 5) ────────────────────────────────────
$myTaskList = [];
try {
    $tl = $db->prepare("
        SELECT ta.subtask, ta.to_date, ta.hours, ta.status, p.project_name, p.project_code
        FROM task_assignments ta
        JOIN projects p ON ta.project_id = p.id
        WHERE ta.assigned_to=? AND ta.status IN ('Pending','In Progress')
        ORDER BY ta.to_date ASC LIMIT 5
    ");
    $tl->execute([$uid]);
    $myTaskList = $tl->fetchAll();
} catch (Exception $e) { $myTaskList = []; }

// ── This Month Attendance (last 10) ─────────────────────────
$attendance = [];
try {
    $at = $db->prepare("
        SELECT log_date, clock_in, clock_out, work_seconds, status
        FROM attendance_logs
        WHERE user_id=? AND DATE_FORMAT(log_date,'%Y-%m')=?
        ORDER BY log_date DESC LIMIT 10
    ");
    $at->execute([$uid, $month]);
    $attendance = $at->fetchAll();
} catch (Exception $e) { $attendance = []; }

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
          <div class="stat-value"><?= number_format($leaveBalance,1) ?></div>
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
          <div class="stat-sub">Pending / In progress</div>
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
          <h2>Recent Leaves</h2>
          <a href="my_leaves.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <table>
          <thead><tr><th>Type</th><th>From</th><th>To</th><th>Status</th></tr></thead>
          <tbody>
            <?php if(empty($myLeaves)): ?>
              <tr class="empty-row"><td colspan="4">No leave requests yet</td></tr>
            <?php else: foreach($myLeaves as $l): ?>
            <tr>
              <td class="font-semibold text-sm"><?= htmlspecialchars($l['leave_type'] ?? 'Leave') ?></td>
              <td class="text-sm text-muted"><?= date('d M Y', strtotime($l['start_date'])) ?></td>
              <td class="text-sm text-muted"><?= date('d M Y', strtotime($l['end_date'])) ?></td>
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
            <a href="attendance.php" class="quick-btn">
              <div class="qi" style="background:var(--blue-bg);color:var(--blue);"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
              Attendance
            </a>
            <a href="my_tasks.php" class="quick-btn">
              <div class="qi" style="background:#ede9fe;color:#7c3aed;"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
              My Tasks
            </a>
            <a href="profile.php" class="quick-btn">
              <div class="qi" style="background:var(--yellow-bg);color:var(--yellow);"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
              My Profile
            </a>
          </div>
        </div>

        <!-- Tasks -->
        <div class="table-wrap">
          <div class="table-toolbar">
            <h2>Upcoming Tasks</h2>
            <a href="my_tasks.php" class="btn btn-secondary btn-sm">View All</a>
          </div>
          <table>
            <thead><tr><th>Task</th><th>Project</th><th>Due</th></tr></thead>
            <tbody>
              <?php if(empty($myTaskList)): ?>
                <tr class="empty-row"><td colspan="3">No open tasks</td></tr>
              <?php else: foreach($myTaskList as $t): ?>
              <tr>
                <td class="font-semibold text-sm"><?= htmlspecialchars($t['subtask']) ?></td>
                <td class="text-sm text-muted"><?= htmlspecialchars($t['project_code']) ?></td>
                <td class="text-sm" style="color:<?= $t['to_date']<$today?'var(--red)':'var(--muted)' ?>;"><?= date('d M', strtotime($t['to_date'])) ?></td>
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
        <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th></tr></thead>
        <tbody>
          <?php if(empty($attendance)): ?>
            <tr class="empty-row"><td colspan="5">No attendance records this month</td></tr>
          <?php else: foreach($attendance as $a):
            $statusMap = ['present'=>'badge-green','remote'=>'badge-blue','late'=>'badge-yellow','half_day'=>'badge-yellow','absent'=>'badge-red'];
          ?>
          <tr>
            <td class="text-sm font-semibold"><?= date('D, d M', strtotime($a['log_date'])) ?></td>
            <td class="text-sm"><?= $a['clock_in'] ? date('h:i A', strtotime($a['clock_in'])) : '—' ?></td>
            <td class="text-sm"><?= $a['clock_out'] ? date('h:i A', strtotime($a['clock_out'])) : '<span style="color:var(--yellow)">Ongoing</span>' ?></td>
            <td class="text-sm"><?php
              if ($a['work_seconds']) {
                $h = floor($a['work_seconds']/3600); $m = floor(($a['work_seconds']%3600)/60);
                echo sprintf('%dh %02dm', $h, $m);
              } else echo '—';
            ?></td>
            <td><span class="badge <?= $statusMap[$a['status']]??'badge-gray' ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
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
