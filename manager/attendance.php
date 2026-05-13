<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

try {
$todayLog = $db->prepare("SELECT al.*, loc.name AS loc_name FROM attendance_logs al LEFT JOIN attendance_locations loc ON al.location_id = loc.id WHERE al.user_id = ? AND al.log_date = ?");
$todayLog->execute([$uid, $today]);
$todayLog = $todayLog->fetch();

$month = date('Y-m');
$logs  = $db->prepare("SELECT al.*, loc.name AS loc_name FROM attendance_logs al LEFT JOIN attendance_locations loc ON al.location_id = loc.id WHERE al.user_id = ? AND DATE_FORMAT(al.log_date,'%Y-%m') = ? ORDER BY al.log_date DESC");
$logs->execute([$uid, $month]);
$logs = $logs->fetchAll();
} catch (PDOException $e) {
    $todayLog = null; $logs = [];
    error_log('Attendance query error: ' . $e->getMessage());
}

$presentDays  = count(array_filter($logs, fn($l) => in_array($l['status'], ['present','remote','late'])));
$totalSeconds = array_sum(array_column($logs, 'work_seconds'));

if (!function_exists('fmtTime')) {
    function fmtTime($dt) { return $dt ? date('h:i A', strtotime($dt)) : '—'; }
}
if (!function_exists('fmtHrs')) {
    function fmtHrs($sec) {
        if (!$sec) return '—';
        return sprintf('%dh %02dm', floor($sec/3600), floor(($sec%3600)/60));
    }
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
  padding: 14px 28px; border-radius: var(--radius-lg);
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
  background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.3);
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
}
</style>
</head>
<body>
<div class="app-shell">

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Attendance</span>
      <span class="page-breadcrumb"><?= date('l, F j, Y') ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
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
            <span class="status-pill">In: <?= fmtTime($todayLog['clock_in']) ?></span>
          <?php endif; ?>
          <?php if($todayLog && $todayLog['clock_out']): ?>
            <span class="status-pill">Out: <?= fmtTime($todayLog['clock_out']) ?></span>
            <span class="status-pill"><?= fmtHrs($todayLog['work_seconds']) ?></span>
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
          <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count(array_filter($logs, fn($l) => ($l['work_seconds']??0) > 0 && ($l['work_seconds']??0) < 32400)) ?></div>
          <div class="stat-label">Short Days</div>
          <div class="stat-sub">Under 9 hours</div>
        </div>
      </div>
    </div>

    <!-- Monthly log -->
    <div class="table-wrap">
      <div class="table-toolbar"><h2>This Month's Log</h2></div>
      <table>
        <thead>
          <tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Work Hours</th><th>Status</th><th>Location</th><th></th></tr>
        </thead>
        <tbody>
          <?php if(empty($logs)): ?>
            <tr class="empty-row"><td colspan="7">No records this month.</td></tr>
          <?php else: foreach($logs as $l):
            $short = ($l['work_seconds']??0) > 0 && ($l['work_seconds']??0) < 32400;
            $sm = ['present'=>'badge-green','remote'=>'badge-blue','half_day'=>'badge-yellow','late'=>'badge-yellow','absent'=>'badge-red'];
            $regPending = $db->prepare("SELECT id FROM attendance_regularizations WHERE user_id=? AND log_date=? AND status='pending'");
            $regPending->execute([$uid, $l['log_date']]); $regPending = $regPending->fetch();
          ?>
          <tr>
            <td class="font-semibold text-sm"><?= date('D, d M', strtotime($l['log_date'])) ?></td>
            <td class="text-sm"><?= fmtTime($l['clock_in']) ?></td>
            <td class="text-sm"><?= fmtTime($l['clock_out']) ?></td>
            <td><span style="font-size:13px;font-weight:600;color:<?= $short?'var(--red)':'var(--green)' ?>;"><?= fmtHrs($l['work_seconds']) ?></span></td>
            <td><span class="badge <?= $sm[$l['status']]??'badge-gray' ?>"><?= ucfirst(str_replace('_',' ',$l['status'])) ?></span></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($l['loc_name'] ?? '—') ?></td>
            <td>
              <?php if($regPending): ?>
                <span class="badge badge-yellow" style="font-size:11px;">Pending</span>
              <?php else: ?>
                <button type="button" class="btn btn-ghost btn-sm"
                  onclick="openRegModal('<?= $l['log_date'] ?>','<?= date('D, d M Y',strtotime($l['log_date'])) ?>','<?= $l['clock_in']?date('H:i',strtotime($l['clock_in'])):'09:00' ?>','<?= $l['clock_out']?date('H:i',strtotime($l['clock_out'])):'18:00' ?>')">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Regularize
                </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>

<!-- Regularization Modal -->
<div class="modal-overlay" id="regModal">
  <div class="modal" style="max-width:440px;">
    <div class="modal-header">
      <div><h3>Request Regularization</h3><p id="regDateLabel" style="font-size:12.5px;color:var(--muted);margin-top:2px;"></p></div>
      <button class="modal-close" onclick="document.getElementById('regModal').classList.remove('open')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" action="regularizations.php" onsubmit="return validateReg()">
      <input type="hidden" name="action" value="submit_regularization">
      <input type="hidden" name="log_date" id="regDate">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
          <div class="form-group"><label>Clock In Time <span class="req">*</span></label><input type="time" name="req_clock_in" id="regClockIn" class="form-control" required></div>
          <div class="form-group"><label>Clock Out Time <span class="req">*</span></label><input type="time" name="req_clock_out" id="regClockOut" class="form-control" required></div>
        </div>
        <div class="form-group"><label>Reason <span class="req">*</span></label><textarea name="reason" class="form-control" rows="3" placeholder="Explain why regularization is needed…" required></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('regModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<div id="toast"></div>

<script>
function tick() {
  document.getElementById('liveClock').textContent =
    new Date().toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
}
tick(); setInterval(tick, 1000);

function doAction(action) {
  const btn = document.getElementById(action === 'clock_in' ? 'btnIn' : 'btnOut');
  btn.disabled = true; btn.textContent = 'Please wait…';

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
      });
  };

  navigator.geolocation
    ? navigator.geolocation.getCurrentPosition(p => proceed(p.coords.latitude, p.coords.longitude), () => proceed(null, null), {timeout:8000})
    : proceed(null, null);
}

function showToast(msg, color) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.background = color || '#111827'; t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 4000);
}
</script>
</body>
</html>
