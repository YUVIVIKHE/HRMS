<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

// Today's log
$todayLog = $db->prepare("SELECT al.*, loc.name AS loc_name, loc.is_remote FROM attendance_logs al LEFT JOIN attendance_locations loc ON al.location_id = loc.id WHERE al.user_id = ? AND al.log_date = ?");
$todayLog->execute([$uid, $today]);
$todayLog = $todayLog->fetch();

// This month's logs
$month = date('Y-m');
$logs  = $db->prepare("SELECT al.*, loc.name AS loc_name FROM attendance_logs al LEFT JOIN attendance_locations loc ON al.location_id = loc.id WHERE al.user_id = ? AND DATE_FORMAT(al.log_date,'%Y-%m') = ? ORDER BY al.log_date DESC");
$logs->execute([$uid, $month]);
$logs = $logs->fetchAll();

// Stats
$totalDays    = count($logs);
$presentDays  = count(array_filter($logs, fn($l) => in_array($l['status'], ['present','remote','late'])));
$totalSeconds = array_sum(array_column($logs, 'work_seconds'));

function fmtTime($dt) { return $dt ? date('h:i A', strtotime($dt)) : '—'; }
function fmtHrs($sec) {
    if (!$sec) return '—';
    return sprintf('%dh %02dm', floor($sec/3600), floor(($sec%3600)/60));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attendance – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.clock-card {
  background: linear-gradient(135deg, var(--brand) 0%, var(--brand-mid) 100%);
  border-radius: var(--radius-lg);
  padding: 32px 36px;
  color: #fff;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
}
.clock-time { font-size: 42px; font-weight: 800; letter-spacing: -1px; font-variant-numeric: tabular-nums; }
.clock-date { font-size: 14px; opacity: .8; margin-top: 4px; }
.clock-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.btn-clock {
  padding: 14px 28px;
  border-radius: var(--radius-lg);
  font-size: 15px; font-weight: 700;
  border: none; cursor: pointer;
  display: flex; align-items: center; gap: 8px;
  transition: all .2s;
}
.btn-clock svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }
.btn-in  { background: #fff; color: var(--brand); }
.btn-in:hover  { background: #f0f4ff; }
.btn-out { background: rgba(255,255,255,.2); color: #fff; border: 2px solid rgba(255,255,255,.5); }
.btn-out:hover { background: rgba(255,255,255,.3); }
.btn-clock:disabled { opacity: .45; cursor: not-allowed; }
.status-pill {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,.2);
  border: 1px solid rgba(255,255,255,.3);
  border-radius: 20px; padding: 5px 14px;
  font-size: 13px; font-weight: 600;
}
#toast {
  position: fixed; bottom: 28px; right: 28px;
  background: var(--text); color: #fff;
  padding: 14px 20px; border-radius: var(--radius-lg);
  font-size: 13.5px; font-weight: 500;
  box-shadow: var(--shadow-lg);
  display: none; z-index: 999; max-width: 360px;
  animation: slideUp .25s ease;
}
@keyframes slideUp { from { transform: translateY(16px); opacity:0; } to { transform: translateY(0); opacity:1; } }
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
      <a href="attendance.php" class="active"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Attendance</a>
    </nav>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-section-label">Account</div>
    <nav class="sidebar-nav">
      <a href="profile.php"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>My Profile</a>
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
      <span class="page-title">Attendance</span>
      <span class="page-breadcrumb"><?= date('l, F j, Y') ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip" style="background:#d1fae5;color:#065f46;">Employee</span>
      <div class="topbar-avatar" style="background:linear-gradient(135deg,#059669,#10b981);"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <!-- Clock card -->
    <div class="clock-card">
      <div>
        <div class="clock-time" id="liveClock">--:--:--</div>
        <div class="clock-date"><?= date('l, d F Y') ?></div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <?php if($todayLog && $todayLog['clock_in']): ?>
            <span class="status-pill">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/></svg>
              In: <?= fmtTime($todayLog['clock_in']) ?>
            </span>
          <?php endif; ?>
          <?php if($todayLog && $todayLog['clock_out']): ?>
            <span class="status-pill">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Out: <?= fmtTime($todayLog['clock_out']) ?>
            </span>
            <span class="status-pill">
              <?= fmtHrs($todayLog['work_seconds']) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="clock-actions">
        <button class="btn-clock btn-in" id="btnIn"
          <?= ($todayLog && $todayLog['clock_in']) ? 'disabled' : '' ?>
          onclick="doAction('clock_in')">
          <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Clock In
        </button>
        <button class="btn-clock btn-out" id="btnOut"
          <?= (!$todayLog || !$todayLog['clock_in'] || $todayLog['clock_out']) ? 'disabled' : '' ?>
          onclick="doAction('clock_out')">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Clock Out
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $presentDays ?></div>
          <div class="stat-label">Days Present</div>
          <div class="stat-sub">This month</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= fmtHrs($totalSeconds) ?></div>
          <div class="stat-label">Total Hours</div>
          <div class="stat-sub">This month</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow);">
          <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count(array_filter($logs, fn($l) => ($l['work_seconds']??0) > 0 && ($l['work_seconds']??0) < 32400)) ?></div>
          <div class="stat-label">Short Days</div>
          <div class="stat-sub">Under 9 hours</div>
        </div>
      </div>
    </div>

    <!-- Monthly log table -->
    <div class="table-wrap">
      <div class="table-toolbar"><h2>This Month's Log</h2></div>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Work Hours</th>
            <th>Status</th>
            <th>Location</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($logs)): ?>
            <tr class="empty-row"><td colspan="6">No attendance records this month.</td></tr>
          <?php else: foreach($logs as $l):
            $short = ($l['work_seconds']??0) > 0 && ($l['work_seconds']??0) < 32400;
            $statusMap = ['present'=>'badge-green','remote'=>'badge-blue','half_day'=>'badge-yellow','late'=>'badge-yellow','absent'=>'badge-red'];
          ?>
          <tr>
            <td class="font-semibold text-sm"><?= date('D, d M', strtotime($l['log_date'])) ?></td>
            <td class="text-sm"><?= fmtTime($l['clock_in']) ?></td>
            <td class="text-sm"><?= fmtTime($l['clock_out']) ?></td>
            <td>
              <span style="font-size:13px;font-weight:600;color:<?= $short?'var(--red)':'var(--green)' ?>;">
                <?= fmtHrs($l['work_seconds']) ?>
              </span>
            </td>
            <td><span class="badge <?= $statusMap[$l['status']]??'badge-gray' ?>"><?= ucfirst(str_replace('_',' ',$l['status'])) ?></span></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($l['loc_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>

<div id="toast"></div>

<script>
// Live clock
function tick() {
  const now = new Date();
  document.getElementById('liveClock').textContent =
    now.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
}
tick(); setInterval(tick, 1000);

// Clock in/out
function doAction(action) {
  const btn = document.getElementById(action === 'clock_in' ? 'btnIn' : 'btnOut');
  btn.disabled = true;
  btn.textContent = 'Please wait…';

  const proceed = (lat, lng) => {
    const fd = new FormData();
    fd.append('action', action);
    if (lat !== null) { fd.append('lat', lat); fd.append('lng', lng); }

    fetch('../auth/attendance_action.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        showToast(data.msg, data.ok ? '#10b981' : '#ef4444');
        if (data.ok) setTimeout(() => location.reload(), 1800);
        else { btn.disabled = false; btn.textContent = action === 'clock_in' ? 'Clock In' : 'Clock Out'; }
      })
      .catch(() => {
        showToast('Network error. Please try again.', '#ef4444');
        btn.disabled = false;
      });
  };

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => proceed(pos.coords.latitude, pos.coords.longitude),
      ()  => proceed(null, null),
      { timeout: 8000 }
    );
  } else {
    proceed(null, null);
  }
}

function showToast(msg, color) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = color || '#111827';
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 4000);
}
</script>
</body>
</html>
