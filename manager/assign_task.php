<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/task_config.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// My projects (assigned by admin)
$myProjects = $db->prepare("
    SELECT id, project_name, project_code, deadline_date, total_hours
    FROM projects WHERE manager_id=? AND status NOT IN ('Cancelled')
    ORDER BY project_name ASC
");
$myProjects->execute([$uid]);
$myProjects = $myProjects->fetchAll();

// Team members
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();
$teamMembers = [];
if ($deptId) {
    $tm = $db->prepare("SELECT u.id, u.name, u.email, e.job_title, e.employee_id AS emp_code FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? AND u.status='active' ORDER BY u.name");
    $tm->execute([$deptId, $uid]); $teamMembers = $tm->fetchAll();
}

$preProject = (int)($_GET['project_id'] ?? 0);

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_task') {
    $projectId  = (int)($_POST['project_id'] ?? 0);
    $assignedTo = (int)($_POST['assigned_to'] ?? 0);
    $fromDate   = trim($_POST['from_date'] ?? '');
    $toDate     = trim($_POST['to_date'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');
    $taskData   = $_POST['tasks'] ?? [];
    $errors     = [];

    if (!$projectId) $errors[] = 'Select a project.';
    if (!$assignedTo) $errors[] = 'Select a team member.';
    if (!$fromDate || !$toDate) $errors[] = 'Date range required.';
    if ($fromDate && $toDate && $fromDate > $toDate) $errors[] = 'To date must be after From date.';

    $validTasks = [];
    if (!empty($taskData)) {
        foreach ($taskData as $td) {
            $subtask = trim($td['subtask'] ?? '');
            $hours   = (float)($td['hours'] ?? 0);
            if ($subtask && $hours > 0) {
                $validTasks[] = ['subtask' => $subtask, 'hours' => $hours];
            }
        }
    }
    if (empty($validTasks)) $errors[] = 'Add at least one task with hours.';

    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM projects WHERE id=? AND manager_id=?");
        $chk->execute([$projectId, $uid]);
        if (!$chk->fetch()) $errors[] = 'Project not found.';
    }

    if (empty($errors)) {
        // Budget check — exclude Sat, Sun, holidays, leaves
        $hols = [];
        try {
            $hStmt = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?");
            $hStmt->execute([$fromDate, $toDate]);
            $hols = array_flip($hStmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) {}

        $lvs = [];
        try {
            $lStmt = $db->prepare("SELECT start_date, end_date FROM leave_applications WHERE user_id=? AND status='approved' AND start_date<=? AND end_date>=?");
            $lStmt->execute([$assignedTo, $toDate, $fromDate]);
            foreach ($lStmt->fetchAll() as $lv) {
                $ld = new DateTime(max($fromDate, $lv['start_date']));
                $le = new DateTime(min($toDate, $lv['end_date']));
                while ($ld <= $le) { $lvs[$ld->format('Y-m-d')] = 1; $ld->modify('+1 day'); }
            }
        } catch (Exception $e) {}

        $wDays = 0;
        $d = new DateTime($fromDate); $e = new DateTime($toDate);
        while ($d <= $e) {
            $dow = (int)$d->format('N');
            $ds = $d->format('Y-m-d');
            if ($dow < 6 && !isset($hols[$ds]) && !isset($lvs[$ds])) $wDays++;
            $d->modify('+1 day');
        }

        if ($wDays === 0) {
            $errors[] = "No working days in selected range (all weekends/holidays/leaves).";
        } else {
            $maxHrs = $wDays * 9;

            $existing = $db->prepare("SELECT COALESCE(SUM(hours),0) FROM task_assignments WHERE assigned_to=? AND from_date<=? AND to_date>=?");
            $existing->execute([$assignedTo, $toDate, $fromDate]);
            $alreadyAssigned = (float)$existing->fetchColumn();

            $newHrs = array_sum(array_column($validTasks, 'hours'));

            if (($alreadyAssigned + $newHrs) > $maxHrs) {
                $errors[] = "Exceeds budget! Max: {$maxHrs} hrs ({$wDays} working days). Already assigned: " . number_format($alreadyAssigned,1) . " hrs. New: " . number_format($newHrs,1) . " hrs.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO task_assignments (project_id, assigned_to, assigned_by, subtask, from_date, to_date, hours, notes) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($validTasks as $vt) {
            $stmt->execute([$projectId, $assignedTo, $uid, $vt['subtask'], $fromDate, $toDate, $vt['hours'], $notes ?: null]);
        }
        $_SESSION['flash_success'] = count($validTasks) . " task(s) assigned successfully.";
        header("Location: tasks.php?project_id=$projectId&member_id=$assignedTo"); exit;
    } else {
        $errorMsg = implode(' ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Assign Task – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.assign-layout{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;}
@media(max-width:1100px){.assign-layout{grid-template-columns:1fr;}}
.task-row{display:flex;gap:10px;align-items:center;margin-bottom:8px;padding:10px 12px;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;}
.budget-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-top:12px;}
.budget-pill.ok{background:#d1fae5;color:#059669;border:1px solid #a7f3d0;}
.budget-pill.over{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;}
/* Calendar */
.cal-wrap{border:1px solid var(--border);border-radius:10px;overflow:hidden;background:var(--surface);}
.cal-header{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--surface-2);border-bottom:1px solid var(--border);}
.cal-header h4{font-size:13px;font-weight:700;margin:0;color:var(--text);}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);}
.cal-grid .dh{background:var(--surface-2);text-align:center;font-size:10px;font-weight:700;color:var(--muted);padding:5px 2px;}
.cal-grid .dc{background:var(--surface);min-height:44px;padding:3px;font-size:10px;position:relative;}
.cal-grid .dc.empty{background:var(--surface-2);}
.cal-grid .dc.sun{background:#fef2f2;}
.cal-grid .dc .dn{font-weight:700;font-size:11px;color:var(--text);}
.cal-grid .dc.full{background:#fef3c7;}
.cal-grid .dc.full .dn{color:#d97706;}
.cal-grid .dc.booked .dn{color:#059669;}
.cal-hrs{font-size:9px;font-weight:700;border-radius:3px;padding:1px 3px;display:inline-block;}
.cal-hrs.g{background:#d1fae5;color:#059669;}
.cal-hrs.y{background:#fef3c7;color:#d97706;}
.cal-hrs.r{background:#fee2e2;color:#dc2626;}
.cal-task{font-size:8.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Assign Task</span>
      <span class="page-breadcrumb"><a href="tasks.php" style="color:var(--muted);text-decoration:none;">Tasks</a> / Assign New</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?><div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <?php if(empty($myProjects)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:60px 20px;">
        <div style="font-size:15px;font-weight:700;">No projects assigned to you</div>
        <div style="font-size:13px;color:var(--muted);">Ask admin to assign a project first.</div>
      </div></div>
    <?php elseif(empty($teamMembers)): ?>
      <div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">No team members found in your department.</div>
    <?php else: ?>

    <form method="POST" id="assignForm">
      <input type="hidden" name="action" value="assign_task">

      <div class="assign-layout">
        <!-- LEFT: Form -->
        <div>
          <!-- Step 1: Employee + Project + Dates -->
          <div class="card" style="margin-bottom:16px;">
            <div class="card-body" style="padding:20px;">

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <!-- Employee -->
                <div class="form-group">
                  <label>Team Member <span class="req">*</span></label>
                  <select name="assigned_to" id="empSelect" class="form-control" required onchange="onEmpChange()">
                    <option value="">— Select Employee —</option>
                    <?php foreach($teamMembers as $m): ?>
                      <option value="<?= $m['id'] ?>" <?= (isset($_POST['assigned_to'])&&(int)$_POST['assigned_to']==$m['id'])?'selected':'' ?>>
                        <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['job_title'] ?: $m['emp_code'] ?: $m['email']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <!-- Project -->
                <div class="form-group">
                  <label>Project <span class="req">*</span></label>
                  <select name="project_id" id="projectSelect" class="form-control" required>
                    <option value="">— Select Project —</option>
                    <?php foreach($myProjects as $p): ?>
                      <option value="<?= $p['id'] ?>" <?= ($preProject==$p['id']||(isset($_POST['project_id'])&&(int)$_POST['project_id']==$p['id']))?'selected':'' ?>>
                        <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <!-- From -->
                <div class="form-group">
                  <label>From Date <span class="req">*</span></label>
                  <input type="date" name="from_date" id="fromDate" class="form-control" value="<?= htmlspecialchars($_POST['from_date'] ?? '') ?>" required onchange="onDatesChange()">
                </div>
                <!-- To -->
                <div class="form-group">
                  <label>To Date <span class="req">*</span></label>
                  <input type="date" name="to_date" id="toDate" class="form-control" value="<?= htmlspecialchars($_POST['to_date'] ?? '') ?>" required onchange="onDatesChange()">
                </div>
              </div>

              <!-- Budget -->
              <div id="budgetArea"></div>
              <!-- Busy warning -->
              <div id="busyWarning" style="display:none;margin-top:10px;padding:10px 14px;background:var(--red-bg);border:1px solid #fca5a5;border-radius:8px;font-size:12.5px;font-weight:600;color:var(--red);"></div>

            </div>
          </div>

          <!-- Step 2: Subtasks -->
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header" style="padding:14px 20px;"><h2 style="font-size:15px;">Subtasks & Hours</h2></div>
            <div class="card-body" style="padding:16px 20px;">

              <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
                <select id="subtaskPicker" class="form-control" style="max-width:300px;font-size:12.5px;">
                  <option value="">— Pick subtask —</option>
                  <?php foreach(SUBTASKS as $st): ?>
                    <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="number" id="pickerHrs" class="form-control" min="0.5" step="0.5" placeholder="Hrs" style="width:80px;font-size:12.5px;font-weight:700;text-align:center;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addFromPicker()">+ Add</button>
              </div>
              <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
                <input type="text" id="customName" class="form-control" placeholder="Custom task name" style="max-width:300px;font-size:12.5px;">
                <input type="number" id="customHrs" class="form-control" min="0.5" step="0.5" placeholder="Hrs" style="width:80px;font-size:12.5px;font-weight:700;text-align:center;">
                <button type="button" class="btn btn-ghost btn-sm" onclick="addCustom()">+ Add Custom</button>
              </div>

              <div id="taskList"></div>

              <!-- Total -->
              <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
                <span style="font-size:13px;color:var(--muted);">Total:</span>
                <span style="font-size:18px;font-weight:800;color:var(--brand);" id="totalHrs">0.0</span>
              </div>
              <div id="overWarning" style="display:none;margin-top:8px;padding:8px 12px;background:var(--red-bg);border:1px solid #fca5a5;border-radius:8px;font-size:12px;font-weight:600;color:var(--red);"></div>

            </div>
          </div>

          <!-- Notes + Submit -->
          <div class="card" style="margin-bottom:20px;">
            <div class="card-body" style="padding:16px 20px;">
              <div class="form-group" style="margin-bottom:14px;">
                <label>Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Instructions for the employee…"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
              </div>
              <div style="display:flex;gap:10px;justify-content:flex-end;">
                <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="btnSubmit">Assign Tasks</button>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: Calendar -->
        <div>
          <div class="card" style="position:sticky;top:80px;">
            <div class="card-header" style="padding:12px 16px;"><h2 style="font-size:14px;">Employee Schedule</h2></div>
            <div class="card-body" style="padding:12px;">
              <div id="calPlaceholder" style="text-align:center;padding:30px 10px;color:var(--muted);font-size:12.5px;">
                Select an employee to view schedule.
              </div>
              <div id="calContainer" style="display:none;">
                <div class="cal-wrap">
                  <div class="cal-header">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="calNav(-1)" style="padding:4px 8px;">←</button>
                    <h4 id="calLabel"></h4>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="calNav(1)" style="padding:4px 8px;">→</button>
                  </div>
                  <div class="cal-grid" id="calGrid"></div>
                </div>
                <div style="margin-top:8px;font-size:10.5px;color:var(--muted);display:flex;gap:10px;flex-wrap:wrap;">
                  <span><span class="cal-hrs g">●</span> Available</span>
                  <span><span class="cal-hrs y">●</span> Partial</span>
                  <span><span class="cal-hrs r">●</span> Full (9h)</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>

    <?php endif; ?>
  </div>
</div>
</div>

<script>
let taskIdx = 0, calMonth = new Date().getMonth()+1, calYear = new Date().getFullYear(), empData = {};

// ── Task rows ────────────────────────────────────────────────
function addRow(name, hrs) {
  if(!name){alert('Enter a task name.');return;}
  if(!hrs||hrs<=0){alert('Enter hours for this task.');return;}
  const i = taskIdx++;
  const el = document.createElement('div');
  el.className = 'task-row'; el.id = 'tr-'+i;
  el.innerHTML = `<div style="flex:1;font-size:12.5px;font-weight:600;color:var(--text);">${esc(name)}</div>
    <div style="font-size:13px;font-weight:800;color:var(--brand);min-width:50px;text-align:center;">${parseFloat(hrs).toFixed(1)}h</div>
    <input type="hidden" name="tasks[${i}][subtask]" value="${esc(name)}">
    <input type="hidden" name="tasks[${i}][hours]" value="${hrs}">
    <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;padding:4px 8px;font-size:11px;" onclick="document.getElementById('tr-${i}').remove();reCalc()">✕</button>`;
  document.getElementById('taskList').appendChild(el);
  reCalc();
}
function addFromPicker(){
  const s=document.getElementById('subtaskPicker');
  const h=document.getElementById('pickerHrs');
  if(!s.value){alert('Pick a subtask first.');return;}
  if(!h.value||parseFloat(h.value)<=0){alert('Enter hours for this task.');return;}
  addRow(s.value, parseFloat(h.value));
  s.value=''; h.value='';
}
function addCustom(){
  const n=document.getElementById('customName');
  const h=document.getElementById('customHrs');
  if(!n.value.trim()){alert('Enter a task name.');return;}
  if(!h.value||parseFloat(h.value)<=0){alert('Enter hours for this task.');return;}
  addRow(n.value.trim(), parseFloat(h.value));
  n.value=''; h.value='';
}

function reCalc(){
  let t=0; document.querySelectorAll('input[name$="[hours]"]').forEach(i=>{t+=parseFloat(i.value)||0;});
  document.getElementById('totalHrs').textContent=t.toFixed(1);
  const max=getBudget();
  const warn=document.getElementById('overWarning');
  if(max>0 && t>max){ warn.style.display='block'; warn.textContent='⚠ Total '+t.toFixed(1)+' hrs exceeds budget of '+max+' hrs. Remove tasks to proceed.'; document.getElementById('btnSubmit').disabled=true; }
  else{ warn.style.display='none'; if(!hasBusyDates()) document.getElementById('btnSubmit').disabled=false; }
}

function getBudget(){
  const f=document.getElementById('fromDate').value, t=document.getElementById('toDate').value;
  if(!f||!t||f>t) return 0;
  let d=new Date(f),e=new Date(t),n=0;
  while(d<=e){
    const dow=d.getDay();
    const ds=d.toISOString().split('T')[0];
    // Exclude Sat, Sun, holidays, leaves
    if(dow!==0&&dow!==6&&!empHolidays[ds]&&!empLeaves[ds]) n++;
    d.setDate(d.getDate()+1);
  }
  return n*9;
}

function onDatesChange(){
  const f=document.getElementById('fromDate').value, t=document.getElementById('toDate').value;
  const area=document.getElementById('budgetArea');
  if(!f||!t||f>t){area.innerHTML='';return;}
  const max=getBudget();
  const days=max/9;
  area.innerHTML='<div class="budget-pill ok">'+days+' working day(s) × 9 hrs = <strong>'+max+' hrs</strong> budget</div>';
  reCalc();
  checkBusy();
  loadCal();
}

// ── Busy check ───────────────────────────────────────────────
function hasBusyDates(){
  const f=document.getElementById('fromDate').value, t=document.getElementById('toDate').value;
  if(!f||!t||f>t) return false;
  // Check if there are zero working days
  let d=new Date(f),e=new Date(t),workDays=0;
  while(d<=e){
    const dow=d.getDay();
    const ds=d.toISOString().split('T')[0];
    if(dow!==0&&dow!==6&&!empHolidays[ds]&&!empLeaves[ds]) workDays++;
    d.setDate(d.getDate()+1);
  }
  return workDays===0;
}

function checkBusy(){
  const f=document.getElementById('fromDate').value, t=document.getElementById('toDate').value;
  const warn=document.getElementById('busyWarning');
  if(!f||!t||f>t){warn.style.display='none';return;}

  let busyDates=[], reasons=[];
  let d=new Date(f),e=new Date(t);
  while(d<=e){
    const dow=d.getDay(); // 0=Sun,6=Sat
    const ds=d.toISOString().split('T')[0];

    if(dow===0||dow===6){
      busyDates.push(d.toLocaleDateString('en-IN',{day:'numeric',month:'short'}));
      reasons.push(dow===6?'Sat':'Sun');
    } else if(empHolidays[ds]){
      busyDates.push(d.toLocaleDateString('en-IN',{day:'numeric',month:'short'})+' (Holiday)');
    } else if(empLeaves[ds]){
      busyDates.push(d.toLocaleDateString('en-IN',{day:'numeric',month:'short'})+' (Leave)');
    } else if(empData[ds]&&empData[ds].total_hrs>=9){
      busyDates.push(d.toLocaleDateString('en-IN',{day:'numeric',month:'short'})+' (Full 9h)');
    }
    d.setDate(d.getDate()+1);
  }

  // Check if ALL days in range are blocked
  let workingDaysInRange=0;
  d=new Date(f); e=new Date(t);
  while(d<=e){
    const dow=d.getDay();
    const ds=d.toISOString().split('T')[0];
    if(dow!==0&&dow!==6&&!empHolidays[ds]&&!empLeaves[ds]) workingDaysInRange++;
    d.setDate(d.getDate()+1);
  }

  // Only show warning for holidays/leaves/full days (weekends are expected)
  let blockingDates=[];
  d=new Date(f); e=new Date(t);
  while(d<=e){
    const dow=d.getDay();
    const ds=d.toISOString().split('T')[0];
    if(dow!==0&&dow!==6){
      if(empHolidays[ds]) blockingDates.push(d.toLocaleDateString('en-IN',{day:'numeric',month:'short'})+' 🎉 '+empHolidays[ds]);
      else if(empLeaves[ds]) blockingDates.push(d.toLocaleDateString('en-IN',{day:'numeric',month:'short'})+' 🏖 '+empLeaves[ds]);
      else if(empData[ds]&&empData[ds].total_hrs>=9) blockingDates.push(d.toLocaleDateString('en-IN',{day:'numeric',month:'short'})+' (Full)');
    }
    d.setDate(d.getDate()+1);
  }

  if(workingDaysInRange===0){
    warn.innerHTML='⚠ No working days in selected range (all weekends/holidays/leaves).';
    warn.style.display='block';
    document.getElementById('btnSubmit').disabled=true;
  } else if(blockingDates.length>0){
    warn.innerHTML='⚠ Blocked dates: <strong>'+blockingDates.join(', ')+'</strong>. Hours will be distributed only on available working days.';
    warn.style.display='block';
    // Don't disable — just warn. Budget recalculates based on actual working days.
    reCalc();
  } else {
    warn.style.display='none';
    reCalc();
  }
}

// ── Employee change ──────────────────────────────────────────
function onEmpChange(){
  const v=document.getElementById('empSelect').value;
  if(v){ loadCal(); } else {
    document.getElementById('calPlaceholder').style.display='block';
    document.getElementById('calContainer').style.display='none';
    empData={};
  }
}

// ── Calendar ─────────────────────────────────────────────────
function calNav(dir){ calMonth+=dir; if(calMonth>12){calMonth=1;calYear++;} if(calMonth<1){calMonth=12;calYear--;} loadCal(); }

function loadCal(){
  const emp=document.getElementById('empSelect').value;
  if(!emp) return;
  fetch('get_employee_tasks.php?employee_id='+emp+'&month='+calMonth+'&year='+calYear)
    .then(r=>r.json()).then(data=>{
      empData=data.days||{};
      empHolidays=data.holidays||{};
      empLeaves=data.leaves||{};
      renderCal();checkBusy();
    }).catch(()=>{empData={};empHolidays={};empLeaves={};renderCal();});
}

let empHolidays={}, empLeaves={};

function renderCal(){
  document.getElementById('calPlaceholder').style.display='none';
  document.getElementById('calContainer').style.display='block';
  const ms=['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  document.getElementById('calLabel').textContent=ms[calMonth]+' '+calYear;
  const g=document.getElementById('calGrid'); g.innerHTML='';
  ['M','T','W','T','F','S','S'].forEach(d=>{g.innerHTML+='<div class="dh">'+d+'</div>';});
  const first=new Date(calYear,calMonth-1,1);
  let sd=first.getDay(); sd=sd===0?7:sd;
  const dim=new Date(calYear,calMonth,0).getDate();
  for(let i=1;i<sd;i++) g.innerHTML+='<div class="dc empty"></div>';
  for(let d=1;d<=dim;d++){
    const ds=calYear+'-'+String(calMonth).padStart(2,'0')+'-'+String(d).padStart(2,'0');
    const dow=new Date(calYear,calMonth-1,d).getDay(); // 0=Sun,6=Sat
    const isSat=dow===6, isSun=dow===0;
    const isHoliday=empHolidays[ds]||false;
    const isLeave=empLeaves[ds]||false;
    const dd=empData[ds];
    const hrs=dd?dd.total_hrs:0;

    let cls='dc';
    let html='<div class="dn">'+d+'</div>';

    if(isSat||isSun){
      cls+=' sun';
      html+='<div style="font-size:8px;color:#ef4444;font-weight:600;">'+(isSat?'Sat':'Sun')+'</div>';
    } else if(isHoliday){
      cls+=' sun';
      html+='<div style="font-size:8px;color:#d97706;font-weight:600;">🎉 '+esc(String(isHoliday).substring(0,8))+'</div>';
    } else if(isLeave){
      cls+=' sun';
      html+='<div style="font-size:8px;color:#7c3aed;font-weight:600;">🏖 '+esc(String(isLeave).substring(0,8))+'</div>';
    } else if(hrs>=9){
      cls+=' full';
      html+='<span class="cal-hrs r">'+hrs.toFixed(1)+'h</span>';
      if(dd.tasks&&dd.tasks.length) html+='<div class="cal-task">'+esc(dd.tasks[0].subtask.substring(0,10))+'</div>';
    } else if(hrs>0){
      cls+=' booked';
      const hc=hrs>=5?'y':'g';
      html+='<span class="cal-hrs '+hc+'">'+hrs.toFixed(1)+'h</span>';
      if(dd.tasks&&dd.tasks.length) html+='<div class="cal-task">'+esc(dd.tasks[0].subtask.substring(0,10))+'</div>';
    }

    const title=ds+(isSat||isSun?' — Weekend':isHoliday?' — Holiday: '+isHoliday:isLeave?' — Leave: '+isLeave:hrs?' — '+hrs.toFixed(1)+'h assigned':'');
    g.innerHTML+='<div class="'+cls+'" title="'+esc(title)+'">'+html+'</div>';
  }
}

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML.replace(/"/g,'&quot;');}

// Pre-populate tasks from POST
<?php if(!empty($_POST['tasks'])): ?>
<?php foreach($_POST['tasks'] as $td): ?>
addRow(<?= json_encode($td['subtask']??'') ?>,<?= json_encode($td['hours']??'') ?>);
<?php endforeach; ?>
<?php endif; ?>
</script>
</body>
</html>
