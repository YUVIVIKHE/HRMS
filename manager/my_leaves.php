<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST: Apply leave ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'apply_leave') {
    $typeId = (int)$_POST['leave_type_id'];
    $from   = $_POST['from_date'] ?? '';
    $to     = $_POST['to_date']   ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $errors = [];

    if (!$typeId) $errors[] = 'Select a leave type.';
    if (!$from)   $errors[] = 'From date is required.';
    if (!$to)     $errors[] = 'To date is required.';
    if ($from && $to && $from > $to) $errors[] = 'From date must be before To date.';
    if ($from && $from < date('Y-m-d')) $errors[] = 'Cannot apply for past dates.';

    if (empty($errors)) {
        $days = 0;
        $d = new DateTime($from); $e = new DateTime($to);
        while ($d <= $e) { if ((int)$d->format('N') <= 6) $days++; $d->modify('+1 day'); }
        if ($days <= 0) $errors[] = 'No working days in selected range.';

        $bal = $db->prepare("SELECT balance FROM leave_balances WHERE user_id=? AND leave_type_id=?");
        $bal->execute([$uid,$typeId]); $bal = $bal->fetchColumn();
        if ($bal === false || $bal < $days) $errors[] = "Insufficient balance. Available: ".($bal ?: 0).", Requested: $days.";

        // Check overlap — any leave type, excluding rejected/cancelled
        $overlap = $db->prepare("SELECT id FROM leave_applications WHERE user_id=? AND status NOT IN ('rejected','cancelled') AND from_date<=? AND to_date>=?");
        $overlap->execute([$uid, $to, $from]);
        if ($overlap->fetch()) $errors[] = 'You already have an active leave application overlapping these dates.';
    }

    if (empty($errors)) {
        $days = 0;
        $d = new DateTime($from); $e = new DateTime($to);
        while ($d <= $e) { if ((int)$d->format('N') <= 6) $days++; $d->modify('+1 day'); }
        $db->prepare("INSERT INTO leave_applications (user_id,leave_type_id,from_date,to_date,days,reason) VALUES (?,?,?,?,?,?)")
           ->execute([$uid,$typeId,$from,$to,$days,$reason]);
        $_SESSION['flash_success'] = "Leave application submitted for $days working day(s).";
        header("Location: my_leaves.php"); exit;
    } else { $errorMsg = implode(' ', $errors); }
}

// ── POST: Cancel own leave ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'cancel_leave') {
    $appId = (int)$_POST['app_id'];
    $app   = $db->prepare("SELECT * FROM leave_applications WHERE id=? AND user_id=?");
    $app->execute([$appId,$uid]); $app = $app->fetch();
    if ($app && $app['status'] === 'pending') {
        $db->prepare("UPDATE leave_applications SET status='cancelled' WHERE id=?")->execute([$appId]);
        $_SESSION['flash_success'] = "Leave cancelled.";
    }
    header("Location: my_leaves.php"); exit;
}

// ── POST: Approve/Reject team leave ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action']??'', ['approve_leave','reject_leave'])) {
    $appId  = (int)$_POST['app_id'];
    $note   = trim($_POST['review_note'] ?? '');
    $status = $_POST['action'] === 'approve_leave' ? 'approved' : 'rejected';

    $app = $db->prepare("SELECT la.*, u.manager_id FROM leave_applications la JOIN users u ON la.user_id=u.id WHERE la.id=?");
    $app->execute([$appId]); $app = $app->fetch();

    if ($app && $app['manager_id'] == $uid) {
        $db->prepare("UPDATE leave_applications SET status=?,reviewed_by=?,reviewed_at=NOW(),review_note=? WHERE id=?")
           ->execute([$status,$uid,$note,$appId]);

        // Deduct balance on approval
        if ($status === 'approved') {
            $db->prepare("UPDATE leave_balances SET balance=balance-?, used=used+? WHERE user_id=? AND leave_type_id=?")
               ->execute([$app['days'],$app['days'],$app['user_id'],$app['leave_type_id']]);
        }
        $_SESSION['flash_success'] = "Leave ".ucfirst($status).".";
    }
    header("Location: my_leaves.php"); exit;
}

// ── Data ─────────────────────────────────────────────────────
$leaveTypes = $db->query("SELECT * FROM leave_types WHERE is_active=1 ORDER BY name")->fetchAll();

$balances = [];
foreach ($leaveTypes as $lt) {
    $b = $db->prepare("SELECT balance, used FROM leave_balances WHERE user_id=? AND leave_type_id=?");
    $b->execute([$uid,$lt['id']]); $b = $b->fetch();
    $balances[$lt['id']] = ['balance'=>(float)($b['balance']??0),'used'=>(float)($b['used']??0)];
}

$myApps = $db->prepare("SELECT la.*, lt.name AS type_name, lt.color FROM leave_applications la JOIN leave_types lt ON la.leave_type_id=lt.id WHERE la.user_id=? ORDER BY la.created_at DESC");
$myApps->execute([$uid]); $myApps = $myApps->fetchAll();

// Team pending leaves
$teamLeaves = $db->prepare("
    SELECT la.*, lt.name AS type_name, lt.color, u.name AS emp_name
    FROM leave_applications la
    JOIN leave_types lt ON la.leave_type_id=lt.id
    JOIN users u ON la.user_id=u.id
    WHERE u.manager_id=? AND la.status='pending'
    ORDER BY la.from_date ASC
");
$teamLeaves->execute([$uid]); $teamLeaves = $teamLeaves->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Leaves – HRMS Portal</title>
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
      <span class="page-title">Leaves</span>
      <span class="page-breadcrumb">My Balance &amp; Team Requests</span>
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

    <!-- Balance Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
      <?php foreach($leaveTypes as $lt):
        $b = $balances[$lt['id']] ?? ['balance'=>0,'used'=>0];
        $total = $b['balance'] + $b['used'];
        $pct   = $total > 0 ? min(100, round(($b['used']/$total)*100)) : 0;
      ?>
      <div class="card" style="padding:20px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($lt['color']) ?>;flex-shrink:0;"></span>
          <span style="font-size:13px;font-weight:700;"><?= htmlspecialchars($lt['name']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <div style="text-align:center;"><div style="font-size:22px;font-weight:800;"><?= number_format($b['balance'],1) ?></div><div style="font-size:11px;color:var(--muted);">Available</div></div>
          <div style="text-align:center;"><div style="font-size:22px;font-weight:800;color:var(--red);"><?= number_format($b['used'],1) ?></div><div style="font-size:11px;color:var(--muted);">Used</div></div>
          <div style="text-align:center;"><div style="font-size:22px;font-weight:800;color:var(--muted);"><?= number_format($total,1) ?></div><div style="font-size:11px;color:var(--muted);">Total</div></div>
        </div>
        <div style="height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= htmlspecialchars($lt['color']) ?>;border-radius:3px;"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:right;"><?= $pct ?>% used</div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Team Pending Leaves -->
    <?php if(!empty($teamLeaves)): ?>
    <div class="table-wrap" style="margin-bottom:24px;">
      <div class="table-toolbar">
        <h2>Team Leave Requests <span class="badge badge-yellow" style="margin-left:8px;"><?= count($teamLeaves) ?> pending</span></h2>
      </div>
      <table>
        <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($teamLeaves as $tl): ?>
          <tr>
            <td class="font-semibold"><?= htmlspecialchars($tl['emp_name']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;">
                <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($tl['color']) ?>;"></span>
                <span class="text-sm"><?= htmlspecialchars($tl['type_name']) ?></span>
              </div>
            </td>
            <td class="text-sm"><?= date('d M Y', strtotime($tl['from_date'])) ?></td>
            <td class="text-sm"><?= date('d M Y', strtotime($tl['to_date'])) ?></td>
            <td class="font-semibold"><?= $tl['days'] ?></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($tl['reason'] ?: '—') ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this leave?')">
                  <input type="hidden" name="action" value="approve_leave">
                  <input type="hidden" name="app_id" value="<?= $tl['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--green-bg);color:var(--green-text);border:1px solid #a7f3d0;">Approve</button>
                </form>
                <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;"
                  onclick="openReject(<?= $tl['id'] ?>)">Reject</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:380px 1fr;gap:20px;align-items:start;">
      <!-- Apply Form -->
      <div class="card">
        <div class="card-header"><div><h2>Apply for Leave</h2></div></div>
        <div class="card-body">
          <form method="POST" onsubmit="return validateLeaveForm()">
            <input type="hidden" name="action" value="apply_leave">
            <div class="form-group" style="margin-bottom:14px;">
              <label>Leave Type <span class="req">*</span></label>
              <select name="leave_type_id" id="applyType" class="form-control" required onchange="updateBalance()">
                <option value="">Select type…</option>
                <?php foreach($leaveTypes as $lt): ?>
                  <option value="<?= $lt['id'] ?>" data-balance="<?= $balances[$lt['id']]['balance']??0 ?>">
                    <?= htmlspecialchars($lt['name']) ?> (<?= number_format($balances[$lt['id']]['balance']??0,1) ?> available)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
              <div class="form-group"><label>From <span class="req">*</span></label><input type="date" name="from_date" id="applyFrom" class="form-control" min="<?= date('Y-m-d') ?>" required onchange="calcDays()"></div>
              <div class="form-group"><label>To <span class="req">*</span></label><input type="date" name="to_date" id="applyTo" class="form-control" min="<?= date('Y-m-d') ?>" required onchange="calcDays()"></div>
            </div>
            <div id="daysPreview" style="display:none;border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:13px;font-weight:600;"></div>
            <div class="form-group" style="margin-bottom:20px;">
              <label>Reason</label>
              <textarea name="reason" class="form-control" rows="3" style="resize:vertical;"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Submit Application</button>
          </form>
        </div>
      </div>

      <!-- My Applications -->
      <div class="table-wrap">
        <div class="table-toolbar"><h2>My Applications</h2></div>
        <table>
          <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Applied</th><th></th></tr></thead>
          <tbody>
            <?php if(empty($myApps)): ?>
              <tr class="empty-row"><td colspan="7">No applications yet.</td></tr>
            <?php else: foreach($myApps as $app):
              $sm = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red','cancelled'=>'badge-gray'];
            ?>
            <tr>
              <td><div style="display:flex;align-items:center;gap:6px;"><span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($app['color']) ?>;"></span><span class="font-semibold text-sm"><?= htmlspecialchars($app['type_name']) ?></span></div></td>
              <td class="text-sm"><?= date('d M Y', strtotime($app['from_date'])) ?></td>
              <td class="text-sm"><?= date('d M Y', strtotime($app['to_date'])) ?></td>
              <td class="font-semibold"><?= $app['days'] ?></td>
              <td><span class="badge <?= $sm[$app['status']]??'badge-gray' ?>"><?= ucfirst($app['status']) ?></span></td>
              <td class="text-muted text-sm"><?= date('d M Y', strtotime($app['created_at'])) ?></td>
              <td>
                <?php if($app['status']==='pending'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel?')">
                  <input type="hidden" name="action" value="cancel_leave">
                  <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
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
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3>Reject Leave</h3><button class="modal-close" onclick="document.getElementById('rejectModal').classList.remove('open')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
    <form method="POST">
      <input type="hidden" name="action" value="reject_leave">
      <input type="hidden" name="app_id" id="reject_app_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason for Rejection</label>
          <textarea name="review_note" class="form-control" rows="3" placeholder="Optional note to employee…"></textarea>
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
function openReject(id) {
  document.getElementById('reject_app_id').value = id;
  document.getElementById('rejectModal').classList.add('open');
}
document.getElementById('rejectModal').addEventListener('click', e => { if(e.target===document.getElementById('rejectModal')) document.getElementById('rejectModal').classList.remove('open'); });

function calcDays() {
  const from = document.getElementById('applyFrom').value;
  const to   = document.getElementById('applyTo').value;
  const prev = document.getElementById('daysPreview');
  if (!from || !to || from > to) { prev.style.display='none'; return; }
  let days = 0, d = new Date(from), e = new Date(to);
  while (d <= e) { if (d.getDay() !== 0) days++; d.setDate(d.getDate()+1); }
  const sel = document.getElementById('applyType');
  const bal = parseFloat(sel.options[sel.selectedIndex]?.dataset.balance || 0);
  prev.style.display = 'block';
  prev.style.background = days > bal ? 'var(--red-bg)' : 'var(--brand-light)';
  prev.style.color = days > bal ? 'var(--red)' : 'var(--brand)';
  prev.textContent = `${days} working day(s)` + (bal > 0 ? ` · ${bal} available` : '');
}
function updateBalance() { calcDays(); }
function validateLeaveForm() {
  const type = document.getElementById('applyType').value;
  const from = document.getElementById('applyFrom').value;
  const to   = document.getElementById('applyTo').value;
  if (!type) { alert('Select a leave type.'); return false; }
  if (!from || !to) { alert('Select both dates.'); return false; }
  if (from > to) { alert('From must be before To.'); return false; }
  return true;
}
</script>
</body>
</html>
