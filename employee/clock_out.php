<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');
date_default_timezone_set('Asia/Kolkata');

$todayLog = $db->prepare("SELECT * FROM attendance_logs WHERE user_id=? AND log_date=?");
$todayLog->execute([$uid, $today]);
$todayLog = $todayLog->fetch();

if (!$todayLog || !$todayLog['clock_in'] || $todayLog['clock_out']) {
    header("Location: attendance.php"); exit;
}

$clockInTime = date('h:i A', strtotime($todayLog['clock_in']));
$now = date('h:i A');
$workSec = time() - strtotime($todayLog['clock_in']);
$workHrs = round($workSec / 3600, 1);
$workH = floor($workSec / 3600);
$workM = floor(($workSec % 3600) / 60);

// Get all tasks for today (including completed — they won't have remaining hrs so input stays 0)
$allTasks = $db->prepare("
    SELECT ta.id, ta.subtask, ta.hours AS assigned_hours, ta.status,
           p.project_name, p.project_code,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = ?
      AND ta.from_date <= ? AND ta.to_date >= ?
    ORDER BY ta.status = 'Completed' ASC, p.project_name, ta.subtask
");
$allTasks->execute([$uid, $today, $today]);
$allTasks = $allTasks->fetchAll();

// Split into active and completed
$activeTasks = array_filter($allTasks, fn($t) => $t['status'] !== 'Completed');
$completedTasks = array_filter($allTasks, fn($t) => $t['status'] === 'Completed');
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
.co-shell{display:grid;grid-template-rows:auto 1fr auto;height:calc(100vh - 56px);overflow:hidden;}
.co-header{background:var(--surface);border-bottom:1px solid var(--border);padding:16px 28px;display:flex;align-items:center;justify-content:space-between;}
.co-header-left h1{font-size:18px;font-weight:800;color:var(--text);margin:0 0 2px;}
.co-header-left p{font-size:12.5px;color:var(--muted);margin:0;}
.co-stats{display:flex;gap:20px;align-items:center;}
.co-stat{text-align:center;}
.co-stat .val{font-size:20px;font-weight:800;line-height:1;}
.co-stat .lbl{font-size:10.5px;font-weight:600;color:var(--muted);text-transform:uppercase;margin-top:2px;}
.co-body{overflow-y:auto;padding:20px 28px;}
.co-footer{background:var(--surface);border-top:1.5px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;}
.task-card{display:grid;grid-template-columns:1fr 70px 70px 80px;gap:12px;align-items:center;padding:14px 16px;background:var(--surface);border:1.5px solid var(--border);border-radius:10px;margin-bottom:8px;transition:border-color .12s;}
.task-card:hover{border-color:var(--brand);}
.task-card.done{opacity:.5;background:var(--surface-2);}
.tc-name{font-size:13px;font-weight:700;color:var(--text);}
.tc-proj{font-size:11px;color:var(--muted);}
.tc-num{font-size:13px;font-weight:700;text-align:center;}
.tc-input{width:100%;font-size:13px;font-weight:700;text-align:center;padding:7px 4px;border:1.5px solid var(--border);border-radius:7px;background:#fff;transition:border-color .12s;}
.tc-input:focus{border-color:var(--brand);outline:none;box-shadow:0 0 0 3px var(--brand-light);}
.tc-input.err{border-color:var(--red);background:#fff5f5;}
.tc-input:disabled{background:var(--surface-2);color:var(--muted);cursor:not-allowed;}
.section-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin:16px 0 8px;padding-left:4px;}
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content" style="display:flex;flex-direction:column;height:100vh;">

  <header class="topbar" style="flex-shrink:0;">
    <div class="topbar-left"><span class="page-title">Clock Out</span></div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="co-shell">
    <!-- Header -->
    <div class="co-header">
      <div class="co-header-left">
        <h1>Log Today's Work</h1>
        <p>Enter hours per task. Total must not exceed your worked time.</p>
      </div>
      <div class="co-stats">
        <div class="co-stat">
          <div class="val" style="color:var(--brand);"><?= $clockInTime ?></div>
          <div class="lbl">Clock In</div>
        </div>
        <div class="co-stat">
          <div class="val" style="color:var(--text);" id="liveNow"><?= $now ?></div>
          <div class="lbl">Now</div>
        </div>
        <div class="co-stat">
          <div class="val" style="color:var(--green-text);"><?= $workH ?>h <?= $workM ?>m</div>
          <div class="lbl">Worked</div>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="co-body">
      <?php if(empty($activeTasks) && empty($completedTasks)): ?>
        <div style="text-align:center;padding:40px 20px;">
          <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px;">No tasks for today</div>
          <div style="font-size:13px;color:var(--muted);margin-bottom:16px;">Clock out directly.</div>
          <button class="btn btn-primary" id="btnDirect" onclick="clockOut([])">Clock Out Now</button>
          <a href="attendance.php" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
        </div>
      <?php else: ?>

        <!-- Column headers -->
        <div style="display:grid;grid-template-columns:1fr 70px 70px 80px;gap:12px;padding:0 16px 6px;font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;">
          <div>Task</div>
          <div style="text-align:center;">Allotted</div>
          <div style="text-align:center;">Remaining</div>
          <div style="text-align:center;">Hrs Today</div>
        </div>

        <!-- Active Tasks -->
        <?php if(!empty($activeTasks)): ?>
        <div class="section-label">Active Tasks</div>
        <?php foreach($activeTasks as $t):
          $remaining = max(0, round((float)$t['assigned_hours'] - (float)$t['utilized_hours'], 1));
        ?>
        <div class="task-card">
          <div>
            <div class="tc-name"><?= htmlspecialchars($t['subtask']) ?></div>
            <div class="tc-proj"><?= htmlspecialchars($t['project_code']) ?> · <?= htmlspecialchars($t['project_name']) ?></div>
          </div>
          <div class="tc-num" style="color:var(--brand);"><?= number_format($t['assigned_hours'],1) ?></div>
          <div class="tc-num" style="color:<?= $remaining>0?'var(--green-text)':'var(--red)' ?>;"><?= number_format($remaining,1) ?></div>
          <div>
            <?php if($remaining > 0): ?>
            <input type="number" class="tc-input" id="hrs-<?= $t['id'] ?>" data-id="<?= $t['id'] ?>" data-max="<?= $remaining ?>" min="0" max="<?= $remaining ?>" step="0.5" value="" placeholder="—" oninput="validate()">
            <?php else: ?>
            <input type="number" class="tc-input" disabled value="" placeholder="0">
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Completed Tasks -->
        <?php if(!empty($completedTasks)): ?>
        <div class="section-label" style="margin-top:20px;">Completed Tasks</div>
        <?php foreach($completedTasks as $t): ?>
        <div class="task-card done">
          <div>
            <div class="tc-name"><?= htmlspecialchars($t['subtask']) ?></div>
            <div class="tc-proj"><?= htmlspecialchars($t['project_code']) ?> · <?= htmlspecialchars($t['project_name']) ?></div>
          </div>
          <div class="tc-num" style="color:var(--muted);"><?= number_format($t['assigned_hours'],1) ?></div>
          <div class="tc-num" style="color:var(--muted);">0.0</div>
          <div><input type="number" class="tc-input" disabled value="" placeholder="✓"></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

      <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php if(!empty($activeTasks) || !empty($completedTasks)): ?>
    <div class="co-footer">
      <div>
        <span style="font-size:13px;color:var(--muted);">Total logged: </span>
        <strong id="totalHrs" style="font-size:17px;color:var(--brand);">0.0</strong>
        <span style="font-size:13px;color:var(--muted);"> / <?= $workHrs ?> hrs max</span>
        <div id="warnMsg" class="warn-text" style="display:none;font-size:12px;color:var(--red);font-weight:600;margin-top:2px;"></div>
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

<div id="toast" style="position:fixed;bottom:20px;right:20px;background:#111827;color:#fff;padding:12px 18px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.15);display:none;z-index:999;"></div>

<script>
const MAX_HRS = <?= $workHrs ?>;

function tick(){const n=new Date();document.getElementById('liveNow').textContent=n.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',hour12:true}).toUpperCase();}
tick();setInterval(tick,30000);

function validate() {
  let total = 0, hasErr = false;
  document.querySelectorAll('.tc-input:not(:disabled)').forEach(inp => {
    const val = parseFloat(inp.value) || 0;
    const max = parseFloat(inp.dataset.max);
    total += val;
    if (val > max) { inp.classList.add('err'); hasErr = true; }
    else { inp.classList.remove('err'); }
  });
  document.getElementById('totalHrs').textContent = total.toFixed(1);
  const warn = document.getElementById('warnMsg');
  const btn = document.getElementById('btnClockOut');
  if (total > MAX_HRS) {
    warn.textContent = '⚠ Exceeds worked time (' + MAX_HRS + ' hrs)';
    warn.style.display = 'block'; btn.disabled = true;
  } else if (hasErr) {
    warn.textContent = '⚠ Some tasks exceed remaining hours';
    warn.style.display = 'block'; btn.disabled = true;
  } else {
    warn.style.display = 'none'; btn.disabled = false;
  }
}

function submitClockOut() {
  const progress = [];
  document.querySelectorAll('.tc-input:not(:disabled)').forEach(inp => {
    const hrs = parseFloat(inp.value) || 0;
    if (hrs > 0) {
      const id = parseInt(inp.dataset.id);
      const max = parseFloat(inp.dataset.max);
      const status = (max - hrs) <= 0 ? 'Completed' : 'In Progress';
      progress.push({task_id: id, hours: hrs, progress: status});
    }
  });
  clockOut(progress);
}

function clockOut(progress) {
  const btn = document.getElementById('btnClockOut') || document.getElementById('btnDirect');
  if(btn){btn.disabled=true;btn.textContent='Clocking out…';}
  const doIt = (lat, lng) => {
    const fd = new FormData();
    fd.append('action', 'clock_out');
    if (lat !== null) { fd.append('lat', lat); fd.append('lng', lng); }
    fd.append('task_progress', JSON.stringify(progress));
    fetch('../auth/attendance_action.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(data=>{
        showToast(data.msg, data.ok?'#10b981':'#ef4444');
        if(data.ok) setTimeout(()=>location.href='attendance.php',1000);
        else if(btn){btn.disabled=false;btn.textContent='Clock Out & Save';}
      })
      .catch(()=>{showToast('Network error.','#ef4444');if(btn){btn.disabled=false;btn.textContent='Clock Out & Save';}});
  };
  if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(pos=>doIt(pos.coords.latitude,pos.coords.longitude),()=>doIt(null,null),{enableHighAccuracy:true,timeout:5000,maximumAge:0});
  } else doIt(null,null);
}

function showToast(msg,color){const t=document.getElementById('toast');t.textContent=msg;t.style.background=color||'#111827';t.style.display='block';setTimeout(()=>t.style.display='none',4000);}
</script>
</body>
</html>
