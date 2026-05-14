<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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

    if (!$projectId)        $errors[] = 'Select a project.';
    if (!$assignedTo)       $errors[] = 'Select a team member.';
    if (empty($subtasks))   $errors[] = 'Select at least one subtask.';
    if (!$fromDate||!$toDate) $errors[] = 'Date range is required.';
    if ($fromDate && $toDate && $fromDate > $toDate) $errors[] = 'End date must be after start date.';

    if (empty($errors)) {
        $wDays    = workingDays($fromDate, $toDate);
        $maxHours = $wDays * 9;
        $hrsEach  = $maxHours > 0 ? max(0.5, round(($maxHours / count($subtasks)) * 2) / 2) : 0.5;

        $existing = $db->prepare("
            SELECT COALESCE(SUM(hours),0) FROM task_assignments
            WHERE assigned_to=? AND project_id=?
              AND ((from_date<=? AND to_date>=?) OR (from_date<=? AND to_date>=?) OR (from_date>=? AND to_date<=?))
        ");
        $existing->execute([$assignedTo,$projectId,$toDate,$fromDate,$fromDate,$toDate,$fromDate,$toDate]);
        $alreadyHrs = (float)$existing->fetchColumn();
        $newTotal   = $alreadyHrs + ($hrsEach * count($subtasks));

        if ($newTotal > $maxHours) {
            $errors[] = "Cannot assign: total $newTotal hrs exceeds $maxHours hr budget ($wDays day(s) × 9 hrs). Already assigned: $alreadyHrs hrs.";
        }
    }

    if (empty($errors)) {
        $wDays    = workingDays($fromDate, $toDate);
        $maxHours = $wDays * 9;
        $hrsEach  = max(0.5, round(($maxHours / count($subtasks)) * 2) / 2);
        $stmt = $db->prepare("INSERT INTO task_assignments (project_id,assigned_to,assigned_by,subtask,from_date,to_date,hours,notes) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($subtasks as $st) {
            if (in_array($st, SUBTASKS)) $stmt->execute([$projectId,$assignedTo,$uid,$st,$fromDate,$toDate,$hrsEach,$notes?:null]);
        }
        $_SESSION['flash_success'] = count($subtasks)." task(s) assigned successfully.";
        header("Location: tasks.php?project_id=$projectId"); exit;
    } else {
        $errorMsg = implode(' ', $errors);
    }
}

// ── POST: Update status ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'update_status') {
    $taskId = (int)$_POST['task_id'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['Pending','In Progress','Completed','On Hold'])) {
        $db->prepare("UPDATE task_assignments SET status=? WHERE id=? AND assigned_by=?")->execute([$status,$taskId,$uid]);
    }
    header("Location: tasks.php?project_id=".((int)$_POST['project_id'])); exit;
}

// ── POST: Delete task ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete_task') {
    $db->prepare("DELETE FROM task_assignments WHERE id=? AND assigned_by=?")->execute([(int)$_POST['task_id'],$uid]);
    $_SESSION['flash_success'] = "Task removed.";
    header("Location: tasks.php?project_id=".((int)$_POST['project_id'])); exit;
}

// ── Data ─────────────────────────────────────────────────────
$myProjects = $db->prepare("SELECT id, project_name, project_code, status, deadline_date FROM projects WHERE manager_id=? AND status NOT IN ('Cancelled') ORDER BY FIELD(status,'Active','Planning','On Hold','Completed'), deadline_date ASC");
$myProjects->execute([$uid]); $myProjects = $myProjects->fetchAll();

$selectedProject = (int)($_GET['project_id'] ?? ($myProjects[0]['id'] ?? 0));

// Team members
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();
$teamMembers = [];
if ($deptId) {
    $tm = $db->prepare("SELECT u.id, u.name FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? ORDER BY u.name");
    $tm->execute([$deptId,$uid]); $teamMembers = $tm->fetchAll();
}

// Tasks for selected project
$tasks = [];
if ($selectedProject) {
    $t = $db->prepare("SELECT ta.*, u.name AS emp_name FROM task_assignments ta JOIN users u ON ta.assigned_to=u.id WHERE ta.project_id=? AND ta.assigned_by=? ORDER BY ta.from_date ASC, u.name ASC");
    $t->execute([$selectedProject,$uid]); $tasks = $t->fetchAll();
}

// Per-member summary
$memberSummary = [];
foreach ($tasks as $t2) {
    $k = $t2['assigned_to'];
    if (!isset($memberSummary[$k])) $memberSummary[$k] = ['name'=>$t2['emp_name'],'total'=>0,'done'=>0,'count'=>0];
    $memberSummary[$k]['total'] += $t2['hours'];
    if ($t2['status']==='Completed') $memberSummary[$k]['done'] += $t2['hours'];
    $memberSummary[$k]['count']++;
}

$statusBadge = ['Pending'=>'badge-yellow','In Progress'=>'badge-blue','Completed'=>'badge-green','On Hold'=>'badge-gray'];
$statusColor = ['Pending'=>'#d97706','In Progress'=>'#2563eb','Completed'=>'#059669','On Hold'=>'#6b7280'];
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
.proj-tab { display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;text-decoration:none;margin-bottom:2px;transition:background .15s;cursor:pointer;border:none;background:transparent;width:100%;text-align:left; }
.proj-tab:hover { background:var(--surface-2); }
.proj-tab.active { background:var(--brand-light); }
.proj-tab .pt-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
.proj-tab .pt-name { font-size:13px;font-weight:700;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.proj-tab .pt-code { font-size:11px;color:var(--muted);font-family:monospace; }

.member-bar { background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:14px; }
.member-bar-av { width:36px;height:36px;border-radius:50%;background:var(--brand-light);color:var(--brand);font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.member-bar-info { flex:1;min-width:0; }
.member-bar-name { font-size:13px;font-weight:700;color:var(--text); }
.member-bar-meta { font-size:11.5px;color:var(--muted);margin-top:1px; }
.prog-bar { height:5px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:6px; }
.prog-fill { height:100%;border-radius:3px;background:var(--green);transition:width .3s; }

.task-card { background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;transition:box-shadow .15s; }
.task-card:hover { box-shadow:var(--shadow); }
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Tasks</span>
      <span class="page-breadcrumb">Assign &amp; track project tasks</span>
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

    <div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start;">

      <!-- ── Project list ── -->
      <div class="card">
        <div class="card-header"><div><h2>Projects</h2></div></div>
        <div style="padding:8px;">
          <?php foreach($myProjects as $proj):
            $dotColor = $proj['status']==='Active'?'var(--green)':($proj['status']==='Planning'?'var(--yellow)':'var(--muted-light)');
            $isActive = $selectedProject == $proj['id'];
          ?>
          <a href="tasks.php?project_id=<?= $proj['id'] ?>" class="proj-tab <?= $isActive?'active':'' ?>">
            <span class="pt-dot" style="background:<?= $dotColor ?>;"></span>
            <div>
              <div class="pt-name" style="color:<?= $isActive?'var(--brand)':'var(--text)' ?>;"><?= htmlspecialchars($proj['project_name']) ?></div>
              <div class="pt-code"><?= htmlspecialchars($proj['project_code']) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── Right panel ── -->
      <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Page header with Assign button -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
          <div>
            <?php $curProj = null; foreach($myProjects as $p) { if($p['id']==$selectedProject){$curProj=$p;break;} } ?>
            <h2 style="font-size:18px;font-weight:800;color:var(--text);"><?= $curProj?htmlspecialchars($curProj['project_name']):'Select a project' ?></h2>
            <?php if($curProj): ?>
              <div style="font-size:13px;color:var(--muted);margin-top:2px;"><?= count($tasks) ?> task(s) · <?= count($memberSummary) ?> member(s) assigned</div>
            <?php endif; ?>
          </div>
          <?php if($selectedProject): ?>
          <button class="btn btn-primary" onclick="openAssignModal()">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Assign Task
          </button>
          <?php endif; ?>
        </div>

        <!-- Member progress bars -->
        <?php if(!empty($memberSummary)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;">
          <?php foreach($memberSummary as $ms):
            $pct = $ms['total']>0 ? min(100,round(($ms['done']/$ms['total'])*100)) : 0;
          ?>
          <div class="member-bar">
            <div class="member-bar-av"><?= strtoupper(substr($ms['name'],0,1)) ?></div>
            <div class="member-bar-info">
              <div class="member-bar-name"><?= htmlspecialchars($ms['name']) ?></div>
              <div class="member-bar-meta"><?= $ms['count'] ?> task(s) · <?= number_format($ms['total'],1) ?> hrs · <?= $pct ?>% done</div>
              <div class="prog-bar"><div class="prog-fill" style="width:<?= $pct ?>%;"></div></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Task list -->
        <?php if(empty($tasks)): ?>
          <div class="card"><div class="card-body" style="text-align:center;padding:40px 20px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--muted-light)" stroke-width="1.5" style="display:block;margin:0 auto 12px;"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;">No tasks assigned yet</div>
            <div style="font-size:13px;color:var(--muted);">Click "Assign Task" to get started.</div>
          </div></div>
        <?php else: ?>
        <div class="table-wrap">
          <div class="table-toolbar">
            <h2>Assigned Tasks <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($tasks) ?>)</span></h2>
            <div class="search-box">
              <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="tSearch" placeholder="Search…" oninput="filterT(this.value)">
            </div>
          </div>
          <table id="taskTable">
            <thead>
              <tr>
                <th>Member</th>
                <th>Subtask</th>
                <th>Date Range</th>
                <th>Hours</th>
                <th>Status</th>
                <th style="width:80px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($tasks as $t2): ?>
              <tr class="t-row" data-name="<?= htmlspecialchars(strtolower($t2['emp_name'].' '.$t2['subtask'])) ?>">
                <td>
                  <div class="td-user">
                    <div class="td-avatar"><?= strtoupper(substr($t2['emp_name'],0,1)) ?></div>
                    <div class="td-name"><?= htmlspecialchars($t2['emp_name']) ?></div>
                  </div>
                </td>
                <td class="font-semibold text-sm"><?= htmlspecialchars($t2['subtask']) ?></td>
                <td class="text-sm text-muted"><?= date('d M', strtotime($t2['from_date'])) ?> – <?= date('d M Y', strtotime($t2['to_date'])) ?></td>
                <td style="font-weight:700;color:var(--brand);"><?= number_format($t2['hours'],1) ?> hrs</td>
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
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

      </div><!-- /right panel -->
    </div><!-- /grid -->

    <?php endif; ?>
  </div>
</div>
</div>

<!-- ── Assign Task Modal ── -->
<div class="modal-overlay" id="assignModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <div>
        <h3>Assign Task</h3>
        <p style="font-size:12.5px;color:var(--muted);margin-top:2px;">Max 9 hrs per working day. Hours split equally across selected subtasks.</p>
      </div>
      <button class="modal-close" onclick="closeAssignModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" onsubmit="return validateAssign()">
      <input type="hidden" name="action" value="assign_task">
      <input type="hidden" name="project_id" value="<?= $selectedProject ?>">
      <div class="modal-body">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
          <div class="form-group">
            <label>Team Member <span class="req">*</span></label>
            <select name="assigned_to" id="m_assignTo" class="form-control" required>
              <option value="">Select member…</option>
              <?php foreach($teamMembers as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div></div>
          <div class="form-group">
            <label>From Date <span class="req">*</span></label>
            <input type="date" name="from_date" id="m_from" class="form-control" required onchange="updateBudget()">
          </div>
          <div class="form-group">
            <label>To Date <span class="req">*</span></label>
            <input type="date" name="to_date" id="m_to" class="form-control" required onchange="updateBudget()">
          </div>
        </div>

        <!-- Budget indicator -->
        <div id="m_budget" style="display:none;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;font-weight:600;background:var(--brand-light);color:var(--brand);border:1px solid #c7d2fe;">
          <span id="m_budgetLabel"></span>
          <span id="m_perTask" style="font-weight:400;color:var(--muted);margin-left:8px;"></span>
        </div>

        <!-- Subtasks -->
        <div class="form-group" style="margin-bottom:14px;">
          <label>Subtasks <span class="req">*</span></label>
          <div style="display:flex;gap:6px;margin-bottom:6px;">
            <button type="button" class="btn btn-ghost btn-sm" onclick="selAll(true)">Select All</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="selAll(false)">Clear</button>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:10px;">
            <?php foreach(SUBTASKS as $st): ?>
            <label style="display:flex;align-items:center;gap:7px;padding:6px 8px;border-radius:6px;cursor:pointer;font-size:12.5px;font-weight:500;transition:background .12s;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
              <input type="checkbox" name="subtasks[]" value="<?= htmlspecialchars($st) ?>" class="m-chk" style="width:14px;height:14px;accent-color:var(--brand);cursor:pointer;" onchange="updateBudget()">
              <?= htmlspecialchars($st) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional instructions…"></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Assign Tasks
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openAssignModal()  { document.getElementById('assignModal').classList.add('open'); }
function closeAssignModal() { document.getElementById('assignModal').classList.remove('open'); }
document.getElementById('assignModal').addEventListener('click', e => { if(e.target===document.getElementById('assignModal')) closeAssignModal(); });

function workingDays(from, to) {
  if (!from || !to || from > to) return 0;
  let d = new Date(from), e = new Date(to), n = 0;
  while (d <= e) { if (d.getDay() !== 0) n++; d.setDate(d.getDate()+1); }
  return n;
}

function updateBudget() {
  const from  = document.getElementById('m_from').value;
  const to    = document.getElementById('m_to').value;
  const chks  = document.querySelectorAll('.m-chk:checked').length;
  const bDiv  = document.getElementById('m_budget');
  const bLbl  = document.getElementById('m_budgetLabel');
  const bPer  = document.getElementById('m_perTask');
  if (!from || !to || from > to) { bDiv.style.display='none'; return; }
  const wDays = workingDays(from, to);
  const max   = wDays * 9;
  bDiv.style.display = 'block';
  bLbl.textContent = wDays+' working day(s) × 9 hrs = '+max+' hrs budget';
  if (chks > 0) {
    const each = Math.max(0.5, Math.round((max/chks)*2)/2);
    bPer.textContent = '· '+chks+' task(s) → ~'+each+' hrs each';
    bDiv.style.background = (each*chks) > max ? 'var(--red-bg)' : 'var(--brand-light)';
    bDiv.style.color       = (each*chks) > max ? 'var(--red)'    : 'var(--brand)';
    bDiv.style.borderColor = (each*chks) > max ? '#fca5a5'       : '#c7d2fe';
  } else { bPer.textContent = ''; }
}

function selAll(v) { document.querySelectorAll('.m-chk').forEach(c=>c.checked=v); updateBudget(); }

function validateAssign() {
  if (!document.getElementById('m_assignTo').value) { alert('Select a team member.'); return false; }
  if (!document.getElementById('m_from').value || !document.getElementById('m_to').value) { alert('Select a date range.'); return false; }
  if (document.getElementById('m_from').value > document.getElementById('m_to').value) { alert('End date must be after start date.'); return false; }
  if (!document.querySelectorAll('.m-chk:checked').length) { alert('Select at least one subtask.'); return false; }
  return true;
}

function filterT(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.t-row').forEach(r => { r.style.display = !q || r.dataset.name.includes(q) ? '' : 'none'; });
}
</script>
</body>
</html>
