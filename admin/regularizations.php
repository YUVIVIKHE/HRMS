<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Auto-escalate all pending >3 days ───────────────────────
$db->exec("UPDATE attendance_regularizations SET escalated=1, escalated_at=NOW() WHERE status='pending' AND escalated=0 AND DATEDIFF(NOW(),created_at)>=3");

// ── POST: Approve / Reject ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $regId  = (int)($_POST['reg_id'] ?? 0);
    $note   = trim($_POST['review_note'] ?? '');

    if (in_array($action, ['approve_reg','reject_reg']) && $regId) {
        $reg = $db->prepare("SELECT * FROM attendance_regularizations WHERE id=? AND status='pending'");
        $reg->execute([$regId]); $reg = $reg->fetch();

        if ($reg) {
            $status = $action === 'approve_reg' ? 'approved' : 'rejected';
            $db->prepare("UPDATE attendance_regularizations SET status=?,reviewed_by=?,reviewed_at=NOW(),review_note=? WHERE id=?")
               ->execute([$status, $_SESSION['user_id'], $note, $regId]);

            if ($status === 'approved') {
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

// ── Filters ──────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'pending';
$filterRole   = $_GET['role']   ?? '';
$filterEsc    = isset($_GET['escalated']);

// Admin sees: manager's own regularizations + escalated employee regularizations
$where  = ["(u.role='manager' OR ar.escalated=1)"];
$params = [];
if ($filterStatus !== 'all') { $where[] = "ar.status=?"; $params[] = $filterStatus; }
if ($filterRole)  { $where[] = "u.role=?"; $params[] = $filterRole; }
if ($filterEsc)   { $where[] = "ar.escalated=1"; }

$regs = $db->prepare("
    SELECT ar.*, u.name AS emp_name, u.email AS emp_email, u.role AS emp_role,
           e.direct_manager_name AS manager_name
    FROM attendance_regularizations ar
    JOIN users u ON ar.user_id = u.id
    INNER JOIN employees e ON e.email = u.email
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ar.escalated DESC, ar.created_at ASC
");
$regs->execute($params);
$regs = $regs->fetchAll();

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM attendance_regularizations ar JOIN users u ON ar.user_id=u.id INNER JOIN employees e ON e.email=u.email WHERE (u.role='manager' OR ar.escalated=1) AND ar.status=?");
    $c->execute([$s]); $counts[$s] = (int)$c->fetchColumn();
}
$escalatedCount = (int)$db->query("SELECT COUNT(*) FROM attendance_regularizations WHERE escalated=1 AND status='pending'")->fetchColumn();
$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];
?>
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
      <span class="page-breadcrumb">Manager requests &amp; escalated employee corrections</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
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
    <div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;margin-bottom:16px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <strong><?= $escalatedCount ?> escalated regularization(s)</strong> — pending 3+ days without manager action.
      <a href="regularizations.php?escalated=1&status=pending" style="color:#92400e;font-weight:700;margin-left:8px;">View Escalated →</a>
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-text">
        <h1>Regularizations</h1>
        <p>Manager regularization requests and escalated employee requests.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <?php foreach(['pending'=>['badge-yellow','Pending'],'approved'=>['badge-green','Approved'],'rejected'=>['badge-red','Rejected']] as $s=>[$b,$l]): ?>
      <a href="regularizations.php?status=<?= $s ?>" style="text-decoration:none;">
        <div class="stat-card" style="<?= $filterStatus===$s&&!$filterEsc?'border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-light);':'' ?>">
          <div class="stat-body"><div class="stat-value"><?= $counts[$s] ?></div><div class="stat-label"><?= $l ?></div></div>
          <span class="badge <?= $b ?>" style="align-self:flex-start;"><?= $l ?></span>
        </div>
      </a>
      <?php endforeach; ?>
      <a href="regularizations.php?escalated=1&status=pending" style="text-decoration:none;">
        <div class="stat-card" style="<?= $filterEsc?'border-color:#f59e0b;box-shadow:0 0 0 2px #fef3c7;':'' ?>">
          <div class="stat-body"><div class="stat-value" style="color:#d97706;"><?= $escalatedCount ?></div><div class="stat-label">Escalated</div></div>
          <span class="badge badge-yellow" style="align-self:flex-start;">⚠ Escalated</span>
        </div>
      </a>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
      <?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $s=>$l): ?>
        <a href="regularizations.php?status=<?= $s ?>&role=<?= urlencode($filterRole) ?>" class="btn btn-sm <?= $filterStatus===$s&&!$filterEsc?'btn-primary':'btn-ghost' ?>"><?= $l ?></a>
      <?php endforeach; ?>
      <div style="margin-left:auto;">
        <select class="form-control" style="font-size:13px;padding:7px 12px;min-width:130px;" onchange="location.href='regularizations.php?status=<?= urlencode($filterStatus) ?>&role='+this.value">
          <option value="">All Roles</option>
          <option value="employee" <?= $filterRole==='employee'?'selected':'' ?>>Employee</option>
          <option value="manager"  <?= $filterRole==='manager' ?'selected':'' ?>>Manager</option>
        </select>
      </div>
    </div>

    <div class="table-wrap">
      <div class="table-toolbar">
        <h2><?= $filterEsc?'⚠ Escalated':'Regularization Requests' ?> <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($regs) ?>)</span></h2>
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="regSearch" placeholder="Search name…" oninput="filterRegs(this.value)">
        </div>
      </div>
      <table id="regTable">
        <thead>
          <tr><th>Employee</th><th>Role</th><th>Date</th><th>Req. In</th><th>Req. Out</th><th>Reason</th><th>Status</th><th style="width:160px;"></th></tr>
        </thead>
        <tbody>
          <?php if(empty($regs)): ?>
            <tr class="empty-row"><td colspan="8">No regularization requests found.</td></tr>
          <?php else: foreach($regs as $r):
            $isEsc = $r['escalated'] && $r['status']==='pending';
          ?>
          <tr class="reg-row" data-name="<?= htmlspecialchars(strtolower($r['emp_name'])) ?>" style="<?= $isEsc?'background:#fffbeb;':'' ?>">
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($r['emp_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($r['emp_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($r['emp_email']) ?></div>
                  <?php if($r['manager_name']): ?><div class="td-sub">Mgr: <?= htmlspecialchars($r['manager_name']) ?></div><?php endif; ?>
                </div>
              </div>
              <?php if($isEsc): ?><div><span class="badge badge-yellow" style="font-size:10px;margin-top:3px;">⚠ Escalated</span></div><?php endif; ?>
            </td>
            <td><span class="badge <?= $r['emp_role']==='manager'?'badge-brand':'badge-gray' ?>"><?= ucfirst($r['emp_role']) ?></span></td>
            <td class="font-semibold text-sm"><?= date('D, d M Y', strtotime($r['log_date'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_in'])) ?></td>
            <td class="text-sm"><?= date('h:i A', strtotime($r['req_clock_out'])) ?></td>
            <td class="text-muted text-sm" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['reason']) ?>"><?= htmlspecialchars($r['reason']) ?></td>
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
                <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;" onclick="openReject(<?= $r['id'] ?>)">
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

  </div>
</div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3>Reject Regularization</h3>
      <button class="modal-close" onclick="document.getElementById('rejectModal').classList.remove('open')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reject_reg">
      <input type="hidden" name="reg_id" id="reject_reg_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason <span class="req">*</span></label>
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
function openReject(id) { document.getElementById('reject_reg_id').value=id; document.getElementById('rejectModal').classList.add('open'); }
document.getElementById('rejectModal').addEventListener('click',e=>{if(e.target===document.getElementById('rejectModal'))document.getElementById('rejectModal').classList.remove('open');});
function filterRegs(q){q=q.toLowerCase();document.querySelectorAll('.reg-row').forEach(r=>{r.style.display=!q||r.dataset.name.includes(q)?'':'none';});}
</script>
</body>
</html>
