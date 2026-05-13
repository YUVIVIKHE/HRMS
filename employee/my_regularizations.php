<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST: Submit regularization ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'submit_regularization') {
    $logDate  = $_POST['log_date']      ?? '';
    $clockIn  = $_POST['req_clock_in']  ?? '';
    $clockOut = $_POST['req_clock_out'] ?? '';
    $reason   = trim($_POST['reason']   ?? '');
    $errors   = [];

    if (!$logDate)  $errors[] = 'Date is required.';
    if (!$clockIn)  $errors[] = 'Clock in time is required.';
    if (!$clockOut) $errors[] = 'Clock out time is required.';
    if ($clockIn && $clockOut && $clockIn >= $clockOut) $errors[] = 'Clock out must be after clock in.';
    if (!$reason)   $errors[] = 'Reason is required.';

    // Check no pending request for same date
    if (empty($errors)) {
        $dup = $db->prepare("SELECT id FROM attendance_regularizations WHERE user_id=? AND log_date=? AND status='pending'");
        $dup->execute([$uid, $logDate]);
        if ($dup->fetch()) $errors[] = 'A pending regularization request already exists for this date.';
    }

    if (empty($errors)) {
        $db->prepare("INSERT INTO attendance_regularizations (user_id,log_date,req_clock_in,req_clock_out,reason) VALUES (?,?,?,?,?)")
           ->execute([$uid, $logDate, $clockIn, $clockOut, $reason]);
        $_SESSION['flash_success'] = "Regularization request submitted for ".date('d M Y', strtotime($logDate)).".";
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
    }
    header("Location: my_regularizations.php"); exit;
}

// ── POST: Cancel request ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'cancel_reg') {
    $regId = (int)$_POST['reg_id'];
    $db->prepare("DELETE FROM attendance_regularizations WHERE id=? AND user_id=? AND status='pending'")
       ->execute([$regId, $uid]);
    $_SESSION['flash_success'] = "Request cancelled.";
    header("Location: my_regularizations.php"); exit;
}

// ── Data ─────────────────────────────────────────────────────
$regs = $db->prepare("
    SELECT * FROM attendance_regularizations
    WHERE user_id = ?
    ORDER BY log_date DESC, created_at DESC
");
$regs->execute([$uid]);
$regs = $regs->fetchAll();

$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Regularizations – HRMS Portal</title>
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
      <span class="page-title">My Regularizations</span>
      <span class="page-breadcrumb">Attendance correction requests</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
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
        <h1>My Regularizations</h1>
        <p>View and track your attendance correction requests.</p>
      </div>
      <div class="page-header-actions">
        <a href="attendance.php" class="btn btn-secondary">← Back to Attendance</a>
      </div>
    </div>

    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Regularization Requests <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($regs) ?>)</span></h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Requested Clock In</th>
            <th>Requested Clock Out</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Review Note</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($regs)): ?>
            <tr class="empty-row"><td colspan="8">No regularization requests yet. Use the Regularize button on your attendance page.</td></tr>
          <?php else: foreach($regs as $r): ?>
          <tr>
            <td class="font-semibold text-sm"><?= date('D, d M Y', strtotime($r['log_date'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_in'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_out'])) ?></td>
            <td class="text-muted text-sm" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['reason']) ?>"><?= htmlspecialchars($r['reason']) ?></td>
            <td><span class="badge <?= $statusMap[$r['status']]??'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
            <td class="text-muted text-sm"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($r['review_note'] ?: '—') ?></td>
            <td>
              <?php if($r['status'] === 'pending'): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this request?')">
                <input type="hidden" name="action" value="cancel_reg">
                <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Cancel</button>
              </form>
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
