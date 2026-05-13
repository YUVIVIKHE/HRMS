<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Get manager's department
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]);
$managerDeptId = $managerEmp->fetchColumn();

// ── POST: Submit own regularization ─────────────────────────
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

    if (empty($errors)) {
        $dup = $db->prepare("SELECT id FROM attendance_regularizations WHERE user_id=? AND log_date=? AND status='pending'");
        $dup->execute([$uid, $logDate]);
        if ($dup->fetch()) $errors[] = 'A pending request already exists for this date.';
    }

    if (empty($errors)) {
        $db->prepare("INSERT INTO attendance_regularizations (user_id,log_date,req_clock_in,req_clock_out,reason) VALUES (?,?,?,?,?)")
           ->execute([$uid, $logDate, $clockIn, $clockOut, $reason]);
        $_SESSION['flash_success'] = "Regularization request submitted for ".date('d M Y', strtotime($logDate)).".";
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
    }
    header("Location: regularizations.php"); exit;
}

// ── Auto-escalate pending >3 days ────────────────────────────
if ($managerDeptId) {
    $db->prepare("
        UPDATE attendance_regularizations ar
        JOIN users u ON ar.user_id = u.id
        JOIN employees e ON e.email = u.email
        SET ar.escalated = 1, ar.escalated_at = NOW()
        WHERE e.department_id = ? AND u.id != ?
          AND ar.status = 'pending' AND ar.escalated = 0
          AND DATEDIFF(NOW(), ar.created_at) >= 3
    ")->execute([$managerDeptId, $uid]);
}

// ── POST: Approve / Reject ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $regId  = (int)($_POST['reg_id'] ?? 0);
    $note   = trim($_POST['review_note'] ?? '');

    if (in_array($action, ['approve_reg','reject_reg']) && $regId) {
        // Verify belongs to dept employee
        $reg = $db->prepare("
            SELECT ar.* FROM attendance_regularizations ar
            JOIN users u ON ar.user_id = u.id
            JOIN employees e ON e.email = u.email
            WHERE ar.id = ? AND ar.status = 'pending'
              AND (u.manager_id = ? OR e.department_id = ?)
        ");
        $reg->execute([$regId, $uid, $managerDeptId]);
        $reg = $reg->fetch();

        if ($reg) {
            $status = $action === 'approve_reg' ? 'approved' : 'rejected';
            $db->prepare("UPDATE attendance_regularizations SET status=?,reviewed_by=?,reviewed_at=NOW(),review_note=? WHERE id=?")
               ->execute([$status, $uid, $note, $regId]);

            if ($status === 'approved') {
                // Update attendance_logs with the requested times
                $clockIn  = $reg['log_date'].' '.$reg['req_clock_in'];
                $clockOut = $reg['log_date'].' '.$reg['req_clock_out'];
                $workSec  = strtotime($clockOut) - strtotime($clockIn);

                $existing = $db->prepare("SELECT id FROM attendance_logs WHERE user_id=? AND log_date=?");
                $existing->execute([$reg['user_id'], $reg['log_date']]);
                if ($existing->fetch()) {
                    $db->prepare("UPDATE attendance_logs SET clock_in=?,clock_out=?,work_seconds=?,status='present' WHERE user_id=? AND log_date=?")
                       ->execute([$clockIn, $clockOut, $workSec, $reg['user_id'], $reg['log_date']]);
                } else {
                    $db->prepare("INSERT INTO attendance_logs (user_id,log_date,clock_in,clock_out,work_seconds,status) VALUES (?,?,?,?,?,'present')")
                       ->execute([$reg['user_id'], $reg['log_date'], $clockIn, $clockOut, $workSec]);
                }
            }
            $_SESSION['flash_success'] = "Regularization ".ucfirst($status).".";
        } else {
            $_SESSION['flash_error'] = "Request not found or already reviewed.";
        }
        header("Location: regularizations.php"); exit;
    }
}

// ── Data ─────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'pending';
$where  = ["ar.status != 'cancelled'", "(u.manager_id=? OR e.department_id=?)", "u.id != ?"];
$params = [$uid, $managerDeptId ?: 0, $uid];
if ($filterStatus !== 'all') { $where[] = "ar.status=?"; $params[] = $filterStatus; }

$regs = $db->prepare("
    SELECT ar.*, u.name AS emp_name, u.email AS emp_email
    FROM attendance_regularizations ar
    JOIN users u ON ar.user_id = u.id
    JOIN employees e ON e.email = u.email
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ar.escalated DESC, ar.created_at ASC
");
$regs->execute($params);
$regs = $regs->fetchAll();

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM attendance_regularizations ar JOIN users u ON ar.user_id=u.id JOIN employees e ON e.email=u.email WHERE (u.manager_id=? OR e.department_id=?) AND u.id!=? AND ar.status=?");
    $c->execute([$uid, $managerDeptId ?: 0, $uid, $s]); $counts[$s] = (int)$c->fetchColumn();
}
$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];

// Manager's own regularization requests
$myRegs = $db->prepare("SELECT * FROM attendance_regularizations WHERE user_id=? ORDER BY log_date DESC");
$myRegs->execute([$uid]); $myRegs = $myRegs->fetchAll();?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Regularizations – HRMS Portal</title>
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
      <span class="page-title">Regularizations</span>
      <span class="page-breadcrumb">Team attendance correction requests</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
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
      <div class="page-header-text"><h1>Team Regularizations</h1><p>Review and action attendance correction requests from your team.</p></div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
      <?php foreach(['pending'=>['badge-yellow','Pending'],'approved'=>['badge-green','Approved'],'rejected'=>['badge-red','Rejected']] as $s=>[$b,$l]): ?>
      <a href="regularizations.php?status=<?= $s ?>" style="text-decoration:none;">
        <div class="stat-card" style="<?= $filterStatus===$s?'border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-light);':'' ?>">
          <div class="stat-body"><div class="stat-value"><?= $counts[$s] ?></div><div class="stat-label"><?= $l ?></div></div>
          <span class="badge <?= $b ?>" style="align-self:flex-start;"><?= $l ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Filter -->
    <div style="display:flex;gap:8px;margin-bottom:16px;">
      <?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $s=>$l): ?>
        <a href="regularizations.php?status=<?= $s ?>" class="btn btn-sm <?= $filterStatus===$s?'btn-primary':'btn-ghost' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap">
      <div class="table-toolbar"><h2>Requests <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($regs) ?>)</span></h2></div>
      <table>
        <thead>
          <tr><th>Employee</th><th>Date</th><th>Req. Clock In</th><th>Req. Clock Out</th><th>Reason</th><th>Submitted</th><th>Status</th><th style="width:160px;"></th></tr>
        </thead>
        <tbody>
          <?php if(empty($regs)): ?>
            <tr class="empty-row"><td colspan="8">No regularization requests.</td></tr>
          <?php else: foreach($regs as $r):
            $isEsc = $r['escalated'] && $r['status']==='pending';
          ?>
          <tr style="<?= $isEsc?'background:#fffbeb;':'' ?>">
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($r['emp_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($r['emp_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($r['emp_email']) ?></div>
                </div>
              </div>
              <?php if($isEsc): ?><div><span class="badge badge-yellow" style="font-size:10px;margin-top:3px;">⚠ Escalated</span></div><?php endif; ?>
            </td>
            <td class="font-semibold text-sm"><?= date('D, d M Y', strtotime($r['log_date'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_in'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_out'])) ?></td>
            <td class="text-muted text-sm" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['reason']) ?>"><?= htmlspecialchars($r['reason']) ?></td>
            <td class="text-muted text-sm"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td><span class="badge <?= $statusMap[$r['status']]??'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <?php if($r['status']==='pending'): ?>
              <div style="display:flex;gap:6px;">
                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this regularization?')">
                  <input type="hidden" name="action" value="approve_reg">
                  <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--green-bg);color:var(--green-text);border:1px solid #a7f3d0;">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Approve
                  </button>
                </form>
                <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;"
                  onclick="openReject(<?= $r['id'] ?>)">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject
                </button>
              </div>
              <?php elseif($r['review_note']): ?>
                <span style="font-size:12px;color:var(--muted);" title="<?= htmlspecialchars($r['review_note']) ?>">Note ℹ</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- My own regularization requests -->
    <?php if(!empty($myRegs)): ?>
    <div class="table-wrap" style="margin-top:24px;">
      <div class="table-toolbar"><h2>My Regularization Requests</h2></div>
      <table>
        <thead><tr><th>Date</th><th>Req. Clock In</th><th>Req. Clock Out</th><th>Reason</th><th>Status</th><th>Review Note</th></tr></thead>
        <tbody>
          <?php foreach($myRegs as $r): ?>
          <tr>
            <td class="font-semibold text-sm"><?= date('D, d M Y', strtotime($r['log_date'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_in'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_out'])) ?></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($r['reason']) ?></td>
            <td><span class="badge <?= $statusMap[$r['status']]??'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($r['review_note'] ?: '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>

<!-- Reject Modal -->
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3>Reject Regularization</h3>
      <button class="modal-close" onclick="document.getElementById('rejectModal').classList.remove('open')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reject_reg">
      <input type="hidden" name="reg_id" id="reject_reg_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason for Rejection <span class="req">*</span></label>
          <textarea name="review_note" class="form-control" rows="3" required placeholder="Provide a reason…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejectModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReject(id) {
  document.getElementById('reject_reg_id').value = id;
  document.getElementById('rejectModal').classList.add('open');
}
document.getElementById('rejectModal').addEventListener('click', e => { if(e.target===document.getElementById('rejectModal')) document.getElementById('rejectModal').classList.remove('open'); });
</script>
</body>
</html>
