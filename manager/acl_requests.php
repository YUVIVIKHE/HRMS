<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// POST: Approve/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reqId  = (int)($_POST['req_id'] ?? 0);
    if (in_array($action, ['approve_acl','reject_acl']) && $reqId) {
        $status = $action === 'approve_acl' ? 'approved' : 'rejected';
        $db->prepare("UPDATE acl_requests SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$status, $uid, $reqId]);
        $_SESSION['flash_success'] = "ACL request " . $status . ".";
        header("Location: acl_requests.php"); exit;
    }
}

// Get department
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();

// Get all ACL activity for team employees
$requests = [];
if ($deptId) {
    $requests = $db->prepare("
        SELECT ar.*, u.name AS emp_name, e.employee_id AS emp_code,
               al.clock_in, al.clock_out, al.work_seconds
        FROM acl_requests ar
        JOIN users u ON ar.user_id = u.id
        JOIN employees e ON e.email = u.email
        LEFT JOIN attendance_logs al ON al.user_id = ar.user_id AND al.log_date = ar.work_date
        WHERE e.department_id = ? AND u.role = 'employee'
        ORDER BY ar.work_date DESC
    ");
    $requests->execute([$deptId]);
    $requests = $requests->fetchAll();
}

$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];
$pendingCount = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
$totalHrsApproved = array_sum(array_map(fn($r) => $r['status']==='approved' ? (float)$r['hours'] : 0, $requests));
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
    <div class="topbar-right"><span class="role-chip">Manager</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">
    <?php if($successMsg): ?><div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card"><div class="stat-body"><div class="stat-value"><?= count($requests) ?></div><div class="stat-label">Total Entries</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--yellow);"><?= $pendingCount ?></div><div class="stat-label">Pending Approval</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--green-text);"><?= $approvedCount ?></div><div class="stat-label">Approved</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--brand);"><?= number_format($totalHrsApproved,1) ?></div><div class="stat-label">Total Hrs Approved</div></div></div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar"><h2>Employee ACL Activity <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($requests) ?>)</span></h2></div>
      <table>
        <thead><tr>
          <th>Employee</th>
          <th>Work Date</th>
          <th>Day</th>
          <th>Clock In</th>
          <th>Clock Out</th>
          <th style="text-align:center;">Hrs Worked</th>
          <th>Reason</th>
          <th>Status</th>
          <th style="width:140px;"></th>
        </tr></thead>
        <tbody>
          <?php if(empty($requests)): ?><tr class="empty-row"><td colspan="9">No ACL activity from your team.</td></tr>
          <?php else: foreach($requests as $r):
            $clockIn = $r['clock_in'] ? date('h:i A', strtotime($r['clock_in'])) : '—';
            $clockOut = $r['clock_out'] ? date('h:i A', strtotime($r['clock_out'])) : '—';
            $workHrs = $r['work_seconds'] ? sprintf('%d:%02d', floor($r['work_seconds']/3600), floor(($r['work_seconds']%3600)/60)) : number_format($r['hours'],1).'h';
          ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($r['emp_name'],0,1)) ?></div>
                <div><div class="td-name"><?= htmlspecialchars($r['emp_name']) ?></div><div class="td-sub"><?= htmlspecialchars($r['emp_code']??'') ?></div></div>
              </div>
            </td>
            <td class="font-semibold text-sm"><?= date('d M Y', strtotime($r['work_date'])) ?></td>
            <td class="text-sm text-muted"><?= date('l', strtotime($r['work_date'])) ?></td>
            <td class="text-sm"><?= $clockIn ?></td>
            <td class="text-sm"><?= $clockOut ?></td>
            <td style="text-align:center;font-weight:700;color:var(--brand);"><?= $workHrs ?></td>
            <td class="text-sm text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['reason']) ?>"><?= htmlspecialchars($r['reason']) ?></td>
            <td><span class="badge <?= $statusMap[$r['status']] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <?php if($r['status']==='pending'): ?>
              <div style="display:flex;gap:6px;">
                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="approve_acl"><input type="hidden" name="req_id" value="<?= $r['id'] ?>"><button type="submit" class="btn btn-sm" style="background:var(--green-bg);color:var(--green-text);border:1px solid #a7f3d0;">Approve</button></form>
                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="reject_acl"><input type="hidden" name="req_id" value="<?= $r['id'] ?>"><button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Reject</button></form>
              </div>
              <?php endif; ?>
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
