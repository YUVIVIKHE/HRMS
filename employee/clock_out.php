<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');
date_default_timezone_set('Asia/Kolkata');

// Check if clocked in today
$todayLog = $db->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND log_date=?");
$todayLog->execute([$uid, $today]);
$todayLog = $todayLog->fetch();

if (!$todayLog || !$todayLog['clock_in'] || $todayLog['clock_out']) {
    header("Location: attendance.php"); exit;
}

$clockInTime = date('h:i A', strtotime($todayLog['clock_in']));
$workSec = time() - strtotime($todayLog['clock_in']);
$workHrs = round($workSec / 3600, 1);
$workHrsH = floor($workSec / 3600);
$workHrsM = floor(($workSec % 3600) / 60);

// Get all active tasks for today (across all projects)
$allTasks = $db->prepare("
    SELECT ta.id, ta.subtask, ta.hours AS assigned_hours, ta.project_id, ta.status,
           p.project_name, p.project_code,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = ? AND ta.status != 'Completed'
      AND ta.from_date <= ? AND ta.to_date >= ?
    ORDER BY p.project_name, ta.subtask
");
$allTasks->execute([$uid, $today, $today]);
$allTasks = $allTasks->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clock Out – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.co-page{max-width:640px;margin:0 auto;padding:24px 16px;}
.co-banner{background:linear-gradient(135deg,var(--brand),var(--brand-mid));border-radius:12px;padding:20px 24px;color:#fff;text-align:center;margin-bottom:24px;}
.co-banner h1{font-size:20px;font-weight:800;margin:0 0 4px;}
.co-banner .time{font-size:28px;font-weight:800;letter-spacing:-1px;margin:8px 0;}
.co-banner .meta{font-size:13px;opacity:.85;}
.task-item{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;transition:all .15s;}
.task-item.active{border-color:var(--brand);background:var(--brand-light);}
.task-item label{display:flex;align-items:flex-start;gap:10px;cursor:pointer;}
.task-item input[type="checkbox"]{width:18px;height:18px;accent-color:var(--brand);margin-top:2px;flex-shrink:0;}
.task-item .hrs-row{display:flex;gap:12px;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:none;}
.task-item.active .hrs-row{display:flex;}
.hrs-field{width:80px;font-size:14px;font-weight:700;text-align:center;padding:8px;border:1.5px solid var(--border);border-radius:8px;background:#fff;}
.hrs-field:focus{border-color:var(--brand);outline:none;box-shadow:0 0 0 3px var(--brand-light);}
.co-footer{position:sticky;bottom:0;background:var(--surface);border-top:1px solid var(--border);padding:16px;border-radius:12px;box-shadow:0 -4px 16px rgba(0,0,0,.05);margin-top:20px;display:flex;align-items:center;justify-content:space-between;}
.hr-warn{font-size:12px;color:var(--red);font-weight:600;margin-top:4px;display:none;}
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Clock Out</span>
      <span class="page-breadcrumb">Log task progress</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">
    <div class="co-page">

      <!-- Banner -->
      <div class="co-banner">
        <h1>Clock Out</h1>
        <div class="time" id="liveTime">--:--:--</div>
        <div class="meta">
          Clocked in at <?= $clockInTime ?> · Worked: <strong><?= $workHrsH ?>h <?= $workHrsM ?>m</strong> (~<?= $workHrs ?> hrs)
        </div>
      </div>

      <?php if(empty($allTasks)): ?>
        <div class="card"><div class="card-body" style="text-align:center;padding:32px 20px;">
          <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No active tasks for today</div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:16px;">Clock out directly.</div>
          <button class="btn btn-primary" id="btnDirect" onclick="clockOut([])">Clock Out Now</button>
          <a href="attendance.php" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
        </div></div>
      <?php else: ?>

      <!-- Instructions -->
      <div style="font-size:13px;color:var(--muted);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--brand)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        Select tasks you worked on, enter hours (max <?= $workHrs ?> hrs total). Per-task max = remaining hrs for that task.
      </div>

      <!-- Task List -->
      <div id="taskList">
        <?php foreach($allTasks as $t):
          $remaining = max(0, round((float)$t['assigned_hours'] - (float)$t['utilized_hours'], 1));
          $maxPerTask = min($remaining, $workHrs);
        ?>
        <div class="task-item" id="ti-<?= $t['id'] ?>">
          <label>
            <input type="checkbox" data-id="<?= $t['id'] ?>" onchange="toggleItem(this)">
            <div style="flex:1;">
              <div style="font-size:14px;font-weight:700;color:var(--text);"><?= htmlspecialchars($t['subtask']) ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px;">
                <?= htmlspecialchars($t['project_code']) ?> · <?= htmlspecialchars($t['project_name']) ?>
              </div>
              <div style="font-size:11.5px;color:var(--muted);margin-top:2px;">
                Assigned: <?= number_format($t['assigned_hours'],1) ?>h · Done: <?= number_format($t['utilized_hours'],1) ?>h · <span style="color:var(--brand);font-weight:600;">Remaining: <?= $remaining ?>h</span>
              </div>
            </div>
          </label>
          <div class="hrs-row">
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--muted);display:block;margin-bottom:3px;">Hours today</label>
              <input type="number" class="hrs-field" id="hrs-<?= $t['id'] ?>" data-id="<?= $t['id'] ?>" data-max="<?= $maxPerTask ?>" min="0.5" max="<?= $maxPerTask ?>" step="0.5" placeholder="0" oninput="validateHrs(this)">
              <div class="hr-warn" id="warn-<?= $t['id'] ?>"></div>
            </div>
            <div>
              <label style="font-size:11px;font-weight:600;color:var(--muted);display:block;margin-bottom:3px;">Progress</label>
              <select class="form-control" id="prog-<?= $t['id'] ?>" style="font-size:12px;padding:8px 10px;">
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="On Hold">On Hold</option>
              </select>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Footer -->
      <div class="co-footer">
        <div>
          <div style="font-size:13px;color:var(--muted);">
            Logged: <strong id="totalLogged" style="color:var(--brand);font-size:16px;">0.0</strong> / <?= $workHrs ?> hrs max
          </div>
          <div id="totalWarn" style="display:none;font-size:12px;color:var(--red);font-weight:600;margin-top:2px;"></div>
        </div>
        <div style="display:flex;gap:8px;">
          <a href="attendance.php" class="btn btn-secondary btn-sm">Cancel</a>
          <button class="btn btn-primary" id="btnClockOut" onclick="submitClockOut()">Clock Out & Save</button>
        </div>
      </div>

      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<div id="toast" style="position:fixed;bottom:28px;right:28px;background:#111827;color:#fff;padding:14px 20px;border-radius:10px;font-size:13.5px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.15);display:none;z-index:999;max-width:360px;"></div>

<script>
const MAX_WORK_HRS = <?= $workHrs ?>;

// Live time
function tick(){const n=new Date();document.getElementById('liveTime').textContent=n.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit'});}
tick();setInterval(tick,1000);

function toggleItem(chk) {
  const id = chk.dataset.id;
  const item = document.getElementById('ti-' + id);
  item.classList.toggle('active', chk.checked);
  if (!chk.checked) { document.getElementById('hrs-' + id).value = ''; }
  recalcTotal();
}

function validateHrs(inp) {
  const id = inp.dataset.id;
  const max = parseFloat(inp.dataset.max);
  const val = parseFloat(inp.value) || 0;
  const warn = document.getElementById('warn-' + id);

  if (val > max) {
    warn.textContent = 'Max ' + max + 'h remaining for this task';
    warn.style.display = 'block';
    inp.style.borderColor = 'var(--red)';
  } else {
    warn.style.display = 'none';
    inp.style.borderColor = '';
  }
  recalcTotal();
}

function recalcTotal() {
  let total = 0;
  let hasError = false;
  document.querySelectorAll('.hrs-field').forEach(inp => {
    const val = parseFloat(inp.value) || 0;
    const max = parseFloat(inp.dataset.max);
    total += val;
    if (val > max) hasError = true;
  });

  document.getElementById('totalLogged').textContent = total.toFixed(1);
  const totalWarn = document.getElementById('totalWarn');
  const btn = document.getElementById('btnClockOut');

  if (total > MAX_WORK_HRS) {
    totalWarn.textContent = '⚠ Total ' + total.toFixed(1) + ' hrs exceeds your worked time of ' + MAX_WORK_HRS + ' hrs!';
    totalWarn.style.display = 'block';
    btn.disabled = true;
  } else if (hasError) {
    totalWarn.textContent = '⚠ Some tasks exceed their remaining hours limit.';
    totalWarn.style.display = 'block';
    btn.disabled = true;
  } else {
    totalWarn.style.display = 'none';
    btn.disabled = false;
  }
}

function submitClockOut() {
  const progress = [];
  document.querySelectorAll('.task-item.active').forEach(item => {
    const chk = item.querySelector('input[type="checkbox"]');
    const id = chk.dataset.id;
    const hrs = parseFloat(document.getElementById('hrs-' + id).value) || 0;
    const prog = document.getElementById('prog-' + id).value;
    if (hrs > 0) progress.push({task_id: parseInt(id), hours: hrs, progress: prog});
  });
  clockOut(progress);
}

function clockOut(progress) {
  const btn = document.getElementById('btnClockOut') || document.getElementById('btnDirect');
  if (btn) { btn.disabled = true; btn.textContent = 'Clocking out…'; }

  const doIt = (lat, lng) => {
    const fd = new FormData();
    fd.append('action', 'clock_out');
    if (lat !== null) { fd.append('lat', lat); fd.append('lng', lng); }
    fd.append('task_progress', JSON.stringify(progress));

    fetch('../auth/attendance_action.php', {method:'POST', body:fd})
      .then(r => r.json())
      .then(data => {
        showToast(data.msg, data.ok ? '#10b981' : '#ef4444');
        if (data.ok) setTimeout(() => location.href = 'attendance.php', 1000);
        else if (btn) { btn.disabled = false; btn.textContent = 'Clock Out & Save'; }
      })
      .catch(() => { showToast('Network error.', '#ef4444'); if(btn){btn.disabled=false;btn.textContent='Clock Out & Save';} });
  };

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => doIt(pos.coords.latitude, pos.coords.longitude),
      () => doIt(null, null),
      {enableHighAccuracy: true, timeout: 5000, maximumAge: 0}
    );
  } else doIt(null, null);
}

function showToast(msg, color) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.background = color || '#111827';
  t.style.display = 'block'; setTimeout(() => t.style.display = 'none', 4000);
}
</script>
</body>
</html>
