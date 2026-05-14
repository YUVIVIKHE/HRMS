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

$allTasks = $db->prepare("
    SELECT ta.id, ta.subtask, ta.hours AS assigned_hours, ta.status,
           p.project_name, p.project_code,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = ? AND ta.from_date <= ? AND ta.to_date >= ?
    ORDER BY ta.status = 'Completed' ASC, p.project_name, ta.subtask
");
$allTasks->execute([$uid, $today, $today]);
$allTasks = $allTasks->fetchAll();

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
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.popup{background:#fff;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,.2),0 0 0 1px rgba(0,0,0,.05);width:100%;max-width:580px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;animation:popIn .3s ease;}
@keyframes popIn{from{transform:scale(.95) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
.pop-header{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:20px 24px;color:#fff;position:relative;}
.pop-header h2{font-size:18px;font-weight:800;margin-bottom:4px;}
.pop-header p{font-size:12.5px;opacity:.85;}
.pop-header .close-btn{position:absolute;top:16px;right:16px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.pop-header .close-btn:hover{background:rgba(255,255,255,.3);}
.time-row{display:flex;gap:16px;margin-top:12px;}
.time-chip{background:rgba(255,255,255,.15);border-radius:10px;padding:8px 14px;text-align:center;}
.time-chip .tv{font-size:16px;font-weight:800;letter-spacing:-.5px;}
.time-chip .tl{font-size:10px;opacity:.7;text-transform:uppercase;margin-top:1px;}
.pop-body{flex:1;overflow-y:auto;padding:16px 20px;}
.pop-footer{border-top:1px solid #e5e7eb;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;background:#f9fafb;}
.task-item{display:grid;grid-template-columns:1fr 60px 60px 72px;gap:8px;align-items:center;padding:12px 14px;border-radius:12px;border:1.5px solid #e5e7eb;margin-bottom:8px;transition:all .15s;}
.task-item:hover{border-color:#a5b4fc;box-shadow:0 2px 8px rgba(79,70,229,.06);}
.task-item.done{opacity:.45;background:#f9fafb;}
.ti-name{font-size:12.5px;font-weight:700;color:#1e293b;}
.ti-proj{font-size:10.5px;color:#94a3b8;margin-top:1px;}
.ti-num{font-size:12px;font-weight:700;text-align:center;color:#64748b;}
.ti-input{width:100%;font-size:13px;font-weight:700;text-align:center;padding:7px 4px;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;transition:all .15s;}
.ti-input:focus{border-color:#4f46e5;outline:none;box-shadow:0 0 0 3px rgba(79,70,229,.1);}
.ti-input.err{border-color:#ef4444;background:#fef2f2;}
.ti-input:disabled{background:#f1f5f9;color:#94a3b8;cursor:not-allowed;}
.col-head{display:grid;grid-template-columns:1fr 60px 60px 72px;gap:8px;padding:0 14px 8px;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;}
.sec-label{font-size:10.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin:14px 0 6px 4px;}
.btn-primary{background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;border:none;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;transition:all .15s;}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(79,70,229,.3);}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
.btn-ghost{background:transparent;color:#64748b;border:1.5px solid #e2e8f0;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .12s;}
.btn-ghost:hover{border-color:#a5b4fc;color:#4f46e5;}
.total-text{font-size:13px;color:#64748b;}
.total-val{font-size:18px;font-weight:800;color:#4f46e5;}
.warn{font-size:11.5px;color:#ef4444;font-weight:600;margin-top:2px;display:none;}
#toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.2);display:none;z-index:999;}
</style>
</head>
<body>

<div class="popup">
  <!-- Header -->
  <div class="pop-header">
    <button class="close-btn" onclick="location.href='attendance.php'">✕</button>
    <h2>Clock Out</h2>
    <p>Log hours for today's tasks</p>
    <div class="time-row">
      <div class="time-chip">
        <div class="tv"><?= $clockInTime ?></div>
        <div class="tl">Clock In</div>
      </div>
      <div class="time-chip">
        <div class="tv" id="liveNow"><?= $now ?></div>
        <div class="tl">Now</div>
      </div>
      <div class="time-chip">
        <div class="tv"><?= $workH ?>h <?= $workM ?>m</div>
        <div class="tl">Worked</div>
      </div>
      <div class="time-chip" style="background:rgba(255,255,255,.25);">
        <div class="tv"><?= $workHrs ?></div>
        <div class="tl">Max Hrs</div>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="pop-body">
    <?php if(empty($activeTasks) && empty($completedTasks)): ?>
      <div style="text-align:center;padding:32px 16px;">
        <div style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:6px;">No tasks for today</div>
        <div style="font-size:13px;color:#94a3b8;margin-bottom:16px;">Clock out directly.</div>
        <button class="btn-primary" id="btnDirect" onclick="clockOut([])">Clock Out Now</button>
      </div>
    <?php else: ?>

      <div class="col-head">
        <div>Task</div>
        <div style="text-align:center;">Total</div>
        <div style="text-align:center;">Left</div>
        <div style="text-align:center;">Today</div>
      </div>

      <?php if(!empty($activeTasks)): ?>
      <?php foreach($activeTasks as $t):
        $remaining = max(0, round((float)$t['assigned_hours'] - (float)$t['utilized_hours'], 1));
      ?>
      <div class="task-item">
        <div>
          <div class="ti-name"><?= htmlspecialchars($t['subtask']) ?></div>
          <div class="ti-proj"><?= htmlspecialchars($t['project_code']) ?> · <?= htmlspecialchars($t['project_name']) ?></div>
        </div>
        <div class="ti-num" style="color:#4f46e5;"><?= number_format($t['assigned_hours'],1) ?></div>
        <div class="ti-num" style="color:<?= $remaining>0?'#059669':'#ef4444' ?>;"><?= number_format($remaining,1) ?></div>
        <div>
          <?php if($remaining > 0): ?>
          <input type="number" class="ti-input" data-id="<?= $t['id'] ?>" data-max="<?= $remaining ?>" min="0" max="<?= $remaining ?>" step="0.5" value="" placeholder="—" oninput="validate()">
          <?php else: ?>
          <input type="number" class="ti-input" disabled placeholder="0">
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if(!empty($completedTasks)): ?>
      <div class="sec-label">Completed</div>
      <?php foreach($completedTasks as $t): ?>
      <div class="task-item done">
        <div>
          <div class="ti-name"><?= htmlspecialchars($t['subtask']) ?></div>
          <div class="ti-proj"><?= htmlspecialchars($t['project_code']) ?> · <?= htmlspecialchars($t['project_name']) ?></div>
        </div>
        <div class="ti-num"><?= number_format($t['assigned_hours'],1) ?></div>
        <div class="ti-num">0.0</div>
        <div><input type="number" class="ti-input" disabled placeholder="✓"></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <!-- Footer -->
  <?php if(!empty($activeTasks) || !empty($completedTasks)): ?>
  <div class="pop-footer">
    <div>
      <div class="total-text">Logged: <span class="total-val" id="totalHrs">0.0</span> / <?= $workHrs ?> hrs</div>
      <div class="warn" id="warnMsg"></div>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="attendance.php" class="btn-ghost">Cancel</a>
      <button class="btn-primary" id="btnClockOut" onclick="submitClockOut()">Clock Out & Save</button>
    </div>
  </div>
  <?php endif; ?>
</div>

<div id="toast"></div>

<script>
const MAX_HRS = <?= $workHrs ?>;

function tick(){const n=new Date();document.getElementById('liveNow').textContent=n.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',hour12:true}).toUpperCase();}
tick();setInterval(tick,30000);

function validate(){
  let total=0,hasErr=false;
  document.querySelectorAll('.ti-input:not(:disabled)').forEach(inp=>{
    const val=parseFloat(inp.value)||0;
    const max=parseFloat(inp.dataset.max);
    total+=val;
    if(val>max){inp.classList.add('err');hasErr=true;}else{inp.classList.remove('err');}
  });
  document.getElementById('totalHrs').textContent=total.toFixed(1);
  const warn=document.getElementById('warnMsg');
  const btn=document.getElementById('btnClockOut');
  if(total>MAX_HRS){warn.textContent='⚠ Exceeds worked time ('+MAX_HRS+' hrs)';warn.style.display='block';btn.disabled=true;}
  else if(hasErr){warn.textContent='⚠ Some tasks exceed remaining hours';warn.style.display='block';btn.disabled=true;}
  else{warn.style.display='none';btn.disabled=false;}
}

function submitClockOut(){
  const progress=[];
  document.querySelectorAll('.ti-input:not(:disabled)').forEach(inp=>{
    const hrs=parseFloat(inp.value)||0;
    if(hrs>0){
      const id=parseInt(inp.dataset.id);
      const max=parseFloat(inp.dataset.max);
      const status=(max-hrs)<=0?'Completed':'In Progress';
      progress.push({task_id:id,hours:hrs,progress:status});
    }
  });
  clockOut(progress);
}

function clockOut(progress){
  const btn=document.getElementById('btnClockOut')||document.getElementById('btnDirect');
  if(btn){btn.disabled=true;btn.textContent='Clocking out…';}
  const doIt=(lat,lng)=>{
    const fd=new FormData();
    fd.append('action','clock_out');
    if(lat!==null){fd.append('lat',lat);fd.append('lng',lng);}
    fd.append('task_progress',JSON.stringify(progress));
    fetch('../auth/attendance_action.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(data=>{
        showToast(data.msg,data.ok?'#10b981':'#ef4444');
        if(data.ok)setTimeout(()=>location.href='attendance.php',1000);
        else if(btn){btn.disabled=false;btn.textContent='Clock Out & Save';}
      })
      .catch(()=>{showToast('Network error.','#ef4444');if(btn){btn.disabled=false;btn.textContent='Clock Out & Save';}});
  };
  if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(pos=>doIt(pos.coords.latitude,pos.coords.longitude),()=>doIt(null,null),{enableHighAccuracy:true,timeout:5000,maximumAge:0});
  }else doIt(null,null);
}

function showToast(msg,color){const t=document.getElementById('toast');t.textContent=msg;t.style.background=color||'#1e293b';t.style.display='block';setTimeout(()=>t.style.display='none',4000);}
</script>
</body>
</html>
