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
</script>
</body>
</html>
