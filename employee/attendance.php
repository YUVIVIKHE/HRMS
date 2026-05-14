<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

// Today's log
try {
$todayLog = $db->prepare("SELECT al.*, loc.name AS loc_name, loc.is_remote FROM attendance_logs al LEFT JOIN attendance_locations loc ON al.location_id = loc.id WHERE al.user_id = ? AND al.log_date = ?");
$todayLog->execute([$uid, $today]);
$todayLog = $todayLog->fetch();

// This month's logs
$month = date('Y-m');
$logs  = $db->prepare("SELECT al.*, loc.name AS loc_name FROM attendance_logs al LEFT JOIN attendance_locations loc ON al.location_id = loc.id WHERE al.user_id = ? AND DATE_FORMAT(al.log_date,'%Y-%m') = ? ORDER BY al.log_date DESC");
$logs->execute([$uid, $month]);
$logs = $logs->fetchAll();
} catch (PDOException $e) {
    $todayLog = null; $logs = [];
    error_log('Attendance query error: ' . $e->getMessage());
}

// Stats
$totalDays    = count($logs);
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

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Attendance</span>
      <span class="page-breadcrumb"><?= date('l, F j, Y') ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
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
          onclick="location.href='clock_out.php'">
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
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($logs)): ?>
            <tr class="empty-row"><td colspan="7">No attendance records this month.</td></tr>
          <?php else: foreach($logs as $l):
            $short = ($l['work_seconds']??0) > 0 && ($l['work_seconds']??0) < 32400;
            $statusMap = ['present'=>'badge-green','remote'=>'badge-blue','half_day'=>'badge-yellow','late'=>'badge-yellow','absent'=>'badge-red'];
            // Check if regularization already pending for this date
            $regPending = $db->prepare("SELECT id FROM attendance_regularizations WHERE user_id=? AND log_date=? AND status='pending'");
            $regPending->execute([$uid, $l['log_date']]); $regPending = $regPending->fetch();
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
      <div>
        <h3>Request Regularization</h3>
        <p id="regDateLabel" style="font-size:12.5px;color:var(--muted);margin-top:2px;"></p>
      </div>
      <button class="modal-close" onclick="document.getElementById('regModal').classList.remove('open')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" action="my_regularizations.php" onsubmit="return validateReg()">
      <input type="hidden" name="action" value="submit_regularization">
      <input type="hidden" name="log_date" id="regDate">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
          <div class="form-group">
            <label>Clock In Time <span class="req">*</span></label>
            <input type="time" name="req_clock_in" id="regClockIn" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Clock Out Time <span class="req">*</span></label>
            <input type="time" name="req_clock_out" id="regClockOut" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label>Reason <span class="req">*</span></label>
          <textarea name="reason" class="form-control" rows="3" placeholder="Explain why regularization is needed…" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('regModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- Task Progress Modal (shown during clock-out) -->
<div class="modal-overlay" id="taskModal">
  <div class="modal" style="max-width:720px;">
    <div class="modal-header">
      <div>
        <h3>Log Task Progress</h3>
        <p style="font-size:12.5px;color:var(--muted);margin-top:2px;">Select tasks you worked on today and log hours. Total worked: <strong id="totalWorkHrs">0</strong> hrs | Allocated: <strong id="allocatedHrs">0</strong> hrs</p>
      </div>
      <button class="modal-close" onclick="skipAndClockOut()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body" style="max-height:400px;overflow-y:auto;padding:0;">
      <table style="width:100%;font-size:13px;">
        <thead>
          <tr style="background:var(--surface-2);">
            <th style="padding:10px 14px;text-align:left;">Task</th>
            <th style="padding:10px 8px;text-align:center;width:70px;">Assigned</th>
            <th style="padding:10px 8px;text-align:center;width:70px;">Done</th>
            <th style="padding:10px 8px;text-align:center;width:70px;">Remaining</th>
            <th style="padding:10px 8px;text-align:center;width:80px;">Hrs Today</th>
            <th style="padding:10px 8px;text-align:center;width:120px;">Progress</th>
          </tr>
        </thead>
        <tbody id="taskModalBody"></tbody>
      </table>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:space-between;">
      <button type="button" class="btn btn-secondary" onclick="skipAndClockOut()">Skip & Clock Out</button>
      <button type="button" class="btn btn-primary" onclick="confirmClockOut()">Save & Clock Out</button>
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

// Regularization modal
function openRegModal(date, label, clockIn, clockOut) {
  document.getElementById('regDate').value = date;
  document.getElementById('regDateLabel').textContent = label;
  document.getElementById('regClockIn').value = clockIn;
  document.getElementById('regClockOut').value = clockOut;
  document.getElementById('regModal').classList.add('open');
}
document.getElementById('regModal').addEventListener('click', e => { if(e.target===document.getElementById('regModal')) document.getElementById('regModal').classList.remove('open'); });
function validateReg() {
  const ci = document.getElementById('regClockIn').value;
  const co = document.getElementById('regClockOut').value;
  if (!ci || !co) { alert('Both times are required.'); return false; }
  if (ci >= co) { alert('Clock out must be after clock in.'); return false; }
  return true;
}

// Clock in/out — optimized for speed
let pendingLat = null, pendingLng = null;

function doAction(action) {
  const btn = document.getElementById('btnIn');
  btn.disabled = true;
  btn.innerHTML = '<svg viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/></svg> Clocking in…';

  // Start location fetch and API call in parallel for speed
  let lat = null, lng = null;
  const locationPromise = new Promise(resolve => {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        pos => { lat = pos.coords.latitude; lng = pos.coords.longitude; resolve(); },
        () => resolve(),
        {enableHighAccuracy: true, timeout: 5000, maximumAge: 0}
      );
    } else resolve();
  });

  locationPromise.then(() => {
    const fd = new FormData();
    fd.append('action', 'clock_in');
    if (lat !== null) { fd.append('lat', lat); fd.append('lng', lng); }
    fetch('../auth/attendance_action.php', {method:'POST', body:fd})
      .then(r => r.json())
      .then(data => {
        showToast(data.msg, data.ok ? '#10b981' : '#ef4444');
        if (data.ok) setTimeout(() => location.reload(), 1000);
        else { btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> Clock In'; }
      })
      .catch(() => { showToast('Network error.', '#ef4444'); btn.disabled = false; btn.innerHTML = 'Clock In'; });
  });
}

function showTaskModal(tasks, workHours) {
  const modal = document.getElementById('taskModal');
  const tbody = document.getElementById('taskModalBody');
  document.getElementById('totalWorkHrs').textContent = workHours.toFixed(1);
  tbody.innerHTML = '';

  tasks.forEach(t => {
    const remaining = Math.max(0, (parseFloat(t.assigned_hours) - parseFloat(t.utilized_hours)).toFixed(1));
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" class="task-check" data-id="${t.id}" style="width:15px;height:15px;accent-color:var(--brand);" onchange="toggleTaskRow(this)">
          <div>
            <div style="font-size:13px;font-weight:700;color:var(--text);">${escHtml(t.subtask)}</div>
            <div style="font-size:11.5px;color:var(--muted);">${escHtml(t.project_name)} (${escHtml(t.project_code)})</div>
          </div>
        </label>
      </td>
      <td style="text-align:center;font-size:12.5px;color:var(--muted);">${parseFloat(t.assigned_hours).toFixed(1)}</td>
      <td style="text-align:center;font-size:12.5px;color:var(--green-text);font-weight:600;">${parseFloat(t.utilized_hours).toFixed(1)}</td>
      <td style="text-align:center;font-size:12.5px;color:var(--brand);font-weight:600;">${remaining}</td>
      <td>
        <input type="number" class="form-control task-hours" data-id="${t.id}" min="0.5" max="${workHours}" step="0.5" value="" placeholder="0" style="width:70px;font-size:12px;padding:5px 8px;text-align:center;" disabled>
      </td>
      <td>
        <select class="form-control task-progress" data-id="${t.id}" style="font-size:12px;padding:5px 8px;width:auto;" disabled>
          <option value="In Progress" ${t.status==='In Progress'?'selected':''}>In Progress</option>
          <option value="Completed">Completed</option>
          <option value="On Hold">On Hold</option>
          <option value="Pending" ${t.status==='Pending'?'selected':''}>Pending</option>
        </select>
      </td>
    `;
    tbody.appendChild(tr);
  });

  modal.classList.add('open');
  document.getElementById('btnOut').disabled = false;
  document.getElementById('btnOut').textContent = 'Clock Out';
}

function toggleTaskRow(checkbox) {
  const id = checkbox.dataset.id;
  const row = checkbox.closest('tr');
  const hrsInput = row.querySelector('.task-hours');
  const progSelect = row.querySelector('.task-progress');
  hrsInput.disabled = !checkbox.checked;
  progSelect.disabled = !checkbox.checked;
  if (checkbox.checked) { hrsInput.focus(); } else { hrsInput.value = ''; }
  updateAllocated();
}

function updateAllocated() {
  let total = 0;
  document.querySelectorAll('.task-hours').forEach(inp => { total += parseFloat(inp.value) || 0; inp.addEventListener('input', updateAllocated); });
  const el = document.getElementById('allocatedHrs');
  if (el) el.textContent = total.toFixed(1);
}
document.addEventListener('input', e => { if(e.target.classList.contains('task-hours')) updateAllocated(); });

function confirmClockOut() {
  const progress = [];
  document.querySelectorAll('.task-check:checked').forEach(chk => {
    const id = chk.dataset.id;
    const hrs = parseFloat(document.querySelector(`.task-hours[data-id="${id}"]`).value) || 0;
    const prog = document.querySelector(`.task-progress[data-id="${id}"]`).value;
    if (hrs > 0) progress.push({task_id: parseInt(id), hours: hrs, progress: prog});
  });
  document.getElementById('taskModal').classList.remove('open');
  performClockOut(progress);
}

function skipAndClockOut() {
  document.getElementById('taskModal').classList.remove('open');
  performClockOut([]);
}

function performClockOut(taskProgress) {
  const btn = document.getElementById('btnOut');
  btn.disabled = true;
  btn.textContent = 'Clocking out…';

  const fd = new FormData();
  fd.append('action', 'clock_out');
  if (pendingLat !== null) { fd.append('lat', pendingLat); fd.append('lng', pendingLng); }
  fd.append('task_progress', JSON.stringify(taskProgress));

  fetch('../auth/attendance_action.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      showToast(data.msg, data.ok ? '#10b981' : '#ef4444');
      if (data.ok) setTimeout(() => location.reload(), 1800);
      else { btn.disabled = false; btn.textContent = 'Clock Out'; }
    })
    .catch(() => { showToast('Network error.', '#ef4444'); btn.disabled = false; btn.textContent = 'Clock Out'; });
}

function escHtml(str) {
  const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
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
