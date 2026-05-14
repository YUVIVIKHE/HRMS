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

// Pre-select project from URL
$preProject = (int)($_GET['project_id'] ?? 0);

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_task') {
    $projectId  = (int)($_POST['project_id'] ?? 0);
    $assignedTo = (int)($_POST['assigned_to'] ?? 0);
    $fromDate   = trim($_POST['from_date'] ?? '');
    $toDate     = trim($_POST['to_date'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');
    $taskData   = $_POST['tasks'] ?? []; // array of {subtask, hours}
    $errors     = [];

    if (!$projectId) $errors[] = 'Select a project.';
    if (!$assignedTo) $errors[] = 'Select a team member.';
    if (!$fromDate || !$toDate) $errors[] = 'Date range required.';
    if ($fromDate && $toDate && $fromDate > $toDate) $errors[] = 'To date must be after From date.';
    
    // Validate tasks
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
        // Verify project belongs to this manager
        $chk = $db->prepare("SELECT id FROM projects WHERE id=? AND manager_id=?");
        $chk->execute([$projectId, $uid]);
        if (!$chk->fetch()) {
            $errors[] = 'Project not found.';
        }
    }

    if (empty($errors)) {
        // Calculate budget: working days × 9 hrs
        $wDays = calcWorkingDays($fromDate, $toDate);
        $maxHrs = $wDays * 9;

        // Get already assigned hours for this employee in overlapping date range
        $existing = $db->prepare("SELECT COALESCE(SUM(hours),0) FROM task_assignments WHERE assigned_to=? AND from_date<=? AND to_date>=?");
        $existing->execute([$assignedTo, $toDate, $fromDate]);
        $alreadyAssigned = (float)$existing->fetchColumn();

        // Total new hours
        $newHrs = array_sum(array_column($validTasks, 'hours'));

        if (($alreadyAssigned + $newHrs) > $maxHrs) {
            $errors[] = "Exceeds budget! Available: " . number_format($maxHrs,1) . " hrs, Already assigned: " . number_format($alreadyAssigned,1) . " hrs, New: " . number_format($newHrs,1) . " hrs. Total would be " . number_format($alreadyAssigned + $newHrs,1) . " hrs.";
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

// Calculate working hours for display
function calcWorkingDays(string $from, string $to): int {
    if (!$from || !$to || $from > $to) return 0;
    $days = 0;
    $d = new DateTime($from);
    $e = new DateTime($to);
    while ($d <= $e) {
        if ((int)$d->format('N') !== 7) $days++;
        $d->modify('+1 day');
    }
    return $days;
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
.task-row{display:flex;gap:10px;align-items:center;margin-bottom:10px;padding:12px 14px;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;}
.task-row .form-control{margin:0;}
.budget-box{border-radius:8px;padding:12px 16px;margin-bottom:20px;border:1.5px solid #c7d2fe;background:var(--brand-light);font-size:13px;}
/* Calendar */
.emp-cal{border:1px solid var(--border);border-radius:10px;overflow:hidden;background:var(--surface);}
.emp-cal-header{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--surface-2);border-bottom:1px solid var(--border);}
.emp-cal-header h3{font-size:14px;font-weight:700;margin:0;color:var(--text);}
.emp-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);padding:1px;}
.emp-cal-grid .day-header{background:var(--surface-2);text-align:center;font-size:11px;font-weight:700;color:var(--muted);padding:6px 2px;}
.emp-cal-grid .day-cell{background:var(--surface);min-height:60px;padding:4px;position:relative;font-size:11px;}
.emp-cal-grid .day-cell.empty{background:var(--surface-2);}
.emp-cal-grid .day-cell.sunday{background:#fef2f2;}
.emp-cal-grid .day-cell .day-num{font-weight:700;color:var(--text);margin-bottom:2px;}
.emp-cal-grid .day-cell.full{background:#fef3c7;}
.emp-cal-grid .day-cell.full .day-num{color:#d97706;}
.day-hrs{font-size:10px;font-weight:700;border-radius:3px;padding:1px 4px;display:inline-block;margin-top:1px;}
.day-hrs.ok{background:#d1fae5;color:#059669;}
.day-hrs.warn{background:#fef3c7;color:#d97706;}
.day-hrs.full{background:#fee2e2;color:#dc2626;}
.day-task{font-size:9.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;}
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
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No projects assigned to you</div>
        <div style="font-size:13px;color:var(--muted);">Ask admin to assign a project first.</div>
      </div></div>
    <?php elseif(empty($teamMembers)): ?>
      <div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">No team members found in your department.</div>
    <?php else: ?>

    <form method="POST" id="assignForm">
      <input type="hidden" name="action" value="assign_task">

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2>Assignment Details</h2></div>
        <div class="card-body">
          <div class="form-grid">

            <!-- Project -->
            <div class="form-group">
              <label>Project <span class="req">*</span></label>
              <select name="project_id" id="projectSelect" class="form-control" required>
                <option value="">— Select Project —</option>
                <?php foreach($myProjects as $p): ?>
                  <option value="<?= $p['id'] ?>" data-deadline="<?= $p['deadline_date'] ?>" data-hours="<?= $p['total_hours'] ?>" <?= ($preProject==$p['id']||(isset($_POST['project_id'])&&(int)$_POST['project_id']==$p['id']))?'selected':'' ?>>
                    <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Employee -->
            <div class="form-group">
              <label>Team Member <span class="req">*</span></label>
              <select name="assigned_to" class="form-control" required>
                <option value="">— Select Employee —</option>
                <?php foreach($teamMembers as $m): ?>
                  <option value="<?= $m['id'] ?>" <?= (isset($_POST['assigned_to'])&&(int)$_POST['assigned_to']==$m['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['job_title'] ?: $m['emp_code'] ?: $m['email']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Date range -->
            <div class="form-group">
              <label>From Date <span class="req">*</span></label>
              <input type="date" name="from_date" id="fromDate" class="form-control" value="<?= htmlspecialchars($_POST['from_date'] ?? '') ?>" required onchange="calcBudget()">
            </div>
            <div class="form-group">
              <label>To Date <span class="req">*</span></label>
              <input type="date" name="to_date" id="toDate" class="form-control" value="<?= htmlspecialchars($_POST['to_date'] ?? '') ?>" required onchange="calcBudget()">
            </div>

          </div>

          <!-- Budget display -->
          <div class="budget-box" id="budgetBox" style="display:none;margin-top:16px;">
            <span style="font-weight:700;color:var(--brand);" id="budgetLabel"></span>
          </div>

        </div>
      </div>

      <!-- Employee Calendar -->
      <div class="card" style="margin-bottom:20px;" id="calendarCard" style="display:none;">
        <div class="card-header"><h2>Employee Schedule</h2><p>Highlighted dates show existing task assignments. Red = fully booked (9 hrs).</p></div>
        <div class="card-body">
          <div id="calendarPlaceholder" style="text-align:center;padding:20px;color:var(--muted);font-size:13px;">
            Select an employee to view their schedule.
          </div>
          <div id="calendarContainer" style="display:none;">
            <div class="emp-cal">
              <div class="emp-cal-header">
                <button type="button" class="btn btn-ghost btn-sm" onclick="changeCalMonth(-1)">← Prev</button>
                <h3 id="calMonthLabel"></h3>
                <button type="button" class="btn btn-ghost btn-sm" onclick="changeCalMonth(1)">Next →</button>
              </div>
              <div class="emp-cal-grid" id="calGrid"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tasks Section -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <div>
            <h2>Tasks & Hours</h2>
            <p>Select tasks and enter hours for each. You decide how many hours per task.</p>
          </div>
        </div>
        <div class="card-body">

          <!-- Add from predefined list -->
          <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
            <select id="subtaskPicker" class="form-control" style="max-width:350px;font-size:13px;">
              <option value="">— Pick a subtask to add —</option>
              <?php foreach(SUBTASKS as $st): ?>
                <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addTaskFromPicker()">+ Add Task</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addCustomTask()">+ Custom Task</button>
          </div>

          <!-- Task rows -->
          <div id="taskList">
            <!-- Dynamic rows added here -->
          </div>

          <!-- Total -->
          <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
            <span style="font-size:13px;color:var(--muted);">Total Hours:</span>
            <span style="font-size:18px;font-weight:800;color:var(--brand);" id="totalHrs">0.0</span>
          </div>
          <div id="budgetWarning" style="display:none;margin-top:10px;padding:10px 14px;background:var(--red-bg);border:1px solid #fca5a5;border-radius:8px;font-size:13px;font-weight:600;color:var(--red);"></div>

        </div>
      </div>

      <!-- Notes -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2>Notes</h2></div>
        <div class="card-body">
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional instructions for the employee…" style="resize:vertical;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Actions -->
      <div style="display:flex;justify-content:flex-end;gap:10px;padding-bottom:32px;">
        <a href="tasks.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="btnSubmit">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Assign Tasks
        </button>
      </div>
    </form>

    <?php endif; ?>
  </div>
</div>
</div>

<script>
let taskIndex = 0;

function addTaskRow(subtask, hours) {
  const list = document.getElementById('taskList');
  const idx = taskIndex++;
  const row = document.createElement('div');
  row.className = 'task-row';
  row.id = 'taskRow-' + idx;
  row.innerHTML = `
    <div style="flex:1;min-width:200px;">
      <input type="text" name="tasks[${idx}][subtask]" class="form-control" value="${escAttr(subtask)}" placeholder="Task name" required style="font-size:13px;font-weight:600;">
    </div>
    <div style="width:100px;">
      <input type="number" name="tasks[${idx}][hours]" class="form-control task-hrs-input" min="0.5" step="0.5" value="${hours||''}" placeholder="Hrs" required style="font-size:13px;font-weight:700;text-align:center;" oninput="calcTotal()">
    </div>
    <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;" onclick="removeTask('taskRow-${idx}')">✕</button>
  `;
  list.appendChild(row);
  calcTotal();
}

function addTaskFromPicker() {
  const sel = document.getElementById('subtaskPicker');
  if (!sel.value) { alert('Pick a subtask first.'); return; }
  addTaskRow(sel.value, '');
  sel.value = '';
}

function addCustomTask() {
  addTaskRow('', '');
}

function removeTask(id) {
  document.getElementById(id)?.remove();
  calcTotal();
}

function calcTotal() {
  let total = 0;
  document.querySelectorAll('.task-hrs-input').forEach(inp => { total += parseFloat(inp.value) || 0; });
  document.getElementById('totalHrs').textContent = total.toFixed(1);
  checkBudgetLimit(total);
}

function checkBudgetLimit(total) {
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;
  const warn = document.getElementById('budgetWarning');
  if (!from || !to || from > to) { if(warn) warn.style.display='none'; return; }
  const days = workingDays(from, to);
  const maxHrs = days * 9;
  if (total > maxHrs) {
    if(warn) { warn.style.display='block'; warn.textContent='⚠ Total ' + total.toFixed(1) + ' hrs exceeds budget of ' + maxHrs + ' hrs (' + days + ' days × 9 hrs). Reduce hours to proceed.'; }
    document.getElementById('btnSubmit').disabled = true;
  } else {
    if(warn) warn.style.display='none';
    document.getElementById('btnSubmit').disabled = false;
  }
}

function calcBudget() {
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;
  const box = document.getElementById('budgetBox');
  const lbl = document.getElementById('budgetLabel');
  if (!from || !to || from > to) { box.style.display = 'none'; return; }
  const days = workingDays(from, to);
  const maxHrs = days * 9;
  box.style.display = 'block';
  lbl.textContent = days + ' working day(s) × 9 hrs = ' + maxHrs + ' hrs available budget';
  calcTotal(); // re-check limit
}

function workingDays(f, t) {
  if (!f || !t || f > t) return 0;
  let d = new Date(f), e = new Date(t), n = 0;
  while (d <= e) { if (d.getDay() !== 0) n++; d.setDate(d.getDate() + 1); }
  return n;
}

function escAttr(str) {
  const d = document.createElement('div'); d.textContent = str;
  return d.innerHTML.replace(/"/g, '&quot;');
}

// Pre-populate from POST if validation failed
<?php if(!empty($_POST['tasks'])): ?>
<?php foreach($_POST['tasks'] as $td): ?>
addTaskRow(<?= json_encode($td['subtask']??'') ?>, <?= json_encode($td['hours']??'') ?>);
<?php endforeach; ?>
<?php endif; ?>

// ── Calendar ─────────────────────────────────────────────────
let calMonth = new Date().getMonth() + 1;
let calYear = new Date().getFullYear();
let empTaskData = {};

// Listen for employee selection change
document.querySelector('[name="assigned_to"]').addEventListener('change', function() {
  if (this.value) loadCalendar();
  else {
    document.getElementById('calendarPlaceholder').style.display = 'block';
    document.getElementById('calendarContainer').style.display = 'none';
  }
});

function changeCalMonth(dir) {
  calMonth += dir;
  if (calMonth > 12) { calMonth = 1; calYear++; }
  if (calMonth < 1) { calMonth = 12; calYear--; }
  loadCalendar();
}

function loadCalendar() {
  const empId = document.querySelector('[name="assigned_to"]').value;
  if (!empId) return;

  fetch('get_employee_tasks.php?employee_id=' + empId + '&month=' + calMonth + '&year=' + calYear)
    .then(r => r.json())
    .then(data => {
      empTaskData = data;
      renderCalendar();
    })
    .catch(() => renderCalendar());
}

function renderCalendar() {
  document.getElementById('calendarPlaceholder').style.display = 'none';
  document.getElementById('calendarContainer').style.display = 'block';

  const months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
  document.getElementById('calMonthLabel').textContent = months[calMonth] + ' ' + calYear;

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

  // Day headers
  ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach(d => {
    grid.innerHTML += '<div class="day-header">' + d + '</div>';
  });

  // First day of month
  const firstDay = new Date(calYear, calMonth - 1, 1);
  let startDow = firstDay.getDay(); // 0=Sun
  startDow = startDow === 0 ? 7 : startDow; // Convert to Mon=1

  const daysInMonth = new Date(calYear, calMonth, 0).getDate();

  // Empty cells before first day
  for (let i = 1; i < startDow; i++) {
    grid.innerHTML += '<div class="day-cell empty"></div>';
  }

  // Day cells
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = calYear + '-' + String(calMonth).padStart(2,'0') + '-' + String(d).padStart(2,'0');
    const dow = new Date(calYear, calMonth - 1, d).getDay();
    const isSunday = dow === 0;
    const dayData = empTaskData[dateStr];
    const totalHrs = dayData ? dayData.total_hrs : 0;
    const isFull = totalHrs >= 9;

    let cls = 'day-cell';
    if (isSunday) cls += ' sunday';
    if (isFull) cls += ' full';

    let content = '<div class="day-num">' + d + '</div>';
    if (isSunday) {
      content += '<div style="font-size:9px;color:var(--muted);">Off</div>';
    } else if (dayData && dayData.tasks.length > 0) {
      let hrsClass = totalHrs >= 9 ? 'full' : (totalHrs >= 6 ? 'warn' : 'ok');
      content += '<span class="day-hrs ' + hrsClass + '">' + totalHrs.toFixed(1) + 'h</span>';
      dayData.tasks.slice(0, 2).forEach(t => {
        content += '<div class="day-task">' + escAttr(t.project_code) + ': ' + escAttr(t.subtask.substring(0,12)) + '</div>';
      });
      if (dayData.tasks.length > 2) content += '<div class="day-task">+' + (dayData.tasks.length-2) + ' more</div>';
    }

    grid.innerHTML += '<div class="' + cls + '" title="' + dateStr + (totalHrs ? ' — ' + totalHrs.toFixed(1) + ' hrs assigned' : '') + '">' + content + '</div>';
  }
}
</script>
</body>
</html>
