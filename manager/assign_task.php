<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/task_config.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$projectId = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
if (!$projectId) { header("Location: tasks.php"); exit; }

$project = $db->prepare("SELECT * FROM projects WHERE id = ? AND manager_id = ?");
$project->execute([$projectId, $uid]);
$project = $project->fetch();
if (!$project) {
    $_SESSION['flash_error'] = "Project not found or not assigned to you.";
    header("Location: tasks.php"); exit;
}

// Team members
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();
$teamMembers = [];
if ($deptId) {
    $tm = $db->prepare("SELECT u.id, u.name, u.email, e.job_title, e.employee_id AS emp_code FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? AND u.status='active' ORDER BY u.name");
    $tm->execute([$deptId, $uid]); $teamMembers = $tm->fetchAll();
}

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_task') {
    $assignedTo = (int)($_POST['assigned_to'] ?? 0);
    $subtasks   = array_filter((array)($_POST['subtasks'] ?? []), fn($s) => in_array($s, SUBTASKS));
    $fromDate   = trim($_POST['from_date'] ?? '');
    $toDate     = trim($_POST['to_date'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');
    $errors     = [];

    if (!$assignedTo) $errors[] = 'Select a team member.';
    if (empty($subtasks)) $errors[] = 'Select at least one subtask.';
    if (!$fromDate || !$toDate) $errors[] = 'Date range required.';
    if ($fromDate && $toDate && $fromDate > $toDate) $errors[] = 'To date must be after From date.';

    if (empty($errors)) {
        $wDays = workingDays($fromDate, $toDate);
        $maxHrs = $wDays * 9;
        if ($maxHrs <= 0) { $errors[] = 'No working days in range.'; }
        else {
            $ex = $db->prepare("SELECT COALESCE(SUM(hours),0) FROM task_assignments WHERE assigned_to=? AND project_id=? AND from_date<=? AND to_date>=?");
            $ex->execute([$assignedTo, $projectId, $toDate, $fromDate]);
            $already = (float)$ex->fetchColumn();
            $each = max(0.5, round(($maxHrs / count($subtasks)) * 2) / 2);
            if (($already + $each * count($subtasks)) > $maxHrs) {
                $errors[] = "Over budget: " . ($already + $each * count($subtasks)) . " hrs exceeds $maxHrs hr limit.";
            }
        }
    }

    if (empty($errors)) {
        $wDays = workingDays($fromDate, $toDate);
        $each = max(0.5, round((($wDays * 9) / count($subtasks)) * 2) / 2);
        $stmt = $db->prepare("INSERT INTO task_assignments (project_id,assigned_to,assigned_by,subtask,from_date,to_date,hours,notes) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($subtasks as $st) $stmt->execute([$projectId, $assignedTo, $uid, $st, $fromDate, $toDate, $each, $notes ?: null]);
        $_SESSION['flash_success'] = count($subtasks) . " task(s) assigned.";
        header("Location: tasks.php?project_id=$projectId"); exit;
    } else { $errorMsg = implode(' ', $errors); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Assign Task - HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Assign Task</span>
      <span class="page-breadcrumb"><a href="tasks.php?project_id=<?= $projectId ?>" style="color:var(--muted);text-decoration:none;">Tasks</a> / <?= htmlspecialchars($project['project_name']) ?></span>
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

    <!-- Project banner -->
    <div style="background:linear-gradient(135deg,var(--brand),var(--brand-mid));border-radius:var(--radius-lg);padding:18px 24px;color:#fff;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <div>
        <div style="font-size:11px;font-weight:700;opacity:.8;text-transform:uppercase;">Project</div>
        <div style="font-size:17px;font-weight:800;margin-top:2px;"><?= htmlspecialchars($project['project_name']) ?></div>
        <div style="font-size:12.5px;opacity:.85;margin-top:3px;">
          <code style="background:rgba(255,255,255,.2);padding:1px 7px;border-radius:4px;"><?= htmlspecialchars($project['project_code']) ?></code>
          - Deadline: <?= date('d M Y', strtotime($project['deadline_date'])) ?>
          - <?= number_format($project['total_hours'],1) ?> total hrs
        </div>
      </div>
      <a href="tasks.php?project_id=<?= $projectId ?>" class="btn" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);">Back to Tasks</a>
    </div>

    <?php if(empty($teamMembers)): ?>
      <div class="alert alert-warning" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">No team members found in your department.</div>
    <?php else: ?>

    <div class="card">
      <div class="card-header"><div><h2>New Task Assignment</h2><p>Max 9 hrs per working day. Hours split equally across subtasks.</p></div></div>
      <div class="card-body">
        <form method="POST" onsubmit="return validateForm()">
          <input type="hidden" name="action" value="assign_task">
          <input type="hidden" name="project_id" value="<?= $projectId ?>">

          <!-- Team member -->
          <div class="form-group" style="margin-bottom:18px;">
            <label>Team Member <span class="req">*</span></label>
            <select name="assigned_to" class="form-control" style="max-width:400px;" required>
              <option value="">-- Select Team Member --</option>
              <?php foreach($teamMembers as $m): ?>
                <option value="<?= $m['id'] ?>" <?= (isset($_POST['assigned_to'])&&(int)$_POST['assigned_to']===$m['id'])?'selected':'' ?>>
                  <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['job_title'] ?: $m['emp_code'] ?: $m['email']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Date range -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;max-width:400px;">
            <div class="form-group">
              <label>From Date <span class="req">*</span></label>
              <input type="date" name="from_date" id="fromDate" class="form-control" value="<?= htmlspecialchars($_POST['from_date'] ?? '') ?>" required onchange="updateBudget()">
            </div>
            <div class="form-group">
              <label>To Date <span class="req">*</span></label>
              <input type="date" name="to_date" id="toDate" class="form-control" value="<?= htmlspecialchars($_POST['to_date'] ?? '') ?>" required onchange="updateBudget()">
            </div>
          </div>

          <!-- Budget -->
          <div id="budgetBox" style="display:none;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;border:1px solid #c7d2fe;background:var(--brand-light);max-width:500px;">
            <span style="font-weight:700;color:var(--brand);" id="budgetLabel"></span>
            <span style="color:var(--muted);font-size:12px;margin-left:6px;" id="budgetSplit"></span>
          </div>

          <!-- Subtasks -->
          <div class="form-group" style="margin-bottom:16px;">
            <label>Subtasks <span class="req">*</span></label>
            <div style="display:flex;gap:6px;margin:5px 0 8px;">
              <button type="button" class="btn btn-ghost btn-sm" onclick="selAll(true)">Select All</button>
              <button type="button" class="btn btn-ghost btn-sm" onclick="selAll(false)">Clear</button>
              <span id="selCount" style="font-size:12px;color:var(--muted);align-self:center;"></span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px;border:1px solid var(--border);border-radius:8px;padding:10px;max-height:280px;overflow-y:auto;">
              <?php foreach(SUBTASKS as $st): $chk = in_array($st,(array)($_POST['subtasks']??[])); ?>
              <label style="display:flex;align-items:center;gap:7px;padding:7px 8px;border-radius:6px;cursor:pointer;font-size:12.5px;font-weight:500;transition:background .12s;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
                <input type="checkbox" name="subtasks[]" value="<?= htmlspecialchars($st) ?>" class="st-chk" style="width:14px;height:14px;accent-color:var(--brand);cursor:pointer;" <?= $chk?'checked':'' ?> onchange="updateBudget()">
                <?= htmlspecialchars($st) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Notes -->
          <div class="form-group" style="margin-bottom:20px;max-width:500px;">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional instructions..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          </div>

          <div style="display:flex;gap:10px;">
            <a href="tasks.php?project_id=<?= $projectId ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Assign Tasks</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
function workingDays(f,t){if(!f||!t||f>t)return 0;let d=new Date(f),e=new Date(t),n=0;while(d<=e){if(d.getDay()!==0)n++;d.setDate(d.getDate()+1);}return n;}
function updateBudget(){
  const f=document.getElementById('fromDate').value,t=document.getElementById('toDate').value;
  const chks=document.querySelectorAll('.st-chk:checked').length;
  const box=document.getElementById('budgetBox'),lbl=document.getElementById('budgetLabel'),spl=document.getElementById('budgetSplit');
  document.getElementById('selCount').textContent=chks>0?'('+chks+' selected)':'';
  if(!f||!t||f>t){box.style.display='none';return;}
  const w=workingDays(f,t),max=w*9;
  box.style.display='block';
  lbl.textContent=w+' day(s) x 9 hrs = '+max+' hrs budget';
  if(chks>0){const each=Math.max(0.5,Math.round((max/chks)*2)/2);spl.textContent='-> '+chks+' subtask(s) ~'+each+' hrs each';
    const over=(each*chks)>max;box.style.background=over?'var(--red-bg)':'var(--brand-light)';box.style.borderColor=over?'#fca5a5':'#c7d2fe';lbl.style.color=over?'var(--red)':'var(--brand)';
  }else{spl.textContent='';}
}
function selAll(v){document.querySelectorAll('.st-chk').forEach(c=>c.checked=v);updateBudget();}
function validateForm(){
  if(!document.querySelector('[name="assigned_to"]').value){alert('Select a team member.');return false;}
  if(!document.getElementById('fromDate').value||!document.getElementById('toDate').value){alert('Select dates.');return false;}
  if(!document.querySelectorAll('.st-chk:checked').length){alert('Select subtasks.');return false;}
  return true;
}
</script>
</body>
</html>
