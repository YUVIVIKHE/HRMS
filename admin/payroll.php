<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete_salary') {
    $id = (int)$_POST['salary_id'];
    $db->prepare("DELETE FROM salary_structures WHERE id=?")->execute([$id]);
    $_SESSION['flash_success'] = "Salary structure deleted.";
    header("Location: payroll.php"); exit;
}

$search = trim($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];
if ($search) { $where[] = "(u.name LIKE ? OR e.employee_id LIKE ? OR e.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$employees = $db->prepare("
    SELECT e.id AS emp_id, e.employee_id AS emp_code, e.first_name, e.last_name, e.email,
           e.job_title, e.department_id, u.id AS user_id, u.name AS full_name,
           d.name AS dept_name,
           ss.id AS salary_id, ss.gross_salary, ss.basic_salary
    FROM employees e
    JOIN users u ON e.email = u.email
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN salary_structures ss ON ss.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.name ASC
");
$employees->execute($params);
$employees = $employees->fetchAll();

$totalEmps = count($employees);
$withSalary = count(array_filter($employees, fn($e) => $e['salary_id']));
$withoutSalary = $totalEmps - $withSalary;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payroll – HRMS Portal</title>
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
      <span class="page-title">Payroll</span>
      <span class="page-breadcrumb">Salary Structure Management</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?><div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div class="stat-body"><div class="stat-value"><?= $totalEmps ?></div><div class="stat-label">Total Employees</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);"><svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <div class="stat-body"><div class="stat-value"><?= $withSalary ?></div><div class="stat-label">Salary Added</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow);"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
        <div class="stat-body"><div class="stat-value"><?= $withoutSalary ?></div><div class="stat-label">Pending</div></div>
      </div>
    </div>

    <!-- Search -->
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
      <div class="search-box" style="min-width:240px;flex:0 1 320px;">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search employee name, ID, email…">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <?php if($search): ?><a href="payroll.php" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
    </form>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Employees <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= $totalEmps ?>)</span></h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Designation</th>
            <th style="text-align:center;">Gross (Annual)</th>
            <th style="text-align:center;">Monthly Basic</th>
            <th>Status</th>
            <th style="width:140px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($employees)): ?>
            <tr class="empty-row"><td colspan="7">No employees found.</td></tr>
          <?php else: foreach($employees as $emp): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($emp['full_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($emp['full_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($emp['emp_code'] ?: $emp['email']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm text-muted"><?= htmlspecialchars($emp['dept_name'] ?: '—') ?></td>
            <td class="text-sm"><?= htmlspecialchars($emp['job_title'] ?: '—') ?></td>
            <td style="text-align:center;font-weight:700;color:var(--brand);">
              <?= $emp['salary_id'] ? '₹'.number_format($emp['gross_salary'],0) : '—' ?>
            </td>
            <td style="text-align:center;font-weight:700;color:var(--green-text);">
              <?= $emp['salary_id'] ? '₹'.number_format($emp['basic_salary'],0) : '—' ?>
            </td>
            <td>
              <?php if($emp['salary_id']): ?>
                <span class="badge badge-green">Added</span>
              <?php else: ?>
                <span class="badge badge-yellow">Pending</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <?php if($emp['salary_id']): ?>
                  <a href="salary_structure.php?user_id=<?= $emp['user_id'] ?>" class="btn btn-sm" style="background:var(--brand-light);color:var(--brand);border:1px solid #c7d2fe;">View</a>
                  <a href="salary_structure.php?user_id=<?= $emp['user_id'] ?>&edit=1" class="btn btn-ghost btn-sm">Edit</a>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete salary structure?')">
                    <input type="hidden" name="action" value="delete_salary">
                    <input type="hidden" name="salary_id" value="<?= $emp['salary_id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Del</button>
                  </form>
                <?php else: ?>
                  <a href="salary_structure.php?user_id=<?= $emp['user_id'] ?>&edit=1" class="btn btn-primary btn-sm">+ Add</a>
                <?php endif; ?>
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
</body>
</html>
