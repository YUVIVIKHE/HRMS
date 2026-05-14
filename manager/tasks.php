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
$filterMember    = (int)($_GET['member_id'] ?? 0);
$search          = trim($_GET['q'] ?? '');

// Tasks
$tasks = [];
$memberSummary = [];
$curProj = null;

if ($selectedProject) {
    foreach ($myProjects as $p) { if ($p['id']==$selectedProject) { $curProj=$p; break; } }

    $where  = ['ta.project_id=?','ta.assigned_by=?'];
    $params = [$selectedProject,$uid];
    if ($filterMember) { $where[]="ta.assigned_to=?"; $params[]=$filterMember; }
    if ($search) { $where[]="(ta.subtask LIKE ? OR u.name LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }

    $stmt = $db->prepare("
        SELECT ta.*, u.name AS emp_name, u.email AS emp_email,
               e.employee_id AS emp_code, e.job_title,
               COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
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
               COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl JOIN task_assignments t2 ON tpl.task_id=t2.id WHERE t2.assigned_to=ta.assigned_to AND t2.project_id=?), 0) AS done
        FROM task_assignments ta JOIN users u ON ta.assigned_to=u.id
        WHERE ta.project_id=? AND ta.assigned_by=?
        GROUP BY ta.assigned_to, u.name ORDER BY u.name
    ");
    $ms->execute([$selectedProject, $selectedProject, $uid]);
    $memberSummary = $ms->fetchAll();
}

// Team members for filter
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();
$teamMembers = [];
if ($deptId) {
    $tm = $db->prepare("SELECT u.id, u.name FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? AND u.status='active' ORDER BY u.name");
    $tm->execute([$deptId,$uid]); $teamMembers = $tm->fetchAll();
}
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
.task-card{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:18px 20px;transition:all .15s;}
.task-card:hover{border-color:var(--brand);box-shadow:0 2px 12px rgba(0,0,0,.04);}
.member-chip{display:inline-flex;align-items:center;gap:6px;background:var(--surface-2);border-radius:20px;padding:4px 10px 4px 4px;font-size:12px;font-weight:600;color:var(--text);}
.member-chip .avatar{width:22px;height:22px;border-radius:50%;background:var(--brand-light);color:var(--brand);font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.prog-bar{height:5px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:5px;}
.prog-fill{height:100%;border-radius:3px;background:var(--green);transition:width .3s;}
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

      <!-- Filter bar -->
      <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:24px;">
        <div style="position:relative;min-width:240px;flex:0 1 300px;">
          <select name="project_id" class="form-control" style="font-size:13px;font-weight:600;padding:10px 36px 10px 14px;appearance:none;cursor:pointer;" onchange="this.form.submit()">
            <option value="">— Select Project —</option>
            <?php foreach($myProjects as $proj): ?>
            <option value="<?= $proj['id'] ?>" <?= $selectedProject==$proj['id']?'selected':'' ?>>
              <?= htmlspecialchars($proj['project_name']) ?> (<?= htmlspecialchars($proj['project_code']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <svg style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>

        <?php if($selectedProject): ?>
        <div class="search-box" style="min-width:180px;flex:0 1 220px;">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search task or member…">
        </div>
        <select name="member_id" class="form-control" style="font-size:13px;padding:10px 14px;width:auto;min-width:150px;">
          <option value="">All Members</option>
          <?php foreach($teamMembers as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterMember==$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="tasks.php?project_id=<?= $selectedProject ?>" class="btn btn-ghost btn-sm">Reset</a>
        <a href="assign_task.php?project_id=<?= $selectedProject ?>" class="btn btn-primary btn-sm" style="margin-left:auto;">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Assign Task
        </a>
        <?php endif; ?>
      </form>

      <?php if($selectedProject && $curProj): ?>
      <!-- Project info -->
      <div style="background:linear-gradient(135deg,var(--brand),var(--brand-mid));border-radius:12px;padding:20px 24px;color:#fff;margin-bottom:24px;">
        <div style="font-size:18px;font-weight:800;"><?= htmlspecialchars($curProj['project_name']) ?></div>
        <div style="font-size:13px;opacity:.85;margin-top:4px;">
          Deadline: <?= date('d M Y', strtotime($curProj['deadline_date'])) ?>
          · <?= number_format($curProj['total_hours'],1) ?> total hrs
          · <?= count($tasks) ?> task(s)
        </div>
      </div>

      <!-- Member progress cards -->
      <?php if(!empty($memberSummary)): ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:24px;">
        <?php foreach($memberSummary as $ms):
          $pct = $ms['total']>0 ? min(100,round(($ms['done']/$ms['total'])*100)) : 0;
        ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <div style="width:30px;height:30px;border-radius:50%;background:var(--brand-light);color:var(--brand);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;"><?= strtoupper(substr($ms['emp_name'],0,1)) ?></div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:12.5px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($ms['emp_name']) ?></div>
              <div style="font-size:11px;color:var(--muted);"><?= $ms['cnt'] ?> task(s) · <?= number_format($ms['total'],1) ?> hrs</div>
            </div>
          </div>
          <div class="prog-bar"><div class="prog-fill" style="width:<?= $pct ?>%;"></div></div>
          <div style="font-size:11px;color:var(--muted);text-align:right;margin-top:3px;"><?= number_format($ms['done'],1) ?> / <?= number_format($ms['total'],1) ?> hrs (<?= $pct ?>%)</div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Task cards -->
      <?php if($selectedProject && empty($tasks)): ?>
        <div class="card"><div class="card-body" style="text-align:center;padding:56px 20px;">
          <div style="width:48px;height:48px;margin:0 auto 14px;border-radius:50%;background:var(--brand-light);display:flex;align-items:center;justify-content:center;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--brand)" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:5px;">No tasks found</div>
          <div style="font-size:13px;color:var(--muted);"><?= ($filterMember||$search)?'Try adjusting your filters.':'Click "Assign Task" to get started.' ?></div>
        </div></div>
      <?php elseif($selectedProject): ?>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach($tasks as $t):
          $utilized = (float)$t['utilized_hours'];
          $assigned = (float)$t['hours'];
          $pct = $assigned > 0 ? min(100, round(($utilized/$assigned)*100)) : 0;
          $barColor = $pct >= 100 ? 'var(--green)' : 'var(--blue)';
          $isOverdue = $t['to_date'] < date('Y-m-d') && $pct < 100;
        ?>
        <div class="task-card" style="<?= $isOverdue?'border-color:#fca5a5;':'' ?>">
          <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <!-- Left -->
            <div style="flex:1;min-width:240px;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                <div class="member-chip">
                  <div class="avatar"><?= strtoupper(substr($t['emp_name'],0,1)) ?></div>
                  <?= htmlspecialchars($t['emp_name']) ?>
                </div>
                <?php if($isOverdue): ?><span class="badge badge-red" style="font-size:10.5px;">Overdue</span><?php endif; ?>
              </div>
              <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px;"><?= htmlspecialchars($t['subtask']) ?></div>
              <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--muted);">
                <span><?= date('d M', strtotime($t['from_date'])) ?> → <?= date('d M Y', strtotime($t['to_date'])) ?></span>
                <span style="color:var(--brand);font-weight:700;"><?= number_format($assigned,1) ?> hrs assigned</span>
              </div>
            </div>
            <!-- Right: progress + delete -->
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;min-width:160px;">
              <div style="text-align:right;">
                <div style="font-size:12px;color:var(--muted);">
                  <span style="font-weight:800;color:var(--green-text);font-size:15px;"><?= number_format($utilized,1) ?></span>
                  / <?= number_format($assigned,1) ?> hrs
                </div>
                <div style="height:5px;background:var(--border);border-radius:3px;overflow:hidden;width:130px;margin-top:4px;margin-left:auto;">
                  <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:3px;"></div>
                </div>
                <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= $pct ?>% done</div>
              </div>
              <form method="POST" onsubmit="return confirm('Remove this task?')" style="margin-top:4px;">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <input type="hidden" name="project_id" value="<?= $selectedProject ?>">
                <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;font-size:11px;">Remove</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
