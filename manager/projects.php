<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

// Fetch projects assigned to this manager
$projects = $db->prepare("
    SELECT p.*,
           COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl
                     JOIN task_assignments ta ON tpl.task_id = ta.id
                     WHERE ta.project_id = p.id), 0) AS worked_hours
    FROM projects p
    WHERE p.manager_id = ?
    ORDER BY p.deadline_date ASC
");
$projects->execute([$uid]);
$projects = $projects->fetchAll();

$total = count($projects);
$totalHrs = array_sum(array_column($projects, 'total_hours'));
$totalWorked = array_sum(array_column($projects, 'worked_hours'));
$deadlineNear = count(array_filter($projects, fn($p) => $p['deadline_date'] >= date('Y-m-d') && $p['deadline_date'] <= date('Y-m-d', strtotime('+7 days'))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Projects – HRMS Portal</title>
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
      <span class="page-title">My Projects</span>
      <span class="page-breadcrumb">Projects assigned to you</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $total ?></div>
          <div class="stat-label">Total Projects</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--red-bg);color:var(--red);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $deadlineNear ?></div>
          <div class="stat-label">Deadline Near</div>
          <div class="stat-sub">Within 7 days</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($totalHrs,1) ?></div>
          <div class="stat-label">Total Hrs</div>
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

    <!-- Search -->
    <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
      <div class="search-box" style="min-width:200px;flex:0 1 280px;">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="pSearch" placeholder="Search projects…" oninput="filterP(this.value)">
      </div>
    </div>

    <!-- Projects Table -->
    <?php if(empty($projects)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:60px 20px;">
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No projects assigned</div>
        <div style="font-size:13.5px;color:var(--muted);">Projects assigned to you by admin will appear here.</div>
      </div></div>
    <?php else: ?>
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Projects <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= $total ?>)</span></h2>
      </div>
      <table id="pTable">
        <thead>
          <tr>
            <th>Project</th>
            <th>Client</th>
            <th>Timeline</th>
            <th style="text-align:center;">Total Hrs</th>
            <th style="text-align:center;">Worked Hrs</th>
            <th>Progress</th>
            <th>Deadline</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($projects as $p):
            $worked = (float)$p['worked_hours'];
            $assigned = (float)$p['total_hours'];
            $pct = $assigned > 0 ? min(100, round(($worked/$assigned)*100)) : 0;
            $overdue = $p['deadline_date'] < date('Y-m-d');
            $daysLeft = (int)ceil((strtotime($p['deadline_date']) - time()) / 86400);
          ?>
          <tr class="p-row" data-name="<?= htmlspecialchars(strtolower($p['project_name'].' '.$p['project_code'].' '.($p['client_name']??''))) ?>">
            <td>
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                <code style="font-size:11px;background:var(--surface-2);padding:1px 6px;border-radius:4px;color:var(--muted);"><?= htmlspecialchars($p['project_code']) ?></code>
              </div>
              <div class="td-name"><?= htmlspecialchars($p['project_name']) ?></div>
            </td>
            <td class="text-muted text-sm"><?= htmlspecialchars($p['client_name'] ?: '—') ?></td>
            <td class="text-sm">
              <div style="color:var(--muted);"><?= date('d M Y', strtotime($p['start_date'])) ?></div>
              <div style="color:var(--text-2);"><?= date('d M Y', strtotime($p['deadline_date'])) ?></div>
            </td>
            <td style="text-align:center;font-weight:700;color:var(--brand);"><?= number_format($assigned,1) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--green-text);"><?= number_format($worked,1) ?></td>
            <td style="min-width:100px;">
              <div style="display:flex;align-items:center;gap:5px;">
                <div style="flex:1;height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
                  <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'var(--green)':'var(--blue)' ?>;border-radius:3px;"></div>
                </div>
                <span style="font-size:11px;color:var(--muted);"><?= $pct ?>%</span>
              </div>
            </td>
            <td>
              <?php if($overdue): ?>
                <span style="font-size:12px;color:var(--red);font-weight:700;">Overdue</span>
              <?php elseif($daysLeft <= 7): ?>
                <span style="font-size:12px;color:var(--yellow);font-weight:600;"><?= $daysLeft ?> day<?= $daysLeft!==1?'s':'' ?></span>
              <?php else: ?>
                <span style="font-size:12px;color:var(--muted);"><?= $daysLeft ?> days</span>
              <?php endif; ?>
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

<script>
function filterP(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.p-row').forEach(r => { r.style.display = !q || r.dataset.name.includes(q) ? '' : 'none'; });
}
</script>
</body>
</html>
