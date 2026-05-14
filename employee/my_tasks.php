<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST: Update own task status ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'update_status') {
    $taskId = (int)$_POST['task_id'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['Pending','In Progress','Completed','On Hold'])) {
        $db->prepare("UPDATE task_assignments SET status=? WHERE id=? AND assigned_to=?")
           ->execute([$status, $taskId, $uid]);
        $_SESSION['flash_success'] = "Task status updated.";
    }
    header("Location: my_tasks.php"); exit;
}

// ── Filters ──────────────────────────────────────────────────
$filterStatus  = $_GET['status']  ?? '';
$filterProject = (int)($_GET['project_id'] ?? 0);

$where  = ["ta.assigned_to = ?"];
$params = [$uid];
if ($filterStatus)  { $where[] = "ta.status=?";     $params[] = $filterStatus; }
if ($filterProject) { $where[] = "ta.project_id=?"; $params[] = $filterProject; }

$tasks = $db->prepare("
    SELECT ta.*, p.project_name, p.project_code, p.deadline_date AS proj_deadline,
           u.name AS assigned_by_name
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    JOIN users u ON ta.assigned_by = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ta.from_date ASC, ta.status='Completed' ASC
");
$tasks->execute($params);
$tasks = $tasks->fetchAll();

// My projects (for filter)
$myProjects = $db->query("
    SELECT DISTINCT p.id, p.project_name, p.project_code
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = $uid
    ORDER BY p.project_name
")->fetchAll();

// Stats
$counts = [];
foreach (['Pending','In Progress','Completed','On Hold'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM task_assignments WHERE assigned_to=? AND status=?");
    $c->execute([$uid,$s]); $counts[$s] = (int)$c->fetchColumn();
}
$totalHrs     = (float)$db->query("SELECT COALESCE(SUM(hours),0) FROM task_assignments WHERE assigned_to=$uid")->fetchColumn();
$completedHrs = (float)$db->query("SELECT COALESCE(SUM(hours),0) FROM task_assignments WHERE assigned_to=$uid AND status='Completed'")->fetchColumn();

$statusBadge = ['Pending'=>'badge-yellow','In Progress'=>'badge-blue','Completed'=>'badge-green','On Hold'=>'badge-gray'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Tasks – HRMS Portal</title>
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
      <span class="page-title">My Tasks</span>
      <span class="page-breadcrumb">Tasks assigned to you</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?>
      <div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-text">
        <h1>My Tasks</h1>
        <p>Tasks assigned to you across all projects.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
      <?php foreach(['Pending'=>'badge-yellow','In Progress'=>'badge-blue','Completed'=>'badge-green','On Hold'=>'badge-gray'] as $s=>$b): ?>
      <a href="my_tasks.php?status=<?= urlencode($s) ?>" style="text-decoration:none;">
        <div class="stat-card" style="<?= $filterStatus===$s?'border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-light);':'' ?>">
          <div class="stat-body"><div class="stat-value"><?= $counts[$s] ?></div><div class="stat-label"><?= $s ?></div></div>
          <span class="badge <?= $b ?>" style="align-self:flex-start;"><?= $s ?></span>
        </div>
      </a>
      <?php endforeach; ?>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($completedHrs,1) ?></div>
          <div class="stat-label">Hrs Completed</div>
          <div class="stat-sub">of <?= number_format($totalHrs,1) ?> total</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
      <a href="my_tasks.php" class="btn btn-sm <?= !$filterStatus&&!$filterProject?'btn-primary':'btn-ghost' ?>">All</a>
      <?php foreach(array_keys($counts) as $s): ?>
        <a href="my_tasks.php?status=<?= urlencode($s) ?>" class="btn btn-sm <?= $filterStatus===$s?'btn-primary':'btn-ghost' ?>"><?= $s ?></a>
      <?php endforeach; ?>
      <?php if(!empty($myProjects)): ?>
      <select class="form-control" style="font-size:13px;padding:7px 12px;min-width:180px;margin-left:auto;" onchange="location.href='my_tasks.php?project_id='+this.value+(<?= $filterStatus?'\'&status='.urlencode($filterStatus).'\'':'\'\'' ?>)">
        <option value="">All Projects</option>
        <?php foreach($myProjects as $proj): ?>
          <option value="<?= $proj['id'] ?>" <?= $filterProject==$proj['id']?'selected':'' ?>><?= htmlspecialchars($proj['project_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
    </div>

    <!-- Task cards -->
    <?php if(empty($tasks)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:60px 20px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--muted-light)" stroke-width="1.5" style="display:block;margin:0 auto 16px;"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No tasks found</div>
        <div style="font-size:13.5px;color:var(--muted);">Your manager will assign tasks to you here.</div>
      </div></div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <?php foreach($tasks as $t):
        $isOverdue = $t['status']!=='Completed' && $t['to_date'] < date('Y-m-d');
        $daysLeft  = (int)ceil((strtotime($t['to_date']) - time()) / 86400);
      ?>
      <div class="card" style="<?= $isOverdue?'border-color:#fca5a5;':'' ?>">
        <div style="padding:16px 20px;display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
          <!-- Left: task info -->
          <div style="flex:1;min-width:240px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
              <code style="font-size:11px;background:var(--surface-2);padding:2px 7px;border-radius:5px;color:var(--muted);"><?= htmlspecialchars($t['project_code']) ?></code>
              <span style="font-size:12.5px;color:var(--muted);"><?= htmlspecialchars($t['project_name']) ?></span>
              <?php if($isOverdue): ?><span class="badge badge-red" style="font-size:10.5px;">⚠ Overdue</span><?php endif; ?>
            </div>
            <div style="font-size:15px;font-weight:800;color:var(--text);margin-bottom:6px;"><?= htmlspecialchars($t['subtask']) ?></div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:var(--muted);">
              <span>📅 <?= date('d M', strtotime($t['from_date'])) ?> → <?= date('d M Y', strtotime($t['to_date'])) ?></span>
              <span style="font-weight:700;color:var(--brand);">⏱ <?= number_format($t['hours'],1) ?> hrs</span>
              <span>👤 Assigned by <?= htmlspecialchars($t['assigned_by_name']) ?></span>
              <?php if($t['status']!=='Completed' && $daysLeft >= 0): ?>
                <span style="color:<?= $daysLeft<=2?'var(--red)':($daysLeft<=5?'var(--yellow)':'var(--muted)') ?>;font-weight:600;"><?= $daysLeft ?> day<?= $daysLeft!==1?'s':'' ?> left</span>
              <?php endif; ?>
            </div>
            <?php if($t['notes']): ?>
              <div style="margin-top:8px;font-size:12.5px;color:var(--muted);background:var(--surface-2);padding:8px 10px;border-radius:6px;"><?= htmlspecialchars($t['notes']) ?></div>
            <?php endif; ?>
          </div>
          <!-- Right: status update -->
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;">
            <span class="badge <?= $statusBadge[$t['status']]??'badge-gray' ?>" style="font-size:12px;"><?= $t['status'] ?></span>
            <form method="POST" style="display:flex;gap:6px;align-items:center;">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <select name="status" class="form-control" style="font-size:12px;padding:5px 10px;width:auto;">
                <?php foreach(['Pending','In Progress','Completed','On Hold'] as $s): ?>
                  <option <?= $t['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm">Update</button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>
</body>
</html>
