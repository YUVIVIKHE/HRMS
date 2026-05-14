<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

// ── Filters ──────────────────────────────────────────────────
$filterProject  = (int)($_GET['project_id'] ?? 0);
$filterDate     = trim($_GET['date'] ?? $today);
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo   = trim($_GET['date_to'] ?? '');
$filterMode     = $_GET['mode'] ?? 'day'; // day, range, all

$where  = ["ta.assigned_to = ?"];
$params = [$uid];
if ($filterProject) { $where[] = "ta.project_id=?"; $params[] = $filterProject; }

if ($filterMode === 'day') {
    $where[] = "ta.from_date <= ? AND ta.to_date >= ?";
    $params[] = $filterDate;
    $params[] = $filterDate;
} elseif ($filterMode === 'range' && $filterDateFrom && $filterDateTo) {
    $where[] = "ta.from_date <= ? AND ta.to_date >= ?";
    $params[] = $filterDateTo;
    $params[] = $filterDateFrom;
}
// 'all' = no date filter

$tasks = $db->prepare("
    SELECT ta.*, p.project_name, p.project_code, p.deadline_date AS proj_deadline,
           u.name AS assigned_by_name,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    JOIN users u ON ta.assigned_by = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ta.from_date ASC
");
$tasks->execute($params);
$tasks = $tasks->fetchAll();

// My projects (for filter)
$myProjects = $db->prepare("
    SELECT DISTINCT p.id, p.project_name, p.project_code
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = ?
    ORDER BY p.project_name
");
$myProjects->execute([$uid]);
$myProjects = $myProjects->fetchAll();

// Stats
$stmt = $db->prepare("SELECT COALESCE(SUM(hours),0) FROM task_assignments WHERE assigned_to=?");
$stmt->execute([$uid]); $totalHrs = (float)$stmt->fetchColumn();

$stmt2 = $db->prepare("SELECT COALESCE(SUM(tpl.hours_worked),0) FROM task_progress_logs tpl WHERE tpl.user_id=?");
$stmt2->execute([$uid]); $trackedHrs = (float)$stmt2->fetchColumn();

$stmtCnt = $db->prepare("SELECT COUNT(*) FROM task_assignments WHERE assigned_to=?");
$stmtCnt->execute([$uid]); $totalTasks = (int)$stmtCnt->fetchColumn();
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
      <span class="page-breadcrumb"><?php
        if ($filterMode === 'day') echo date('d M Y', strtotime($filterDate)) . ($filterDate === $today ? " (Today)" : "");
        elseif ($filterMode === 'range') echo date('d M', strtotime($filterDateFrom)) . ' – ' . date('d M Y', strtotime($filterDateTo));
        else echo 'All Tasks';
      ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $totalTasks ?></div>
          <div class="stat-label">Total Tasks</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($totalHrs,1) ?></div>
          <div class="stat-label">Total Hrs Assigned</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($trackedHrs,1) ?></div>
          <div class="stat-label">Hrs Worked</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;padding:12px 16px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
      <!-- Day navigation -->
      <a href="my_tasks.php?mode=day&date=<?= date('Y-m-d', strtotime($filterDate.' -1 day')) ?>" class="btn btn-ghost btn-sm" style="padding:6px 10px;">←</a>
      <input type="date" id="dayPicker" value="<?= htmlspecialchars($filterDate) ?>" class="form-control" style="font-size:12.5px;padding:7px 10px;width:auto;font-weight:600;" onchange="location.href='my_tasks.php?mode=day&date='+this.value">
      <a href="my_tasks.php?mode=day&date=<?= date('Y-m-d', strtotime($filterDate.' +1 day')) ?>" class="btn btn-ghost btn-sm" style="padding:6px 10px;">→</a>
      <a href="my_tasks.php?mode=day&date=<?= $today ?>" class="btn btn-sm <?= ($filterMode==='day'&&$filterDate===$today)?'btn-primary':'btn-ghost' ?>">Today</a>
      <a href="my_tasks.php?mode=all" class="btn btn-sm <?= $filterMode==='all'?'btn-primary':'btn-ghost' ?>">All</a>

      <!-- Date range -->
      <span style="margin-left:auto;display:flex;gap:6px;align-items:center;">
        <input type="date" id="rangeFrom" value="<?= htmlspecialchars($filterDateFrom) ?>" class="form-control" style="font-size:12px;padding:6px 10px;width:auto;">
        <span style="font-size:12px;color:var(--muted);">to</span>
        <input type="date" id="rangeTo" value="<?= htmlspecialchars($filterDateTo) ?>" class="form-control" style="font-size:12px;padding:6px 10px;width:auto;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="applyRange()">Go</button>
      </span>
    </div>

    <!-- Project filter -->
    <?php if(!empty($myProjects)): ?>
    <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
      <select class="form-control" style="font-size:13px;padding:7px 12px;min-width:220px;width:auto;" onchange="applyProject(this.value)">
        <option value="">All Projects</option>
        <?php foreach($myProjects as $proj): ?>
          <option value="<?= $proj['id'] ?>" <?= $filterProject==$proj['id']?'selected':'' ?>><?= htmlspecialchars($proj['project_name']) ?> (<?= htmlspecialchars($proj['project_code']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <span style="font-size:12.5px;color:var(--muted);"><?= count($tasks) ?> task(s) shown</span>
    </div>
    <?php endif; ?>

    <!-- Task cards -->
    <?php if(empty($tasks)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:60px 20px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--muted-light)" stroke-width="1.5" style="display:block;margin:0 auto 16px;"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No tasks for <?= $filterMode==='day' ? date('d M Y', strtotime($filterDate)) : 'this period' ?></div>
        <div style="font-size:13.5px;color:var(--muted);">Try selecting a different date or view all tasks.</div>
      </div></div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <?php foreach($tasks as $t):
        $isOverdue = $t['to_date'] < $today;
        $daysLeft  = (int)ceil((strtotime($t['to_date']) - time()) / 86400);
        $utilized  = (float)$t['utilized_hours'];
        $assigned  = (float)$t['hours'];
        $pct       = $assigned > 0 ? min(100, round(($utilized / $assigned) * 100)) : 0;
        $barColor  = $pct >= 100 ? 'var(--green)' : 'var(--blue)';
      ?>
      <div class="card" style="<?= $isOverdue?'border-color:#fca5a5;':'' ?>">
        <div style="padding:16px 20px;display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
          <div style="flex:1;min-width:240px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
              <code style="font-size:11px;background:var(--surface-2);padding:2px 7px;border-radius:5px;color:var(--muted);"><?= htmlspecialchars($t['project_code']) ?></code>
              <span style="font-size:12.5px;color:var(--muted);"><?= htmlspecialchars($t['project_name']) ?></span>
              <?php if($isOverdue): ?><span class="badge badge-red" style="font-size:10.5px;">Overdue</span><?php endif; ?>
            </div>
            <div style="font-size:15px;font-weight:800;color:var(--text);margin-bottom:6px;"><?= htmlspecialchars($t['subtask']) ?></div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:var(--muted);">
              <span><?= date('d M', strtotime($t['from_date'])) ?> → <?= date('d M Y', strtotime($t['to_date'])) ?></span>
              <span>Assigned by <?= htmlspecialchars($t['assigned_by_name']) ?></span>
              <?php if(!$isOverdue && $daysLeft >= 0): ?>
                <span style="color:<?= $daysLeft<=2?'var(--red)':($daysLeft<=5?'var(--yellow)':'var(--muted)') ?>;font-weight:600;"><?= $daysLeft ?> day<?= $daysLeft!==1?'s':'' ?> left</span>
              <?php endif; ?>
            </div>
            <?php if($t['notes']): ?>
              <div style="margin-top:8px;font-size:12.5px;color:var(--muted);background:var(--surface-2);padding:8px 10px;border-radius:6px;"><?= htmlspecialchars($t['notes']) ?></div>
            <?php endif; ?>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;min-width:150px;">
            <div style="font-size:12px;color:var(--muted);">
              <span style="font-weight:800;color:var(--green-text);font-size:16px;"><?= number_format($utilized,1) ?></span>
              <span>/ <?= number_format($assigned,1) ?> hrs</span>
            </div>
            <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden;width:140px;">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:3px;"></div>
            </div>
            <div style="font-size:11px;color:var(--muted);"><?= $pct ?>% done</div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>

<script>
function applyRange() {
  const f = document.getElementById('rangeFrom').value;
  const t = document.getElementById('rangeTo').value;
  if (!f || !t) { alert('Select both dates.'); return; }
  if (f > t) { alert('From must be before To.'); return; }
  location.href = 'my_tasks.php?mode=range&date_from=' + f + '&date_to=' + t;
}
function applyProject(v) {
  const params = new URLSearchParams(window.location.search);
  if (v) params.set('project_id', v); else params.delete('project_id');
  location.href = 'my_tasks.php?' + params.toString();
}
</script>
</body>
</html>
