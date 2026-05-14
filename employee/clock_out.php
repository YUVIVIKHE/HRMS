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

// Get projects with active tasks for this employee
$projects = $db->prepare("
    SELECT DISTINCT p.id, p.project_name, p.project_code
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = ? AND ta.status != 'Completed'
      AND ta.from_date <= ? AND ta.to_date >= ?
    ORDER BY p.project_name
");
$projects->execute([$uid, $today, $today]);
$projects = $projects->fetchAll();

// Get all active tasks grouped by project
$allTasks = $db->prepare("
    SELECT ta.id, ta.subtask, ta.hours AS assigned_hours, ta.project_id, ta.status,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
    FROM task_assignments ta
    WHERE ta.assigned_to = ? AND ta.status != 'Completed'
      AND ta.from_date <= ? AND ta.to_date >= ?
    ORDER BY ta.subtask
");
$allTasks->execute([$uid, $today, $today]);
$allTasks = $allTasks->fetchAll();

// Group tasks by project
$tasksByProject = [];
foreach ($allTasks as $t) {
    $tasksByProject[$t['project_id']][] = $t;
}
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
.clockout-page { max-width:700px; margin:0 auto; padding:32px 20px; }
.clockout-header { text-align:center; margin-bottom:28px; }
.clockout-header h1 { font-size:22px; font-weight:800; color:var(--text); margin-bottom:6px; }
.clockout-header p { font-size:13.5px; color:var(--muted); }
.time-badge { display:inline-flex; align-items:center; gap:6px; background:var(--brand-light); color:var(--brand); padding:8px 16px; border-radius:20px; font-size:13px; font-weight:700; margin-top:10px; }
.task-entry { background:var(--surface); border:1.5px solid var(--border); border-radius:10px; padding:16px; margin-bottom:12px; transition:border-color .15s; }
.task-entry.selected { border-color:var(--brand); background:var(--brand-light); }
.task-entry-header { display:flex; align-items:center; gap:10px; cursor:pointer; }
.task-entry-header input[type="checkbox"] { width:18px; height:18px; accent-color:var(--brand); cursor:pointer; }
.task-entry-body { margin-top:12px; padding-top:12px; border-top:1px solid var(--border); display:none; }
.task-entry.selected .task-entry-body { display:block; }
.hrs-input { width:90px; font-size:14px; font-weight:700; text-align:center; padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; background:var(--surface); }
.hrs-input:focus { border-color:var(--brand); outline:none; box-shadow:0 0 0 3px var(--brand-light); }
.progress-select { font-size:13px; padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; background:var(--surface); }
.summary-bar { position:sticky; bottom:0; background:var(--surface); border-top:1px solid var(--border); padding:16px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px; border-radius:12px; box-shadow:0 -4px 20px rgba(0,0,0,.06); margin-top:24px; }
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Clock Out</span>
      <span class="page-breadcrumb">Log your task progress</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">
    <div class="clockout-page">

      <div class="clockout-header">
        <h1>Log Task Progress & Clock Out</h1>
        <p>Select the project and tasks you worked on today, enter hours, then clock out.</p>
        <div class="time-badge">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Clocked in at <?= $clockInTime ?> · ~<?= $workHrs ?> hrs worked today
        </div>
      </div>

      <?php if(empty($projects)): ?>
        <div class="card"><div class="card-body" style="text-align:center;padding:40px 20px;">
          <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No active tasks for today</div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:16px;">You can clock out directly without logging task progress.</div>
          <button class="btn btn-primary" onclick="clockOutDirect()">Clock Out Now</button>
          <a href="attendance.php" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
        </div></div>
      <?php else: ?>

      <!-- Project Selection -->
      <div class="form-group" style="margin-bottom:20px;">
        <label style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:8px;display:block;">Select Project</label>
        <select id="projectSelect" class="form-control" style="font-size:14px;padding:10px 14px;max-width:400px;" onchange="filterTasks()">
          <option value="">— Choose a project —</option>
          <?php foreach($projects as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tasks List -->
      <div id="tasksContainer" style="display:none;">
        <label style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px;display:block;">Select Tasks & Enter Hours</label>

        <?php foreach($projects as $p): ?>
        <div class="project-tasks" data-project="<?= $p['id'] ?>" style="display:none;">
          <?php if(!empty($tasksByProject[$p['id']])): ?>
            <?php foreach($tasksByProject[$p['id']] as $t):
              $remaining = max(0, round($t['assigned_hours'] - $t['utilized_hours'], 1));
            ?>
            <div class="task-entry" id="task-<?= $t['id'] ?>">
              <div class="task-entry-header" onclick="toggleTask(<?= $t['id'] ?>)">
                <input type="checkbox" id="chk-<?= $t['id'] ?>" data-id="<?= $t['id'] ?>" onchange="toggleTask(<?= $t['id'] ?>)" onclick="event.stopPropagation()">
                <div style="flex:1;">
                  <div style="font-size:14px;font-weight:700;color:var(--text);"><?= htmlspecialchars($t['subtask']) ?></div>
                  <div style="font-size:12px;color:var(--muted);margin-top:2px;">
                    Assigned: <?= number_format($t['assigned_hours'],1) ?> hrs · Done: <?= number_format($t['utilized_hours'],1) ?> hrs · Remaining: <?= $remaining ?> hrs
                  </div>
                </div>
              </div>
              <div class="task-entry-body">
                <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                  <div>
                    <label style="font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px;">Hours Worked Today</label>
                    <input type="number" class="hrs-input" id="hrs-<?= $t['id'] ?>" data-id="<?= $t['id'] ?>" min="0.5" max="<?= $workHrs ?>" step="0.5" placeholder="0" oninput="updateTotal()">
                  </div>
                  <div>
                    <label style="font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px;">Progress</label>
                    <select class="progress-select" id="prog-<?= $t['id'] ?>" data-id="<?= $t['id'] ?>">
                      <option value="In Progress">In Progress</option>
                      <option value="Completed">Completed</option>
                      <option value="On Hold">On Hold</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px;">No active tasks in this project for today.</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Summary & Action -->
      <div class="summary-bar" id="summaryBar">
        <div>
          <div style="font-size:13px;color:var(--muted);">Total hours logged: <strong id="totalLogged" style="color:var(--brand);font-size:15px;">0.0</strong></div>
        </div>
        <div style="display:flex;gap:10px;">
          <a href="attendance.php" class="btn btn-secondary">Cancel</a>
          <button class="btn btn-primary" id="btnClockOut" onclick="submitClockOut()">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Clock Out & Save
          </button>
        </div>
      </div>

      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<div id="toast" style="position:fixed;bottom:28px;right:28px;background:#111827;color:#fff;padding:14px 20px;border-radius:10px;font-size:13.5px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.15);display:none;z-index:999;max-width:360px;"></div>

<script>
function filterTasks() {
  const pid = document.getElementById('projectSelect').value;
  const container = document.getElementById('tasksContainer');
  document.querySelectorAll('.project-tasks').forEach(el => el.style.display = 'none');
  if (pid) {
    container.style.display = 'block';
    const target = document.querySelector(`.project-tasks[data-project="${pid}"]`);
    if (target) target.style.display = 'block';
  } else {
    container.style.display = 'none';
  }
}

function toggleTask(id) {
  const chk = document.getElementById('chk-' + id);
  const entry = document.getElementById('task-' + id);
  // If triggered from the header click (not checkbox itself), toggle checkbox
  if (event && event.target !== chk) chk.checked = !chk.checked;
  entry.classList.toggle('selected', chk.checked);
  if (!chk.checked) {
    document.getElementById('hrs-' + id).value = '';
  }
  updateTotal();
}

function updateTotal() {
  let total = 0;
  document.querySelectorAll('.hrs-input').forEach(inp => {
    total += parseFloat(inp.value) || 0;
  });
  document.getElementById('totalLogged').textContent = total.toFixed(1);
}

function submitClockOut() {
  const btn = document.getElementById('btnClockOut');
  btn.disabled = true;
  btn.textContent = 'Clocking out…';

  // Gather task progress
  const progress = [];
  document.querySelectorAll('input[type="checkbox"]:checked').forEach(chk => {
    const id = chk.dataset.id;
    if (!id) return;
    const hrs = parseFloat(document.getElementById('hrs-' + id)?.value) || 0;
    const prog = document.getElementById('prog-' + id)?.value || 'In Progress';
    if (hrs > 0) progress.push({task_id: parseInt(id), hours: hrs, progress: prog});
  });

  // Get location then clock out
  const doClockOut = (lat, lng) => {
    const fd = new FormData();
    fd.append('action', 'clock_out');
    if (lat !== null) { fd.append('lat', lat); fd.append('lng', lng); }
    fd.append('task_progress', JSON.stringify(progress));

    fetch('../auth/attendance_action.php', {method:'POST', body:fd})
      .then(r => r.json())
      .then(data => {
        showToast(data.msg, data.ok ? '#10b981' : '#ef4444');
        if (data.ok) setTimeout(() => location.href = 'attendance.php', 1500);
        else { btn.disabled = false; btn.textContent = 'Clock Out & Save'; }
      })
      .catch(() => { showToast('Network error.', '#ef4444'); btn.disabled = false; btn.textContent = 'Clock Out & Save'; });
  };

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => doClockOut(pos.coords.latitude, pos.coords.longitude),
      () => doClockOut(null, null),
      {timeout: 8000}
    );
  } else {
    doClockOut(null, null);
  }
}

function clockOutDirect() {
  const doClockOut = (lat, lng) => {
    const fd = new FormData();
    fd.append('action', 'clock_out');
    if (lat !== null) { fd.append('lat', lat); fd.append('lng', lng); }
    fd.append('task_progress', '[]');

    fetch('../auth/attendance_action.php', {method:'POST', body:fd})
      .then(r => r.json())
      .then(data => {
        showToast(data.msg, data.ok ? '#10b981' : '#ef4444');
        if (data.ok) setTimeout(() => location.href = 'attendance.php', 1500);
      })
      .catch(() => showToast('Network error.', '#ef4444'));
  };

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => doClockOut(pos.coords.latitude, pos.coords.longitude),
      () => doClockOut(null, null),
      {timeout: 8000}
    );
  } else {
    doClockOut(null, null);
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
