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
$workSec = time() - strtotime($todayLog['clock_in']);
$workHrs = round($workSec / 3600, 1);

// Get all active tasks for today
$allTasks = $db->prepare("
    SELECT ta.id, ta.subtask, ta.hours AS assigned_hours, ta.project_id,
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
html,body{height:100%;overflow:hidden;}
.co-layout{display:flex;flex-direction:column;height:calc(100vh - 56px);overflow:hidden;}
.co-top{background:linear-gradient(135deg,var(--brand),var(--brand-mid));padding:16px 24px;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.co-top h1{font-size:18px;font-weight:800;margin:0;}
.co-top .info{font-size:13px;opacity:.9;}
.co-top .big-time{font-size:24px;font-weight:800;letter-spacing:-0.5px;}
.co-body{flex:1;overflow-y:auto;padding:16px 24px;}
.co-footer{flex-shrink:0;background:var(--surface);border-top:1.5px solid var(--border);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;}
.t-row{display:grid;grid-template-columns:1fr 80px 80px 90px;gap:10px;align-items:center;padding:12px 14px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;background:var(--surface);}
.t-row:hover{border-color:var(--brand-light);}
.t-name{font-size:13px;font-weight:700;color:var(--text);}
.t-meta{font-size:11px;color:var(--muted);}
.t-hrs{font-size:12px;font-weight:700;text-align:center;}
.t-input{width:100%;font-size:13px;font-weight:700;text-align:center;padding:6px 4px;border:1.5px solid var(--border);border-radius:6px;background:#fff;}
.t-input:focus{border-color:var(--brand);outline:none;}
.t-input.error{border-color:var(--red);background:#fff5f5;}
.warn-text{color:var(--red);font-size:12px;font-weight:600;}
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content" style="display:flex;flex-direction:column;height:100vh;">

  <header class="topbar" style="flex-shrink:0;">
    <div class="topbar-left">
      <span class="page-title">Clock Out</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="co-layout">
    <!-- Top Banner -->
    <div class="co-top">
      <div>
        <h1>Log Hours & Clock Out</h1>
        <div class="info">In: <?= $clockInTime ?> · Worked: <strong><?= $workHrs ?> hrs</strong></div>
      </div>
      <div class="big-time" id="liveTime">--:--</div>
    </div>

    <?php if(empty($allTasks)): ?>
    <!-- No tasks -->
    <div class="co-body" style="display:flex;align-items:center;justify-content:center;">
      <div style="text-align:center;">
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px;">No tasks assigned for today</div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:16px;">You can clock out directly.</div>
        <button class="btn btn-primary" id="btnDirect" onclick="clockOut([])">Clock Out Now</button>
        <a href="attendance.php" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
      </div>
    </div>
    <?php else: ?>

    <!-- Task Table -->
    <div class="co-body">
      <!-- Header -->
      <div style="display:grid;grid-template-columns:1fr 80px 80px 90px;gap:10px;padding:8px 14px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;">
        <div>Task</div>
        <div style="text-align:center;">Allotted</div>
        <div style="text-align:center;">Remaining</div>
        <div style="text-align:center;">Hrs Today</div>
      </div>

      <?php foreach($allTasks as $t):
        $remaining = max(0, round((float)$t['assigned_hours'] - (float)$t['utilized_hours'], 1));
      ?>
      <div class="t-row" id="row-<?= $t['id'] ?>">
        <div>
          <div class="t-name"><?= htmlspecialchars($t['subtask']) ?></div>
          <div class="t-meta"><?= htmlspecialchars($t['project_code']) ?> · <?= htmlspecialchars($t['project_name']) ?></div>
        </div>
        <div class="t-hrs" style="color:var(--brand);"><?= number_format($t['assigned_hours'],1) ?></div>
        <div class="t-hrs" style="color:<?= $remaining>0?'var(--green-text)':'var(--red)' ?>;"><?= number_format($remaining,1) ?></div>
        <div>
          <input type="number" class="t-input" id="hrs-<?= $t['id'] ?>" data-id="<?= $t['id'] ?>" data-max="<?= $remaining ?>" min="0" max="<?= $remaining ?>" step="0.5" value="" placeholder="—" oninput="validate()">
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="co-footer">
      <div>
        <span style="font-size:13px;color:var(--muted);">Total: </span>
        <strong id="totalHrs" style="font-size:16px;color:var(--brand);">0.0</strong>
        <span style="font-size:13px;color:var(--muted);"> / <?= $workHrs ?> hrs</span>
        <div id="footerWarn" class="warn-text" style="display:none;"></div>
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

function tick(){const n=new Date();document.getElementById('liveTime').textContent=n.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});}
tick();setInterval(tick,1000);

function validate() {
  let total = 0, hasErr = false;
  document.querySelectorAll('.t-input').forEach(inp => {
    const val = parseFloat(inp.value) || 0;
    const max = parseFloat(inp.dataset.max);
    total += val;
    if (val > max) { inp.classList.add('error'); hasErr = true; }
    else { inp.classList.remove('error'); }
  });

  document.getElementById('totalHrs').textContent = total.toFixed(1);
  const warn = document.getElementById('footerWarn');
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
  document.querySelectorAll('.t-input').forEach(inp => {
    const hrs = parseFloat(inp.value) || 0;
    if (hrs > 0) {
      const id = parseInt(inp.dataset.id);
      const max = parseFloat(inp.dataset.max);
      // Auto-calculate progress: if remaining after this = 0, mark Completed
      const newRemaining = max - hrs;
      const status = newRemaining <= 0 ? 'Completed' : 'In Progress';
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
    navigator.geolocation.getCurrentPosition(
      pos=>doIt(pos.coords.latitude,pos.coords.longitude),
      ()=>doIt(null,null),
      {enableHighAccuracy:true,timeout:5000,maximumAge:0}
    );
  } else doIt(null,null);
}

function showToast(msg,color){const t=document.getElementById('toast');t.textContent=msg;t.style.background=color||'#111827';t.style.display='block';setTimeout(()=>t.style.display='none',4000);}
</script>
</body>
</html>
