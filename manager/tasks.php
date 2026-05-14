<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Subtask options ──────────────────────────────────────────
const SUBTASKS = [
    'PANEL SEGREGATION',
    'FIELD BOX MARKING',
    'PRELIMINARY CAS',
    'PRELIMINARY ROUTING (PDF OR AUTOCAD)',
    'TRAY SIZING',
    'CABLE LENGTH',
    '3D ROUTING',
    'CAS UPDATE WITH RESPECT TO SCHEMATIC',
    'CABLE & ACCESSORIES EXTRACTION',
    'TRAY & ACCESSORIES EXTRACTION',
    'BOM',
    '2D ROUTING IN AUTOCAD',
    '2D ROUTING APPROVAL STATUS',
    'CHECKING WITH SALES / PM',
    'FINAL DOCUMENTATION',
];

// ── Helper: working days between two dates (excl. Sundays) ───
function workingDays(string $from, string $to): int {
    $days = 0;
    $d = new DateTime($from); $e = new DateTime($to);
    while ($d <= $e) { if ((int)$d->format('N') !== 7) $days++; $d->modify('+1 day'); }
    return $days;
}

// ── POST: Assign task ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'assign_task') {
    $projectId  = (int)$_POST['project_id'];
    $assignedTo = (int)$_POST['assigned_to'];
    $subtasks   = $_POST['subtasks'] ?? [];
    $fromDate   = $_POST['from_date'] ?? '';
    $toDate     = $_POST['to_date']   ?? '';
    $notes      = trim($_POST['notes'] ?? '');
    $errors     = [];

    // Validate
    if (!$projectId)        $errors[] = 'Select a project.';
    if (!$assignedTo)       $errors[] = 'Select a team member.';
    if (empty($subtasks))   $errors[] = 'Select at least one subtask.';
    if (!$fromDate||!$toDate) $errors[] = 'Date range is required.';
    if ($fromDate && $toDate && $fromDate > $toDate) $errors[] = 'End date must be after start date.';

    if (empty($errors)) {
        $wDays    = workingDays($fromDate, $toDate);
        $maxHours = $wDays * 9;

        // Hours per subtask (equal split, rounded to 0.5)
        $hrsEach = $maxHours > 0 ? round(($maxHours / count($subtasks)) * 2) / 2 : 0;
        $hrsEach = max(0.5, $hrsEach);

        // Check existing assigned hours for this employee in this date range
        $existing = $db->prepare("
            SELECT COALESCE(SUM(hours),0) FROM task_assignments
            WHERE assigned_to=? AND project_id=?
              AND ((from_date<=? AND to_date>=?) OR (from_date<=? AND to_date>=?) OR (from_date>=? AND to_date<=?))
        ");
        $existing->execute([$assignedTo,$projectId,$toDate,$fromDate,$fromDate,$toDate,$fromDate,$toDate]);
        $alreadyHrs = (float)$existing->fetchColumn();

        $newTotal = $alreadyHrs + ($hrsEach * count($subtasks));
        if ($newTotal > $maxHours) {
            $errors[] = "Cannot assign: total hours ($newTotal hrs) would exceed the $maxHours hr limit ($wDays working day(s) × 9 hrs). Already assigned: $alreadyHrs hrs.";
        }
    }

    if (empty($errors)) {
        $wDays    = workingDays($fromDate, $toDate);
        $maxHours = $wDays * 9;
        $hrsEach  = max(0.5, round(($maxHours / count($subtasks)) * 2) / 2);

        $stmt = $db->prepare("INSERT INTO task_assignments (project_id,assigned_to,assigned_by,subtask,from_date,to_date,hours,notes) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($subtasks as $st) {
            if (in_array($st, SUBTASKS)) {
                $stmt->execute([$projectId,$assignedTo,$uid,$st,$fromDate,$toDate,$hrsEach,$notes?:null]);
            }
        }
        $_SESSION['flash_success'] = count($subtasks)." task(s) assigned to team member.";
        header("Location: tasks.php?project_id=$projectId"); exit;
    } else {
        $errorMsg = implode(' ', $errors);
    }
}

// ── POST: Update task status ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'update_status') {
    $taskId = (int)$_POST['task_id'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['Pending','In Progress','Completed','On Hold'])) {
        $db->prepare("UPDATE task_assignments SET status=? WHERE id=? AND assigned_by=?")
           ->execute([$status, $taskId, $uid]);
    }
    header("Location: tasks.php?project_id=".((int)$_POST['project_id'])); exit;
}

// ── POST: Delete task ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete_task') {
    $taskId = (int)$_POST['task_id'];
    $db->prepare("DELETE FROM task_assignments WHERE id=? AND assigned_by=?")->execute([$taskId,$uid]);
    $_SESSION['flash_success'] = "Task removed.";
    header("Location: tasks.php?project_id=".((int)$_POST['project_id'])); exit;
}

// ── Data ─────────────────────────────────────────────────────
// Manager's projects
$myProjects = $db->prepare("SELECT id, project_name, project_code, status FROM projects WHERE manager_id=? AND status NOT IN ('Cancelled') ORDER BY status='Active' DESC, deadline_date ASC");
$myProjects->execute([$uid]); $myProjects = $myProjects->fetchAll();

$selectedProject = (int)($_GET['project_id'] ?? ($myProjects[0]['id'] ?? 0));

// Team members (dept-based)
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();

$teamMembers = [];
if ($deptId) {
    $tm = $db->prepare("SELECT u.id, u.name FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? ORDER BY u.name");
    $tm->execute([$deptId,$uid]); $teamMembers = $tm->fetchAll();
}

// Existing tasks for selected project
$tasks = [];
if ($selectedProject) {
    $t = $db->prepare("
        SELECT ta.*, u.name AS emp_name
        FROM task_assignments ta
        JOIN users u ON ta.assigned_to = u.id
        WHERE ta.project_id = ? AND ta.assigned_by = ?
        ORDER BY ta.from_date ASC, u.name ASC
    ");
    $t->execute([$selectedProject,$uid]); $tasks = $t->fetchAll();
}

// Hours summary per employee for selected project
$hoursSummary = [];
foreach ($tasks as $t2) {
    $key = $t2['assigned_to'];
    if (!isset($hoursSummary[$key])) $hoursSummary[$key] = ['name'=>$t2['emp_name'],'total'=>0,'completed'=>0];
    $hoursSummary[$key]['total'] += $t2['hours'];
    if ($t2['status']==='Completed') $hoursSummary[$key]['completed'] += $t2['hours'];
}

$statusBadge = ['Pending'=>'badge-yellow','In Progress'=>'badge-blue','Completed'=>'badge-green','On Hold'=>'badge-gray'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tasks – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.task-row { transition: background .15s; }
.task-row:hover td { background: #fafbff; }
.hr-bar { height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:4px; }
.hr-bar-fill { height:100%;border-radius:3px;transition:width .3s; }
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Tasks</span>
      <span class="page-breadcrumb">Assign &amp; manage project tasks</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?>
      <div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
      <div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if(empty($myProjects)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:60px 20px;">
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No projects assigned</div>
        <div style="font-size:13.5px;color:var(--muted);">Ask admin to assign a project to you first.</div>
      </div></div>
    <?php else: ?>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start;">

      <!-- Project selector -->
      <div class="card">
        <div class="card-header"><div><h2>My Projects</h2></div></div>
        <div style="padding:8px;">
          <?php foreach($myProjects as $proj): ?>
          <a href="tasks.php?project_id=<?= $proj['id'] ?>"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;margin-bottom:2px;background:<?= $selectedProject==$proj['id']?'var(--brand-light)':'transparent' ?>;transition:background .15s;"
             onmouseover="if(<?= $selectedProject ?>!==<?= $proj['id'] ?>)this.style.background='var(--surface-2)'"
             onmouseout="if(<?= $selectedProject ?>!==<?= $proj['id'] ?>)this.style.background='transparent'">
            <div style="width:8px;height:8px;border-radius:50%;background:<?= $proj['status']==='Active'?'var(--green)':($proj['status']==='Planning'?'var(--yellow)':'var(--muted-light)') ?>;flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:13px;font-weight:700;color:<?= $selectedProject==$proj['id']?'var(--brand)':'var(--text)' ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($proj['project_name']) ?></div>
              <div style="font-size:11px;color:var(--muted);font-family:monospace;"><?= htmlspecialchars($proj['project_code']) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right panel -->
      <div>
        <?php
        $curProj = null;
        foreach ($myProjects as $proj) { if ($proj['id']==$selectedProject) { $curProj=$proj; break; } }
        ?>

        <!-- Assign task form -->
        <div class="card" style="margin-bottom:20px;">
          <div class="card-header">
            <div>
              <h2>Assign Task<?= $curProj?' — '.htmlspecialchars($curProj['project_name']):'' ?></h2>
              <p>Select team member, subtasks, date range. Max 9 hrs/working day.</p>
            </div>
          </div>
          <div class="card-body">
            <form method="POST" id="assignForm" onsubmit="return validateAssign()">
              <input type="hidden" name="action" value="assign_task">
              <input type="hidden" name="project_id" value="<?= $selectedProject ?>">

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div class="form-group">
                  <label>Team Member <span class="req">*</span></label>
                  <select name="assigned_to" id="assignTo" class="form-control" required>
                    <option value="">Select member…</option>
                    <?php foreach($teamMembers as $m): ?>
                      <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                  <div class="form-group">
                    <label>From Date <span class="req">*</span></label>
                    <input type="date" name="from_date" id="fromDate" class="form-control" required onchange="updateHrBudget()">
                  </div>
                  <div class="form-group">
                    <label>To Date <span class="req">*</span></label>
                    <input type="date" name="to_date" id="toDate" class="form-control" required onchange="updateHrBudget()">
                  </div>
                </div>
              </div>

              <!-- Hour budget indicator -->
              <div id="hrBudget" style="display:none;background:var(--brand-light);border:1px solid #c7d2fe;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span style="font-weight:700;color:var(--brand);" id="hrBudgetLabel"></span>
                  <span style="font-size:12px;color:var(--muted);" id="hrPerTask"></span>
                </div>
              </div>

              <!-- Subtasks -->
              <div class="form-group" style="margin-bottom:16px;">
                <label>Subtasks <span class="req">*</span> <span style="font-size:11.5px;color:var(--muted);font-weight:400;">(select multiple — hours split equally)</span></label>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:6px;margin-top:6px;max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:10px;">
                  <?php foreach(SUBTASKS as $st): ?>
                  <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:6px;cursor:pointer;transition:background .15s;font-size:13px;font-weight:500;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
                    <input type="checkbox" name="subtasks[]" value="<?= htmlspecialchars($st) ?>" class="subtask-chk" style="width:15px;height:15px;accent-color:var(--brand);cursor:pointer;" onchange="updateHrBudget()">
                    <?= htmlspecialchars($st) ?>
                  </label>
                  <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:8px;margin-top:6px;">
                  <button type="button" class="btn btn-ghost btn-sm" onclick="selectAllSubtasks(true)">Select All</button>
                  <button type="button" class="btn btn-ghost btn-sm" onclick="selectAllSubtasks(false)">Clear All</button>
                </div>
              </div>

              <div class="form-group" style="margin-bottom:16px;">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional instructions…"></textarea>
              </div>

              <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Assign Tasks
              </button>
            </form>
          </div>
        </div>

        <!-- Hours summary per member -->
        <?php if(!empty($hoursSummary)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:20px;">
          <?php foreach($hoursSummary as $hs): ?>
          <div class="card" style="padding:14px 16px;">
            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:8px;"><?= htmlspecialchars($hs['name']) ?></div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:4px;">
              <span>Assigned</span><span style="font-weight:700;color:var(--brand);"><?= number_format($hs['total'],1) ?> hrs</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px;">
              <span>Completed</span><span style="font-weight:700;color:var(--green);"><?= number_format($hs['completed'],1) ?> hrs</span>
            </div>
            <?php $pct = $hs['total']>0?min(100,round(($hs['completed']/$hs['total'])*100)):0; ?>
            <div class="hr-bar"><div class="hr-bar-fill" style="width:<?= $pct ?>%;background:var(--green);"></div></div>
            <div style="font-size:11px;color:var(--muted);margin-top:3px;text-align:right;"><?= $pct ?>% done</div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Task list -->
        <div class="table-wrap">
          <div class="table-toolbar">
            <h2>Assigned Tasks <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($tasks) ?>)</span></h2>
            <div class="search-box">
              <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="tSearch" placeholder="Search tasks…" oninput="filterTasks(this.value)">
            </div>
          </div>
          <table id="taskTable">
            <thead>
              <tr>
                <th>Member</th>
                <th>Subtask</th>
                <th>From</th>
                <th>To</th>
                <th>Hours</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($tasks)): ?>
                <tr class="empty-row"><td colspan="7">No tasks assigned yet for this project.</td></tr>
              <?php else: foreach($tasks as $t2): ?>
              <tr class="task-row" data-name="<?= htmlspecialchars(strtolower($t2['emp_name'].' '.$t2['subtask'])) ?>">
                <td>
                  <div class="td-user">
                    <div class="td-avatar"><?= strtoupper(substr($t2['emp_name'],0,1)) ?></div>
                    <div class="td-name"><?= htmlspecialchars($t2['emp_name']) ?></div>
                  </div>
                </td>
                <td class="font-semibold text-sm"><?= htmlspecialchars($t2['subtask']) ?></td>
                <td class="text-sm text-muted"><?= date('d M Y', strtotime($t2['from_date'])) ?></td>
                <td class="text-sm text-muted"><?= date('d M Y', strtotime($t2['to_date'])) ?></td>
                <td class="font-semibold" style="color:var(--brand);"><?= number_format($t2['hours'],1) ?> hrs</td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="task_id" value="<?= $t2['id'] ?>">
                    <input type="hidden" name="project_id" value="<?= $selectedProject ?>">
                    <select name="status" class="form-control" style="font-size:12px;padding:4px 8px;width:auto;" onchange="this.form.submit()">
                      <?php foreach(['Pending','In Progress','Completed','On Hold'] as $s): ?>
                        <option <?= $t2['status']===$s?'selected':'' ?>><?= $s ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
                <td>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this task?')">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" value="<?= $t2['id'] ?>">
                    <input type="hidden" name="project_id" value="<?= $selectedProject ?>">
                    <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Remove</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /right panel -->
    </div><!-- /grid -->

    <?php endif; ?>
  </div>
</div>
</div>

<script>
// ── Working days calc (client-side, excl. Sundays) ────────────
function workingDays(from, to) {
  if (!from || !to || from > to) return 0;
  let days = 0, d = new Date(from), e = new Date(to);
  while (d <= e) { if (d.getDay() !== 0) days++; d.setDate(d.getDate()+1); }
  return days;
}

function updateHrBudget() {
  const from  = document.getElementById('fromDate').value;
  const to    = document.getElementById('toDate').value;
  const chks  = document.querySelectorAll('.subtask-chk:checked').length;
  const budget= document.getElementById('hrBudget');
  const label = document.getElementById('hrBudgetLabel');
  const perT  = document.getElementById('hrPerTask');

  if (!from || !to || from > to) { budget.style.display='none'; return; }
  const wDays   = workingDays(from, to);
  const maxHrs  = wDays * 9;
  budget.style.display = 'block';
  label.textContent = wDays+' working day(s) × 9 hrs = '+maxHrs+' hrs total budget';

  if (chks > 0) {
    const each = Math.max(0.5, Math.round((maxHrs / chks) * 2) / 2);
    perT.textContent = chks+' task(s) selected → ~'+each+' hrs each';
    budget.style.background = (each * chks) > maxHrs ? 'var(--red-bg)' : 'var(--brand-light)';
    budget.style.borderColor = (each * chks) > maxHrs ? '#fca5a5' : '#c7d2fe';
  } else {
    perT.textContent = 'Select subtasks to see hour split';
  }
}

function selectAllSubtasks(sel) {
  document.querySelectorAll('.subtask-chk').forEach(c => c.checked = sel);
  updateHrBudget();
}

function validateAssign() {
  const to   = document.getElementById('assignTo').value;
  const from = document.getElementById('fromDate').value;
  const end  = document.getElementById('toDate').value;
  const chks = document.querySelectorAll('.subtask-chk:checked').length;
  if (!to)   { alert('Select a team member.'); return false; }
  if (!from||!end) { alert('Select a date range.'); return false; }
  if (from > end)  { alert('End date must be after start date.'); return false; }
  if (!chks) { alert('Select at least one subtask.'); return false; }
  return true;
}

function filterTasks(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.task-row').forEach(r => { r.style.display = !q || r.dataset.name.includes(q) ? '' : 'none'; });
}
</script>
</body>
</html>
