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
    $note   = trim($_POST['review_note'] ?? '');
    if (in_array($action, ['approve_acl','reject_acl']) && $reqId) {
        $status = $action === 'approve_acl' ? 'approved' : 'rejected';
        $db->prepare("UPDATE acl_requests SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?")
           ->execute([$status, $uid, $note, $reqId]);
        $_SESSION['flash_success'] = "ACL request " . $status . ".";
        header("Location: acl_requests.php"); exit;
    }
}

// Get employee ACL requests (employees in manager's department)
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]); $deptId = $managerEmp->fetchColumn();

$requests = [];
if ($deptId) {
    $requests = $db->prepare("
        SELECT ar.*, u.name AS emp_name, u.role AS emp_role
        FROM acl_requests ar
        JOIN users u ON ar.user_id = u.id
        JOIN employees e ON e.email = u.email
        WHERE e.department_id = ? AND u.role = 'employee'
        ORDER BY ar.status = 'pending' DESC, ar.created_at DESC
    ");
    $requests->execute([$deptId]);
    $requests = $requests->fetchAll();
}
$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ACL Requests – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left"><span class="page-title">Team ACL Requests</span><span class="page-breadcrumb">Approve/reject employee holiday work requests</span></div>
    <div class="topbar-right"><span class="role-chip">Manager</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">
    <?php if($successMsg): ?><div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <div class="table-wrap">
      <div class="table-toolbar"><h2>Employee ACL Requests <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($requests) ?>)</span></h2></div>
      <table>
        <thead><tr><th>Employee</th><th>Work Date</th><th>Reason</th><th style="text-align:center;">Hours</th><th>Status</th><th style="width:160px;"></th></tr></thead>
        <tbody>
          <?php if(empty($requests)): ?><tr class="empty-row"><td colspan="6">No ACL requests.</td></tr>
          <?php else: foreach($requests as $r): ?>
          <tr>
            <td class="font-semibold"><?= htmlspecialchars($r['emp_name']) ?></td>
            <td><?= date('D, d M Y', strtotime($r['work_date'])) ?></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($r['reason']) ?></td>
            <td style="text-align:center;font-weight:700;"><?= $r['hours'] ?></td>
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
