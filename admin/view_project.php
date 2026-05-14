<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: projects.php"); exit; }

$project = $db->prepare("SELECT p.*, u.name AS manager_name FROM projects p LEFT JOIN users u ON p.manager_id=u.id WHERE p.id=?");
$project->execute([$id]);
$project = $project->fetch();
if (!$project) { header("Location: projects.php"); exit; }

// Hours data
$totalHrs = (float)$project['total_hours'];
$workedHrs = 0;
try {
    $w = $db->prepare("SELECT COALESCE(SUM(tpl.hours_worked),0) FROM task_progress_logs tpl JOIN task_assignments ta ON tpl.task_id=ta.id WHERE ta.project_id=?");
    $w->execute([$id]);
    $workedHrs = (float)$w->fetchColumn();
} catch (Exception $e) {}
$remainingHrs = max(0, $totalHrs - $workedHrs);
$pct = $totalHrs > 0 ? min(100, round(($workedHrs/$totalHrs)*100)) : 0;

// Tasks
$tasks = $db->prepare("
    SELECT ta.*, u.name AS emp_name,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id=ta.id),0) AS task_worked
    FROM task_assignments ta
    JOIN users u ON ta.assigned_to=u.id
    WHERE ta.project_id=?
    ORDER BY ta.from_date ASC
");
$tasks->execute([$id]);
$tasks = $tasks->fetchAll();

$totalTasks = count($tasks);
$estCost = $totalHrs * (float)$project['hr_rate'];
$actualCost = $workedHrs * (float)$project['hr_rate'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($project['project_name']) ?> – HRMS Portal</title>
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
      <span class="page-title">Project Details</span>
      <span class="page-breadcrumb"><a href="projects.php" style="color:var(--muted);text-decoration:none;">Projects</a> / <?= htmlspecialchars($project['project_name']) ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <!-- Project Header -->
    <div style="background:linear-gradient(135deg,var(--brand),var(--brand-mid));border-radius:12px;padding:24px 28px;color:#fff;margin-bottom:24px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
        <code style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:5px;font-size:12px;"><?= htmlspecialchars($project['project_code']) ?></code>
        <?php if($project['priority']): ?>
          <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:5px;font-size:11px;font-weight:700;"><?= $project['priority'] ?></span>
        <?php endif; ?>
      </div>
      <h1 style="font-size:22px;font-weight:800;margin:0 0 6px;"><?= htmlspecialchars($project['project_name']) ?></h1>
      <div style="font-size:13px;opacity:.85;">
        <?php if($project['client_name']): ?>Client: <?= htmlspecialchars($project['client_name']) ?> · <?php endif; ?>
        Manager: <?= htmlspecialchars($project['manager_name'] ?: 'Unassigned') ?> ·
        <?= date('d M Y', strtotime($project['start_date'])) ?> → <?= date('d M Y', strtotime($project['deadline_date'])) ?>
      </div>
    </div>

    <!-- Progress Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($totalHrs,1) ?></div>
          <div class="stat-label">Total Project Hrs</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($workedHrs,1) ?></div>
          <div class="stat-label">Consumed Hrs</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($remainingHrs,1) ?></div>
          <div class="stat-label">Remaining Hrs</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $totalTasks ?></div>
          <div class="stat-label">Total Tasks</div>
        </div>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-body" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-size:14px;font-weight:700;color:var(--text);">Project Progress</span>
          <span style="font-size:14px;font-weight:800;color:var(--brand);"><?= $pct ?>%</span>
        </div>
        <div style="height:10px;background:var(--border);border-radius:5px;overflow:hidden;">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'var(--green)':($pct>=70?'var(--blue)':'var(--brand)') ?>;border-radius:5px;transition:width .3s;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:12px;color:var(--muted);">
          <span><?= number_format($workedHrs,1) ?> hrs consumed</span>
          <span><?= number_format($remainingHrs,1) ?> hrs remaining</span>
        </div>
      </div>
    </div>

    <!-- Project Details -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h2>Project Details</h2></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">Start Date</div>
            <div style="font-size:14px;font-weight:600;color:var(--text);"><?= date('d M Y', strtotime($project['start_date'])) ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">Deadline</div>
            <div style="font-size:14px;font-weight:600;color:<?= $project['deadline_date']<date('Y-m-d')?'var(--red)':'var(--text)' ?>;"><?= date('d M Y', strtotime($project['deadline_date'])) ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">HR Rate</div>
            <div style="font-size:14px;font-weight:600;color:var(--text);"><?= $project['hr_rate']>0 ? '₹'.number_format($project['hr_rate'],0).'/hr' : '—' ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">Est. Cost</div>
            <div style="font-size:14px;font-weight:600;color:var(--brand);"><?= $estCost>0 ? '₹'.number_format($estCost,0) : '—' ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">Actual Cost</div>
            <div style="font-size:14px;font-weight:600;color:var(--green-text);"><?= $actualCost>0 ? '₹'.number_format($actualCost,0) : '—' ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">Manager</div>
            <div style="font-size:14px;font-weight:600;color:var(--text);"><?= htmlspecialchars($project['manager_name'] ?: 'Unassigned') ?></div>
          </div>
        </div>
        <?php if($project['description']): ?>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:6px;">Description</div>
          <div style="font-size:13px;color:var(--text-2);line-height:1.6;"><?= nl2br(htmlspecialchars($project['description'])) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tasks Table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Task Assignments <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= $totalTasks ?>)</span></h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Task</th>
            <th>Date Range</th>
            <th style="text-align:center;">Assigned</th>
            <th style="text-align:center;">Worked</th>
            <th>Progress</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($tasks)): ?>
            <tr class="empty-row"><td colspan="6">No tasks assigned to this project yet.</td></tr>
          <?php else: foreach($tasks as $t):
            $tw = (float)$t['task_worked'];
            $ta = (float)$t['hours'];
            $tp = $ta > 0 ? min(100, round(($tw/$ta)*100)) : 0;
          ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($t['emp_name'],0,1)) ?></div>
                <div><div class="td-name"><?= htmlspecialchars($t['emp_name']) ?></div></div>
              </div>
            </td>
            <td class="font-semibold text-sm"><?= htmlspecialchars($t['subtask']) ?></td>
            <td class="text-sm text-muted"><?= date('d M', strtotime($t['from_date'])) ?> – <?= date('d M', strtotime($t['to_date'])) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--brand);"><?= number_format($ta,1) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--green-text);"><?= number_format($tw,1) ?></td>
            <td style="min-width:90px;">
              <div style="display:flex;align-items:center;gap:5px;">
                <div style="flex:1;height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
                  <div style="height:100%;width:<?= $tp ?>%;background:<?= $tp>=100?'var(--green)':'var(--blue)' ?>;border-radius:3px;"></div>
                </div>
                <span style="font-size:11px;color:var(--muted);"><?= $tp ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:20px;">
      <a href="projects.php" class="btn btn-secondary">← Back to Projects</a>
      <a href="add_project.php?id=<?= $project['id'] ?>" class="btn btn-ghost" style="margin-left:8px;">Edit Project</a>
    </div>

  </div>
</div>
</div>
</body>
</html>
