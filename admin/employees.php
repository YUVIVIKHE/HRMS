<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$stats = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN employee_type='FTE' THEN 1 ELSE 0 END) as fte,
        SUM(CASE WHEN employee_type='External' THEN 1 ELSE 0 END) as external
    FROM employees
")->fetch(PDO::FETCH_ASSOC);

$employees = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name, email, job_title, department_id, status, employee_type, created_at FROM employees ORDER BY created_at DESC")->fetchAll();

$deptRows = $db->query("SELECT id, name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
$departments = $deptRows ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Employees – HRMS Portal</title>
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
      <span class="page-title">Employees</span>
      <span class="page-breadcrumb">Workforce Directory</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?>
      <div class="alert alert-success">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($successMsg) ?>
      </div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
      <div class="alert alert-error">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errorMsg) ?>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-text">
        <h1>Employee Directory</h1>
        <p>Manage your workforce — view profiles, track headcount, and onboard new hires.</p>
      </div>
      <div class="page-header-actions">
        <a href="add_employee.php" class="btn btn-primary">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Employee
        </a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:#eef2ff;color:var(--brand)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
          <div class="stat-label">Total</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green)">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
          <div class="stat-label">Active</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue)">
          <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['fte'] ?? 0 ?></div>
          <div class="stat-label">FTE</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow)">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $stats['external'] ?? 0 ?></div>
          <div class="stat-label">External</div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>All Employees <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($employees) ?>)</span></h2>
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="empSearch" placeholder="Search employees…" oninput="filterTable()">
        </div>
      </div>
      <table id="empTable">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Job Title</th>
            <th>Type</th>
            <th>Status</th>
            <th>Joined</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($employees)): ?>
            <tr class="empty-row"><td colspan="6">No employees found. <a href="add_employee.php" style="color:var(--brand)">Add your first employee →</a></td></tr>
          <?php else: foreach($employees as $emp): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($emp['name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($emp['name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($emp['email']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($departments[$emp['department_id']] ?? '—') ?></td>
            <td class="text-muted"><?= htmlspecialchars($emp['job_title'] ?: '—') ?></td>
            <td>
              <span class="badge <?= $emp['employee_type']==='FTE'?'badge-blue':'badge-yellow' ?>">
                <?= htmlspecialchars($emp['employee_type'] ?: '—') ?>
              </span>
            </td>
            <td>
              <span class="badge <?= strtolower($emp['status'])==='active'?'badge-green':'badge-red' ?>">
                <?= htmlspecialchars(ucfirst($emp['status'])) ?>
              </span>
            </td>
            <td class="text-muted text-sm"><?= date('M d, Y', strtotime($emp['created_at'])) ?></td>
            <td>
              <a href="#" class="btn btn-ghost btn-sm">Edit</a>
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
function filterTable() {
  const q = document.getElementById('empSearch').value.toLowerCase();
  document.querySelectorAll('#empTable tbody tr:not(.empty-row)').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
