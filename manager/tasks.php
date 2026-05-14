<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/task_config.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST: Delete ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_task') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if ($taskId) {
        $chk = $db->prepare("SELECT id FROM task_assignments WHERE id=? AND assigned_by=?");
        $chk->execute([$taskId,$uid]);
        if ($chk->fetch()) {
            $db->prepare("DELETE FROM task_assignments WHERE id=?")->execute([$taskId]);
            $_SESSION['flash_success'] = "Task removed.";
        }
    }
    header("Location: tasks.php?".$_SERVER['QUERY_STRING']); exit;
}

// ── Filters ──────────────────────────────────────────────────
$filterProject = (int)($_GET['project_id'] ?? 0);
$filterMember  = (int)($_GET['member_id'] ?? 0);
$search        = trim($_GET['q'] ?? '');

// My projects
$myProjects = $db->prepare("
    SELECT id, project_name, project_code
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
    $tm = $db->prepare("SELECT u.id, u.name FROM users u JOIN employees e ON e.email=u.email WHERE e.department_id=? AND u.role='employee' AND u.id!=? AND u.status='active' ORDER BY u.name");
    $tm->execute([$deptId,$uid]); $teamMembers = $tm->fetchAll();
}

// ── Build query — default: today's tasks across all projects ─
$where  = ['ta.assigned_by=?'];
$params = [$uid];

if ($filterProject) { $where[] = "ta.project_id=?"; $params[] = $filterProject; }
if ($filterMember)  { $where[] = "ta.assigned_to=?"; $params[] = $filterMember; }
if ($search)        { $where[] = "(ta.subtask LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

// If no filters applied, show only today's active tasks
if (!$filterProject && !$filterMember && !$search) {
    $where[] = "ta.from_date <= ? AND ta.to_date >= ?";
    $params[] = $today;
    $params[] = $today;
}

$tasks = $db->prepare("
    SELECT ta.*, u.name AS emp_name, u.email AS emp_email,
           e.employee_id AS emp_code,
           p.project_name, p.project_code,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
    FROM task_assignments ta
    JOIN users u ON ta.assigned_to=u.id
    LEFT JOIN employees e ON e.email=u.email
    JOIN projects p ON ta.project_id=p.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY u.name ASC, p.project_name ASC, ta.from_date ASC
");
$tasks->execute($params);
$tasks = $tasks->fetchAll();

// Stats
$totalTasks = count($tasks);
$totalAssigned = array_sum(array_column($tasks, 'hours'));
$totalWorked   = array_sum(array_column($tasks, 'utilized_hours'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tasks – HRMS Portal</title>
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
      <span class="page-title">Tasks</span>
      <span class="page-breadcrumb"><?= (!$filterProject && !$filterMember && !$search) ? "Today's Tasks" : 'Filtered Results' ?></span>
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

    <!-- Filter + Assign Task row -->
    <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;">
      <!-- Filters -->
      <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;flex:1;">
        <select name="project_id" class="form-control" style="font-size:13px;padding:9px 14px;min-width:200px;max-width:280px;">
          <option value="">All Projects</option>
          <?php foreach($myProjects as $proj): ?>
          <option value="<?= $proj['id'] ?>" <?= $filterProject==$proj['id']?'selected':'' ?>>
            <?= htmlspecialchars($proj['project_name']) ?> (<?= htmlspecialchars($proj['project_code']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <select name="member_id" class="form-control" style="font-size:13px;padding:9px 14px;min-width:160px;max-width:220px;">
          <option value="">All Members</option>
          <?php foreach($teamMembers as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterMember==$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="search-box" style="min-width:160px;flex:0 1 200px;">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search…">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="tasks.php" class="btn btn-ghost btn-sm">Reset</a>
      </form>
      <!-- Assign Task -->
      <?php if(!empty($myProjects)): ?>
      <a href="assign_task.php?project_id=<?= $filterProject ?: $myProjects[0]['id'] ?>" class="btn btn-primary" style="flex-shrink:0;">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Assign Task
      </a>
      <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $totalTasks ?></div>
          <div class="stat-label">Tasks</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($totalAssigned,1) ?></div>
          <div class="stat-label">Hrs Assigned</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($totalWorked,1) ?></div>
          <div class="stat-label">Hrs Worked</div>
        </div>
      </div>
    </div>

    <!-- Tasks Table -->
    <?php if(empty($tasks)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:56px 20px;">
        <div style="width:48px;height:48px;margin:0 auto 14px;border-radius:50%;background:var(--brand-light);display:flex;align-items:center;justify-content:center;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--brand)" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:5px;">No tasks found</div>
        <div style="font-size:13px;color:var(--muted);"><?= ($filterProject||$filterMember||$search)?'Try adjusting your filters.':'No tasks scheduled for today. Use "Assign Task" to create new ones.' ?></div>
      </div></div>
    <?php else: ?>
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2><?= (!$filterProject && !$filterMember && !$search) ? "Today's Tasks" : 'Tasks' ?> <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= $totalTasks ?>)</span></h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Task</th>
            <th>Project</th>
            <th>Date Range</th>
            <th style="text-align:center;">Assigned</th>
            <th style="text-align:center;">Worked</th>
            <th>Progress</th>
            <th style="width:70px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($tasks as $t):
            $utilized = (float)$t['utilized_hours'];
            $assigned = (float)$t['hours'];
            $pct = $assigned > 0 ? min(100, round(($utilized/$assigned)*100)) : 0;
            $barColor = $pct >= 100 ? 'var(--green)' : 'var(--blue)';
            $isOverdue = $t['to_date'] < $today && $pct < 100;
          ?>
          <tr style="<?= $isOverdue?'background:#fff8f8;':'' ?>">
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($t['emp_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($t['emp_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($t['emp_code'] ?: $t['emp_email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <div class="font-semibold text-sm"><?= htmlspecialchars($t['subtask']) ?></div>
              <?php if($isOverdue): ?><span style="font-size:10.5px;color:var(--red);font-weight:600;">Overdue</span><?php endif; ?>
            </td>
            <td>
              <code style="font-size:11px;background:var(--surface-2);padding:2px 6px;border-radius:4px;color:var(--muted);"><?= htmlspecialchars($t['project_code']) ?></code>
            </td>
            <td class="text-sm text-muted"><?= date('d M', strtotime($t['from_date'])) ?> – <?= date('d M', strtotime($t['to_date'])) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--brand);"><?= number_format($assigned,1) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--green-text);"><?= number_format($utilized,1) ?></td>
            <td style="min-width:100px;">
              <div style="display:flex;align-items:center;gap:5px;">
                <div style="flex:1;height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
                  <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:3px;"></div>
                </div>
                <span style="font-size:11px;color:var(--muted);flex-shrink:0;"><?= $pct ?>%</span>
              </div>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('Remove this task?')">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;font-size:11px;">Del</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>
</body>
</html>
