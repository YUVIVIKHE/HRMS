<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// POST: Submit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'submit_acl') {
    $workDate = trim($_POST['work_date'] ?? '');
    $reason   = trim($_POST['reason'] ?? '');
    $hours    = (float)($_POST['hours'] ?? 9);
    if (!$workDate) { $errorMsg = 'Select a date.'; }
    elseif (!$reason) { $errorMsg = 'Provide a reason.'; }
    else {
        // Check if already requested for this date
        $chk = $db->prepare("SELECT id FROM acl_requests WHERE user_id=? AND work_date=?");
        $chk->execute([$uid, $workDate]);
        if ($chk->fetch()) { $errorMsg = 'Already requested for this date.'; }
        else {
            $db->prepare("INSERT INTO acl_requests (user_id, work_date, reason, hours) VALUES (?,?,?,?)")
               ->execute([$uid, $workDate, $reason, $hours]);
            $_SESSION['flash_success'] = "ACL request submitted for " . date('d M Y', strtotime($workDate)) . ".";
            header("Location: acl_request.php"); exit;
        }
    }
}

// My requests
$requests = $db->prepare("SELECT * FROM acl_requests WHERE user_id=? ORDER BY created_at DESC");
$requests->execute([$uid]);
$requests = $requests->fetchAll();

$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ACL Request – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left"><span class="page-title">ACL Request</span><span class="page-breadcrumb">Request to work on holidays/weekends</span></div>
    <div class="topbar-right"><span class="role-chip">Employee</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">
    <?php if($successMsg): ?><div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <!-- Request Form -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2>New ACL Request</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="submit_acl">
          <div class="form-grid" style="max-width:500px;">
            <div class="form-group">
              <label>Work Date (Holiday/Weekend) <span class="req">*</span></label>
              <input type="date" name="work_date" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Hours to Work</label>
              <input type="number" name="hours" class="form-control" value="9" min="1" max="12" step="0.5">
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;max-width:500px;">
            <label>Reason <span class="req">*</span></label>
            <textarea name="reason" class="form-control" rows="2" placeholder="Why do you need to work on this holiday?" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="margin-top:12px;">Submit Request</button>
        </form>
      </div>
    </div>

    <!-- My Requests -->
    <div class="table-wrap">
      <div class="table-toolbar"><h2>My ACL Requests <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($requests) ?>)</span></h2></div>
      <table>
        <thead><tr><th>Date</th><th>Reason</th><th style="text-align:center;">Hours</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
          <?php if(empty($requests)): ?>
            <tr class="empty-row"><td colspan="5">No ACL requests yet.</td></tr>
          <?php else: foreach($requests as $r): ?>
          <tr>
            <td class="font-semibold"><?= date('D, d M Y', strtotime($r['work_date'])) ?></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($r['reason']) ?></td>
            <td style="text-align:center;font-weight:700;"><?= $r['hours'] ?></td>
            <td><span class="badge <?= $statusMap[$r['status']] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($r['review_note'] ?? '') ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:16px;"><a href="my_acl.php" class="btn btn-secondary">← Back to My ACL</a></div>
  </div>
</div>
</div>
</body>
</html>
