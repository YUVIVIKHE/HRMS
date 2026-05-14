<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Handle delete ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete_project') {
    $id = (int)$_POST['project_id'];
    $db->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);
    $_SESSION['flash_success'] = "Project deleted.";
    header("Location: projects.php"); exit;
}

// ── Data ─────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$where  = ["1=1"];
$params = [];
if ($filterStatus) { $where[] = "p.status=?"; $params[] = $filterStatus; }

$projects = $db->prepare("
    SELECT p.*, u.name AS manager_name
    FROM projects p
    LEFT JOIN users u ON p.manager_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.created_at DESC
");
$projects->execute($params);
$projects = $projects->fetchAll();

$counts = [];
foreach (['Planning','Active','On Hold','Completed','Cancelled'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM projects WHERE status=?");
    $c->execute([$s]); $counts[$s] = (int)$c->fetchColumn();
}
$total = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();

$priorityBadge = ['Low'=>'badge-gray','Medium'=>'badge-blue','High'=>'badge-yellow','Critical'=>'badge-red'];
$statusBadge   = ['Planning'=>'badge-gray','Active'=>'badge-green','On Hold'=>'badge-yellow','Completed'=>'badge-blue','Cancelled'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Projects – HRMS Portal</title>
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
      <span class="page-title">Projects</span>
      <span class="page-breadcrumb">Project Management</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
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

    <div class="page-header">
      <div class="page-header-text">
        <h1>Projects</h1>
        <p>Manage client projects, assign managers, and track timelines.</p>
      </div>
      <div class="page-header-actions">
        <a href="add_project.php" class="btn btn-primary">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          New Project
        </a>
      </div>
    </div>

    <!-- Stats -->
    <?php
      $deadlinePassed = (int)$db->query("SELECT COUNT(*) FROM projects WHERE deadline_date < CURDATE() AND status NOT IN ('Completed','Cancelled')")->fetchColumn();
    ?>
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
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
          <div class="stat-value"><?= $deadlinePassed ?></div>
          <div class="stat-label">Deadline Passed</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $counts['Completed'] ?></div>
          <div class="stat-label">Completed</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
      <span style="font-size:13px;font-weight:600;color:var(--text);">All Projects (<?= $total ?>)</span>
      <div style="margin-left:auto;">
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="pSearch" placeholder="Search projects…" oninput="filterP(this.value)">
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>All Projects <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($projects) ?>)</span></h2>
      </div>
      <table id="pTable">
        <thead>
          <tr>
            <th>Project</th>
            <th>Client</th>
            <th>Manager</th>
            <th>Priority</th>
            <th>Timeline</th>
            <th>Hours / Rate</th>
            <th style="width:90px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($projects)): ?>
            <tr class="empty-row"><td colspan="7">No projects yet. <a href="add_project.php" style="color:var(--brand)">Create your first project →</a></td></tr>
          <?php else: foreach($projects as $p):
            $overdue = $p['status']==='Active' && $p['deadline_date'] < date('Y-m-d');
          ?>
          <tr class="p-row" data-name="<?= htmlspecialchars(strtolower($p['project_name'].' '.$p['project_code'].' '.($p['client_name']??''))) ?>"
              style="<?= $overdue?'background:#fff8f8;':'' ?>">
            <td>
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                <code style="font-size:11px;background:var(--surface-2);padding:1px 6px;border-radius:4px;color:var(--muted);flex-shrink:0;"><?= htmlspecialchars($p['project_code']) ?></code>
              </div>
              <div class="td-name"><?= htmlspecialchars($p['project_name']) ?></div>
              <?php if($overdue): ?><div style="font-size:11px;color:var(--red);font-weight:600;">⚠ Overdue</div><?php endif; ?>
            </td>
            <td class="text-muted text-sm"><?= htmlspecialchars($p['client_name'] ?: '—') ?></td>
            <td class="text-sm"><?= htmlspecialchars($p['manager_name'] ?: '—') ?></td>
            <td><span class="badge <?= $priorityBadge[$p['priority']]??'badge-gray' ?>"><?= $p['priority'] ?></span></td>
            <td class="text-sm">
              <div style="color:var(--muted);"><?= date('d M Y', strtotime($p['start_date'])) ?></div>
              <div style="color:<?= $overdue?'var(--red)':'var(--text-2)' ?>;font-weight:<?= $overdue?'600':'400' ?>;"><?= date('d M Y', strtotime($p['deadline_date'])) ?></div>
            </td>
            <td class="text-sm">
              <div class="font-semibold" style="color:var(--brand);"><?= number_format($p['total_hours'],1) ?> hrs</div>
              <?php if($p['hr_rate'] > 0): ?><div style="color:var(--muted);">₹<?= number_format($p['hr_rate'],0) ?>/hr</div><?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="add_project.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this project?')">
                  <input type="hidden" name="action" value="delete_project">
                  <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Del</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

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
