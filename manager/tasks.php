<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/task_config.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST: Update status ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $projId = (int)($_POST['project_id'] ?? 0);
    if ($taskId && in_array($status, ['Pending','In Progress','Completed','On Hold'])) {
        $chk = $db->prepare("SELECT id FROM task_assignments WHERE id=? AND assigned_by=?");
        $chk->execute([$taskId,$uid]);
        if ($chk->fetch()) {
            $db->prepare("UPDATE task_assignments SET status=? WHERE id=?")->execute([$status,$taskId]);
        }
    }
    header("Location: tasks.php".($projId?"?project_id=$projId":'')); exit;
}

// ── POST: Delete ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $projId = (int)($_POST['project_id'] ?? 0);
    if ($taskId) {
        $chk = $db->prepare("SELECT id FROM task_assignments WHERE id=? AND assigned_by=?");
        $chk->execute([$taskId,$uid]);
        if ($chk->fetch()) {
            $db->prepare("DELETE FROM task_assignments WHERE id=?")->execute([$taskId]);
            $_SESSION['flash_success'] = "Task removed.";
        }
    }
    header("Location: tasks.php".($projId?"?project_id=$projId":'')); exit;
}

// ── Data ─────────────────────────────────────────────────────
$myProjects = $db->prepare("
    SELECT id, project_name, project_code, status, deadline_date, total_hours
    FROM projects WHERE manager_id=? AND status NOT IN ('Cancelled')
    ORDER BY FIELD(status,'Active','Planning','On Hold','Completed'), deadline_date ASC
");
$myProjects->execute([$uid]);
$myProjects = $myProjects->fetchAll();

$selectedProject = (int)($_GET['project_id'] ?? ($myProjects[0]['id'] ?? 0));
$filterStatus    = $_GET['status']    ?? '';
$filterMember    = (int)($_GET['member_id'] ?? 0);
$search          = trim($_GET['q']    ?? '');

// Tasks
$tasks = [];
$memberSummary = [];
$statusCounts  = [];
$curProj = null;

if ($selectedProject) {
    foreach ($myProjects as $p) { if ($p['id']==$selectedProject) { $curProj=$p; break; } }

    $where  = ['ta.project_id=?','ta.assigned_by=?'];
    $params = [$selectedProject,$uid];
    if ($filterStatus) { $where[]="ta.status=?"; $params[]=$filterStatus; }
    if ($filterMember) { $where[]="ta.assigned_to=?"; $params[]=$filterMember; }
    if ($search)       { $where[]="(ta.subtask LIKE ? OR u.name LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }

    $stmt = $db->prepare("
        SELECT ta.*, u.name AS emp_name, u.email AS emp_email,
               e.employee_id AS emp_code, e.job_title
        FROM task_assignments ta
        JOIN users u ON ta.assigned_to=u.id
        LEFT JOIN employees e ON e.email=u.email
        WHERE ".implode(' AND ',$where)."
        ORDER BY ta.from_date ASC, u.name ASC
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    // Member summary
    $ms = $db->prepare("
        SELECT ta.assigned_to, u.name AS emp_name,
               COUNT(*) AS cnt, SUM(ta.hours) AS total,
               SUM(CASE WHEN ta.status='Completed' THEN ta.hours ELSE 0 END) AS done
        FROM task_assignments ta JOIN users u ON ta.assigned_to=u.id
        WHERE ta.project_id=? AND ta.assigned_by=?
        GROUP BY ta.assigned_to, u.name ORDER BY u.name
    ");
    $ms->execute([$selectedProject,$uid]);
    $memberSummary = $ms->fetchAll();

    foreach (['Pending','In Progress','Completed','On Hold'] as $s) {
        $c = $db->prepare("SELECT COUNT(*) FROM task_assignments WHERE project_id=? AND assigned_by=? AND status=?");
        $c->execute([$selectedProject,$uid,$s]); $statusCounts[$s]=(int)$c->fetchColumn();
    }
}

// Team members for filter
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();
$teamMembers = [];
if ($deptId) {
    $tm = $db->prepare("SELECT u.id, u.name FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? AND u.status='active' ORDER BY u.name");
    $tm->execute([$deptId,$uid]); $teamMembers = $tm->fetchAll();
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
.prog-bar { height:5px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:5px; }
.prog-fill { height:100%;border-radius:3px;background:var(--green);transition:width .3s; }
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Tasks</span>
      <span class="page-breadcrumb"><?= $curProj ? htmlspecialchars($curProj['project_name']) : 'Select a project' ?></span>
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
        <div style="font-size:13.5px;color:var(--muted);">Ask admin to assign a project to you.</div>
      </div></div>
    <?php else: ?>

    <!-- Project selector dropdown -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
      <div style="position:relative;min-width:320px;max-width:480px;flex:1;">
        <select id="projectSelect" class="form-control" style="font-size:14px;font-weight:600;padding:10px 16px;border-radius:10px;border:1.5px solid var(--border);appearance:none;cursor:pointer;background:var(--surface);"
          onchange="location.href='tasks.php?project_id='+this.value">
          <option value="">— Select Project —</option>
          <?php foreach($myProjects as $proj):
            $dot = $proj['status']==='Active'?'🟢':($proj['status']==='Planning'?'🟡':'⚪');
          ?>
          <option value="<?= $proj['id'] ?>" <?= $selectedProject==$proj['id']?'selected':'' ?>>
            <?= $dot ?> <?= htmlspecialchars($proj['project_name']) ?> (<?= htmlspecialchars($proj['project_code']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <svg style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <?php if($selectedProject): ?>
        <a href="assign_task.php?project_id=<?= $selectedProject ?>" class="btn btn-primary">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Assign Task
        </a>
      <?php endif; ?>
    </div>

    <?php if($selectedProject && $curProj): ?>

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
      <div>
        <h1 style="font-size:20px;font-weight:800;color:var(--text);"><?= htmlspecialchars($curProj['project_name']) ?></h1>
        <div style="font-size:13px;color:var(--muted);margin-top:3px;">
          Deadline: <?= date('d M Y', strtotime($curProj['deadline_date'])) ?>
          &nbsp;·&nbsp; <?= number_format($curProj['total_hours'],1) ?> total hrs
          &nbsp;·&nbsp; <?= array_sum($statusCounts) ?> task(s)
        </div>
      </div>
    </div>

    <!-- Status stat pills -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
      <?php foreach(['Pending'=>'#d97706','In Progress'=>'#2563eb','Completed'=>'#059669','On Hold'=>'#6b7280'] as $s=>$c): ?>
      <a href="tasks.php?project_id=<?= $selectedProject ?>&status=<?= urlencode($s) ?>"
         style="display:flex;align-items:center;gap:8px;padding:10px 16px;background:var(--surface);border:1.5px solid <?= $filterStatus===$s?$c:'var(--border)' ?>;border-radius:10px;text-decoration:none;transition:all .15s;">
        <span style="font-size:20px;font-weight:800;color:<?= $c ?>;"><?= $statusCounts[$s]??0 ?></span>
        <span style="font-size:12px;font-weight:600;color:var(--muted);"><?= $s ?></span>
      </a>
      <?php endforeach; ?>
      <?php if($filterStatus): ?>
        <a href="tasks.php?project_id=<?= $selectedProject ?>" class="btn btn-ghost btn-sm" style="align-self:center;">Clear filter</a>
      <?php endif; ?>
    </div>

    <!-- Member progress -->
    <?php if(!empty($memberSummary)): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:20px;">
      <?php foreach($memberSummary as $ms):
        $pct = $ms['total']>0 ? min(100,round(($ms['done']/$ms['total'])*100)) : 0;
      ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
          <div style="width:30px;height:30px;border-radius:50%;background:var(--brand-light);color:var(--brand);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= strtoupper(substr($ms['emp_name'],0,1)) ?></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:12.5px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($ms['emp_name']) ?></div>
            <div style="font-size:11px;color:var(--muted);"><?= $ms['cnt'] ?> task(s) · <?= number_format($ms['total'],1) ?> hrs</div>
          </div>
        </div>
        <div class="prog-bar"><div class="prog-fill" style="width:<?= $pct ?>%;"></div></div>
        <div style="font-size:11px;color:var(--muted);text-align:right;margin-top:3px;"><?= $pct ?>% done</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;">
      <input type="hidden" name="project_id" value="<?= $selectedProject ?>">
      <div class="search-box" style="min-width:200px;">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search task or member…">
      </div>
      <select name="member_id" class="form-control" style="font-size:13px;padding:7px 12px;min-width:160px;">
        <option value="">All Members</option>
        <?php foreach($teamMembers as $m): ?>
          <option value="<?= $m['id'] ?>" <?= $filterMember==$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="tasks.php?project_id=<?= $selectedProject ?>" class="btn btn-secondary btn-sm">Reset</a>
    </form>

    <!-- Task table -->
    <?php if(empty($tasks)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:40px 20px;">
        <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;">No tasks found</div>
        <div style="font-size:13px;color:var(--muted);"><?= ($filterStatus||$filterMember||$search)?'Try adjusting filters.':'Click "Assign Task" to get started.' ?></div>
      </div></div>
    <?php else: ?>
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Tasks <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($tasks) ?>)</span></h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Subtask</th>
            <th>Date Range</th>
            <th style="text-align:center;">Assigned Hrs</th>
            <th style="text-align:center;">Utilized Hrs</th>
            <th>Progress</th>
            <th>Status</th>
            <th style="width:90px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($tasks as $t):
            $utilized = $t['status']==='Completed' ? $t['hours'] : ($t['status']==='In Progress' ? round($t['hours']*0.5,1) : 0);
            $pct      = $t['hours']>0 ? min(100,round(($utilized/$t['hours'])*100)) : 0;
            $barColor = $t['status']==='Completed'?'var(--green)':($t['status']==='In Progress'?'var(--blue)':'var(--border)');
          ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($t['emp_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($t['emp_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($t['emp_code'] ?: $t['emp_email']) ?></div>
                </div>
              </div>
            </td>
            <td class="font-semibold text-sm"><?= htmlspecialchars($t['subtask']) ?></td>
            <td class="text-sm text-muted"><?= date('d M', strtotime($t['from_date'])) ?> – <?= date('d M Y', strtotime($t['to_date'])) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--brand);"><?= number_format($t['hours'],1) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--green-text);"><?= number_format($utilized,1) ?></td>
            <td style="min-width:90px;">
              <div style="display:flex;align-items:center;gap:5px;">
                <div style="flex:1;height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
                  <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:3px;"></div>
                </div>
                <span style="font-size:11px;color:var(--muted);flex-shrink:0;"><?= $pct ?>%</span>
              </div>
            </td>
            <td>
              <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <input type="hidden" name="project_id" value="<?= $selectedProject ?>">
                <select name="status" class="form-control" style="font-size:12px;padding:4px 8px;width:auto;" onchange="this.form.submit()">
                  <?php foreach(['Pending','In Progress','Completed','On Hold'] as $s): ?>
                    <option <?= $t['status']===$s?'selected':'' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('Remove this task?')">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
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

    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
