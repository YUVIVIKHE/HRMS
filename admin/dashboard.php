<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$stats = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN employee_type='FTE' THEN 1 ELSE 0 END) as fte,
        SUM(CASE WHEN employee_type='External' THEN 1 ELSE 0 END) as external
    FROM employees
")->fetch(PDO::FETCH_ASSOC);

$recent = $db->query("SELECT CONCAT(first_name,' ',last_name) AS name, email, status, created_at FROM employees ORDER BY created_at DESC LIMIT 5")->fetchAll();
$firstName = explode(' ', $_SESSION['user_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard – HRMS Portal</title>
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
      <span class="page-title">Dashboard</span>
      <span class="page-breadcrumb">Welcome back, <?= htmlspecialchars($firstName) ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:#eef2ff;color:var(--brand)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
          <div class="stat-label">Total Employees</div>
          <div class="stat-sub">All records</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green)">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
          <div class="stat-label">Active</div>
          <div class="stat-sub">Currently active</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue)">
          <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['fte'] ?? 0 ?></div>
          <div class="stat-label">Full-Time (FTE)</div>
          <div class="stat-sub">Permanent staff</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow)">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['external'] ?? 0 ?></div>
          <div class="stat-label">External / Contract</div>
          <div class="stat-sub">Non-permanent</div>
        </div>
      </div>
    </div>

    <!-- Recent Employees -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Recent Employees</h2>
        <a href="employees.php" class="btn btn-secondary btn-sm">View All</a>
      </div>
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Status</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($recent)): ?>
            <tr class="empty-row"><td colspan="3">No employees yet. <a href="add_employee.php" style="color:var(--brand)">Add one →</a></td></tr>
          <?php else: foreach($recent as $e): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($e['name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($e['name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($e['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge <?= strtolower($e['status'])==='active'?'badge-green':'badge-red' ?>">
                <?= htmlspecialchars(ucfirst($e['status'])) ?>
              </span>
            </td>
            <td class="text-muted text-sm"><?= date('M d, Y', strtotime($e['created_at'])) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /page-body -->
</div><!-- /main-content -->
</div><!-- /app-shell -->
</body>
</html>
