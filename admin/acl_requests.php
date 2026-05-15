<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Get all ACL requests (all employees)
$requests = $db->query("
    SELECT ar.*, u.name AS emp_name, u.role AS emp_role, e.employee_id AS emp_code, d.name AS dept_name
    FROM acl_requests ar
    JOIN users u ON ar.user_id = u.id
    JOIN employees e ON e.email = u.email
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY ar.status = 'pending' DESC, ar.work_date DESC
")->fetchAll();

$pendingCount = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
$totalHrs = array_sum(array_map(fn($r) => $r['status']==='approved' ? (float)$r['hours'] : 0, $requests));

$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ACL Activity – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left"><span class="page-title">ACL Activity</span><span class="page-breadcrumb">Employee holiday/weekend work log</span></div>
    <div class="topbar-right"><span class="role-chip">Admin</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">
    <?php if($successMsg): ?><div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card"><div class="stat-body"><div class="stat-value"><?= count($requests) ?></div><div class="stat-label">Total Entries</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--yellow);"><?= $pendingCount ?></div><div class="stat-label">Pending</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--green-text);"><?= $approvedCount ?></div><div class="stat-label">Approved</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--brand);"><?= number_format($totalHrs,1) ?></div><div class="stat-label">Total Approved Hrs</div></div></div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar"><h2>ACL Activity Log <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($requests) ?>)</span></h2></div>
      <table>
        <thead><tr>
          <th>Employee</th>
          <th>Emp ID</th>
          <th>Department</th>
          <th>Work Date</th>
          <th>Day</th>
          <th style="text-align:center;">Hours</th>
          <th>Reason</th>
          <th>Status</th>
          <th>Approved By</th>
        </tr></thead>
        <tbody>
          <?php if(empty($requests)): ?><tr class="empty-row"><td colspan="9">No ACL activity recorded.</td></tr>
          <?php else: foreach($requests as $r): ?>
          <tr>
            <td class="font-semibold"><?= htmlspecialchars($r['emp_name']) ?></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($r['emp_code'] ?? '') ?></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($r['dept_name'] ?? '—') ?></td>
            <td class="text-sm font-semibold"><?= date('d M Y', strtotime($r['work_date'])) ?></td>
            <td class="text-sm"><?= date('l', strtotime($r['work_date'])) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--brand);"><?= number_format($r['hours'],1) ?></td>
            <td class="text-sm text-muted" style="max-width:200px;"><?= htmlspecialchars($r['reason']) ?></td>
            <td><span class="badge <?= $statusMap[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
            <td class="text-sm text-muted"><?= $r['reviewed_at'] ? date('d M', strtotime($r['reviewed_at'])) : '—' ?></td>
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
