<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Auto-escalate: mark pending >3 days as escalated ────────
// Find this manager's department via their employee record
$managerEmp = $db->prepare("SELECT e.department_id FROM employees e JOIN users u ON e.email=u.email WHERE u.id=?");
$managerEmp->execute([$uid]);
$managerDeptId = $managerEmp->fetchColumn();

// Escalate pending leaves from employees in same department
if ($managerDeptId) {
    $db->prepare("
        UPDATE leave_applications la
        JOIN users u ON la.user_id = u.id
        JOIN employees e ON e.email = u.email
        SET la.escalated = 1, la.escalated_at = NOW()
        WHERE e.department_id = ?
          AND u.id != ?
          AND la.status = 'pending'
          AND la.escalated = 0
          AND DATEDIFF(NOW(), la.created_at) >= 3
    ")->execute([$managerDeptId, $uid]);
}

// ── POST handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appId  = (int)($_POST['app_id'] ?? 0);
    $note   = trim($_POST['review_note'] ?? '');

    if (in_array($action, ['approve_leave','reject_leave']) && $appId) {
        // Verify this leave belongs to an employee in this manager's department
        $app = $db->prepare("
            SELECT la.* FROM leave_applications la
            JOIN users u ON la.user_id = u.id
            WHERE la.id = ? AND la.status = 'pending'
              AND EXISTS (
                SELECT 1 FROM employees e
                WHERE e.email = u.email AND e.department_id = ?
              )
              AND u.id != ?
        ");
        $app->execute([$appId, $managerDeptId ?: 0, $uid]);
        $app = $app->fetch();

        if ($app) {
            $status = $action === 'approve_leave' ? 'approved' : 'rejected';
            $db->prepare("UPDATE leave_applications SET status=?,reviewed_by=?,reviewed_at=NOW(),review_note=? WHERE id=?")
               ->execute([$status, $uid, $note, $appId]);

            if ($status === 'approved') {
                // Deduct from balance
                $db->prepare("UPDATE leave_balances SET balance=balance-?, used=used+? WHERE user_id=? AND leave_type_id=?")
                   ->execute([$app['days'], $app['days'], $app['user_id'], $app['leave_type_id']]);
            }
            $_SESSION['flash_success'] = "Leave ".ucfirst($status)." successfully.";
        } else {
            $_SESSION['flash_error'] = "Application not found or already reviewed.";
        }
        header("Location: leave_requests.php"); exit;
    }
}

// ── Data ─────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'pending';

// Manager sees employees in their department (dept-based, no manager_id column)
$where  = ["(EXISTS (SELECT 1 FROM employees e WHERE e.email=u.email AND e.department_id=?) AND u.id!=?)"];
$params = [$managerDeptId ?: 0, $uid];
if ($filterStatus && $filterStatus !== 'all') {
    $where[] = "la.status = ?";
    $params[] = $filterStatus;
}

$requests = $db->prepare("
    SELECT la.*, lt.name AS type_name, lt.color,
           u.name AS emp_name, u.email AS emp_email,
           lb.balance AS remaining_balance
    FROM leave_applications la
    JOIN users u ON la.user_id = u.id
    JOIN leave_types lt ON la.leave_type_id = lt.id
    LEFT JOIN leave_balances lb ON lb.user_id = la.user_id AND lb.leave_type_id = la.leave_type_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY la.escalated DESC, la.created_at ASC
");
$requests->execute($params);
$requests = $requests->fetchAll();

// Counts using same combined condition
$counts = [];
foreach (['pending','approved','rejected','cancelled'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM leave_applications la JOIN users u ON la.user_id=u.id
        WHERE EXISTS(SELECT 1 FROM employees e WHERE e.email=u.email AND e.department_id=? AND u.id!=?)
        AND la.status=?");
    $c->execute([$managerDeptId ?: 0, $uid, $s]);
    $counts[$s] = (int)$c->fetchColumn();
}

$escalatedStmt = $db->prepare("SELECT COUNT(*) FROM leave_applications la JOIN users u ON la.user_id=u.id
    WHERE EXISTS(SELECT 1 FROM employees e WHERE e.email=u.email AND e.department_id=? AND u.id!=?)
    AND la.escalated=1 AND la.status='pending'");
$escalatedStmt->execute([$managerDeptId ?: 0, $uid]);
$escalatedCount = (int)$escalatedStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leave Requests – HRMS Portal</title>
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
      <span class="page-title">Leave Requests</span>
      <span class="page-breadcrumb">Team leave approvals</span>
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

    <?php if($escalatedCount > 0): ?>
    <div class="alert alert-warning" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <strong><?= $escalatedCount ?> leave request(s) pending for 3+ days</strong> — these have been escalated to admin. Please review them urgently.
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-text">
        <h1>Team Leave Requests</h1>
        <p>Review and action leave applications from your team members.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <?php foreach(['pending'=>['badge-yellow','Pending'],'approved'=>['badge-green','Approved'],'rejected'=>['badge-red','Rejected'],'cancelled'=>['badge-gray','Cancelled']] as $s=>[$badge,$label]): ?>
      <a href="leave_requests.php?status=<?= $s ?>" style="text-decoration:none;">
        <div class="stat-card" style="<?= $filterStatus===$s?'border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-light);':'' ?>">
          <div class="stat-body">
            <div class="stat-value"><?= $counts[$s] ?></div>
            <div class="stat-label"><?= $label ?></div>
          </div>
          <span class="badge <?= $badge ?>" style="align-self:flex-start;"><?= $label ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
      <?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $s=>$l): ?>
        <a href="leave_requests.php?status=<?= $s ?>"
           class="btn btn-sm <?= $filterStatus===$s?'btn-primary':'btn-ghost' ?>">
          <?= $l ?> <?php if($s==='pending' && $counts['pending']>0): ?><span style="background:rgba(255,255,255,.3);border-radius:10px;padding:1px 6px;font-size:11px;"><?= $counts['pending'] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Requests table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Leave Requests <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($requests) ?>)</span></h2>
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="lrSearch" placeholder="Search employee…" oninput="filterLR(this.value)">
        </div>
      </div>
      <table id="lrTable">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Leave Type</th>
            <th>From</th>
            <th>To</th>
            <th>Days</th>
            <th>Balance Left</th>
            <th>Reason</th>
            <th>Applied</th>
            <th>Status</th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($requests)): ?>
            <tr class="empty-row"><td colspan="10">No leave requests found.</td></tr>
          <?php else: foreach($requests as $r):
            $sm = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red','cancelled'=>'badge-gray'];
            $isEscalated = $r['escalated'] && $r['status']==='pending';
          ?>
          <tr class="lr-row" data-name="<?= htmlspecialchars(strtolower($r['emp_name'])) ?>"
              style="<?= $isEscalated?'background:#fffbeb;':'' ?>">
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($r['emp_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($r['emp_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($r['emp_email']) ?></div>
                </div>
              </div>
              <?php if($isEscalated): ?>
                <span class="badge badge-yellow" style="font-size:10px;margin-top:4px;">⚠ Escalated</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;">
                <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($r['color']) ?>;flex-shrink:0;"></span>
                <span class="text-sm font-semibold"><?= htmlspecialchars($r['type_name']) ?></span>
              </div>
            </td>
            <td class="text-sm"><?= date('d M Y', strtotime($r['from_date'])) ?></td>
            <td class="text-sm"><?= date('d M Y', strtotime($r['to_date'])) ?></td>
            <td class="font-semibold"><?= $r['days'] ?></td>
            <td>
              <?php $rem = (float)($r['remaining_balance'] ?? 0); ?>
              <span style="font-weight:600;color:<?= $rem < $r['days']?'var(--red)':'var(--green)' ?>;">
                <?= number_format($rem, 1) ?> days
              </span>
            </td>
            <td class="text-muted text-sm" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['reason']??'') ?>">
              <?= htmlspecialchars($r['reason'] ?: '—') ?>
            </td>
            <td class="text-muted text-sm"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td><span class="badge <?= $sm[$r['status']]??'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <?php if($r['status'] === 'pending'): ?>
              <div style="display:flex;gap:6px;">
                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this leave for <?= addslashes($r['emp_name']) ?>?')">
                  <input type="hidden" name="action" value="approve_leave">
                  <input type="hidden" name="app_id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--green-bg);color:var(--green-text);border:1px solid #a7f3d0;">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Approve
                  </button>
                </form>
                <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;"
                  onclick="openReject(<?= $r['id'] ?>, '<?= addslashes($r['emp_name']) ?>')">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  Reject
                </button>
              </div>
              <?php elseif($r['review_note']): ?>
                <span style="font-size:12px;color:var(--muted);" title="<?= htmlspecialchars($r['review_note']) ?>">Note: <?= htmlspecialchars(substr($r['review_note'],0,30)) ?>…</span>
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

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3>Reject Leave Request</h3>
      <button class="modal-close" onclick="document.getElementById('rejectModal').classList.remove('open')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reject_leave">
      <input type="hidden" name="app_id" id="reject_app_id">
      <div class="modal-body">
        <p id="reject_msg" style="font-size:13.5px;color:var(--muted);margin-bottom:16px;"></p>
        <div class="form-group">
          <label>Reason for Rejection <span class="req">*</span></label>
          <textarea name="review_note" class="form-control" rows="3" placeholder="Provide a reason…" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejectModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject Leave</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReject(id, name) {
  document.getElementById('reject_app_id').value = id;
  document.getElementById('reject_msg').textContent = 'Rejecting leave for: ' + name;
  document.getElementById('rejectModal').classList.add('open');
}
document.getElementById('rejectModal').addEventListener('click', e => { if(e.target===document.getElementById('rejectModal')) document.getElementById('rejectModal').classList.remove('open'); });

function filterLR(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.lr-row').forEach(r => { r.style.display = !q || r.dataset.name.includes(q) ? '' : 'none'; });
}
</script>
</body>
</html>
