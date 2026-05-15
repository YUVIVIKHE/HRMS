<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST: Apply leave ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'apply_leave') {
    $typeId   = (int)$_POST['leave_type_id'];
    $from     = $_POST['from_date'] ?? '';
    $to       = $_POST['to_date']   ?? '';

    // ACL leave (type_id = 9999 in form, stored as 0)
    $isACL = ($typeId === 9999);
    if ($isACL) $typeId = 0;
    $reason   = trim($_POST['reason'] ?? '');
    $errors   = [];

    if (!$isACL && !$typeId)  $errors[] = 'Select a leave type.';
    if (!$from)    $errors[] = 'From date is required.';
    if (!$to)      $errors[] = 'To date is required.';
    if ($from && $to && $from > $to) $errors[] = 'From date must be before To date.';
    if ($from && $from < date('Y-m-d')) $errors[] = 'Cannot apply for past dates.';

    if (empty($errors)) {
        // Count working days (Mon–Sat)
        $days = 0;
        $d = new DateTime($from); $e = new DateTime($to);
        while ($d <= $e) { if ((int)$d->format('N') <= 6) $days += 1; $d->modify('+1 day'); }

        if ($days <= 0) $errors[] = 'No working days in selected range.';

        // Check balance
        if ($isACL) {
            // Recalculate ACL available
            $aclAvailCheck = $aclAvailable;
            if ($aclAvailCheck < $days) $errors[] = "Insufficient ACL balance. Available: $aclAvailCheck day(s), Requested: $days day(s).";
        } else {
            $bal = $db->prepare("SELECT balance FROM leave_balances WHERE user_id=? AND leave_type_id=?");
            $bal->execute([$uid, $typeId]); $bal = $bal->fetchColumn();
            if ($bal === false || $bal < $days) $errors[] = "Insufficient balance. Available: ".($bal ?: 0)." day(s), Requested: $days day(s).";
        }

        // Check overlap — any leave type, excluding rejected/cancelled
        $overlap = $db->prepare("SELECT id FROM leave_applications WHERE user_id=? AND status NOT IN ('rejected','cancelled') AND from_date<=? AND to_date>=?");
        $overlap->execute([$uid, $to, $from]);
        if ($overlap->fetch()) $errors[] = 'You already have an active leave application overlapping these dates.';
    }

    if (empty($errors)) {
        $days = 0;
        $d = new DateTime($from); $e = new DateTime($to);
        while ($d <= $e) { if ((int)$d->format('N') <= 6) $days += 1; $d->modify('+1 day'); }

        $db->prepare("INSERT INTO leave_applications (user_id,leave_type_id,from_date,to_date,days,reason) VALUES (?,?,?,?,?,?)")
           ->execute([$uid,$typeId,$from,$to,$days,$reason]);
        $_SESSION['flash_success'] = "Leave application submitted for $days working day(s).";
        header("Location: my_leaves.php"); exit;
    } else {
        $errorMsg = implode(' ', $errors);
    }
}

// ── POST: Cancel leave ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'cancel_leave') {
    $appId = (int)$_POST['app_id'];
    $app   = $db->prepare("SELECT * FROM leave_applications WHERE id=? AND user_id=?");
    $app->execute([$appId,$uid]); $app = $app->fetch();
    if ($app && $app['status'] === 'pending') {
        $db->prepare("UPDATE leave_applications SET status='cancelled' WHERE id=?")->execute([$appId]);
        $_SESSION['flash_success'] = "Leave application cancelled.";
    } else {
        $_SESSION['flash_error'] = "Cannot cancel this application.";
    }
    header("Location: my_leaves.php"); exit;
}

// ── Data ─────────────────────────────────────────────────────
$leaveTypes = $db->query("SELECT * FROM leave_types WHERE is_active=1 ORDER BY name")->fetchAll();

// Balances per type
$balances = [];
foreach ($leaveTypes as $lt) {
    $b = $db->prepare("SELECT balance, used FROM leave_balances WHERE user_id=? AND leave_type_id=?");
    $b->execute([$uid,$lt['id']]); $b = $b->fetch();
    $balances[$lt['id']] = ['balance' => (float)($b['balance']??0), 'used' => (float)($b['used']??0)];
}

// ACL Balance calculation
$aclTotalHrs = 0;
// OT on working days (>11 hrs)
try {
    $otLogs = $db->prepare("SELECT SUM(work_seconds - 32400) AS ot_sec FROM attendance_logs WHERE user_id=? AND work_seconds > 39600 AND DAYOFWEEK(log_date) NOT IN (1,7)");
    $otLogs->execute([$uid]); $aclTotalHrs += round((float)($otLogs->fetchColumn() ?: 0) / 3600, 2);
} catch (Exception $e) {}
// Approved weekend/holiday ACL requests
try {
    $aclApproved = $db->prepare("SELECT COALESCE(SUM(hours),0) FROM acl_requests WHERE user_id=? AND status='approved'");
    $aclApproved->execute([$uid]); $aclTotalHrs += (float)$aclApproved->fetchColumn();
} catch (Exception $e) {}
// ACL used (leaves taken as ACL type)
$aclUsedDays = 0;
try {
    $aclUsed = $db->prepare("SELECT COALESCE(SUM(days),0) FROM leave_applications WHERE user_id=? AND leave_type_id=0 AND status IN ('approved','pending')");
    $aclUsed->execute([$uid]); $aclUsedDays = (float)$aclUsed->fetchColumn();
} catch (Exception $e) {}
$aclEarnedDays = round($aclTotalHrs / 9, 2);
$aclAvailable = max(0, $aclEarnedDays - $aclUsedDays);

// Applications
$applications = $db->prepare("
    SELECT la.*, lt.name AS type_name, lt.color
    FROM leave_applications la
    JOIN leave_types lt ON la.leave_type_id = lt.id
    WHERE la.user_id = ?
    ORDER BY la.created_at DESC
");
$applications->execute([$uid]);
$applications = $applications->fetchAll();
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
      <span class="page-title">My Leaves</span>
      <span class="page-breadcrumb">Balance &amp; Applications</span>
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

    <!-- Leave Balance Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
      <!-- ACL Card -->
      <div class="card" style="padding:20px;border-color:#7c3aed;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
          <span style="width:10px;height:10px;border-radius:50%;background:#7c3aed;flex-shrink:0;"></span>
          <span style="font-size:13px;font-weight:700;color:var(--text);">ACL (Compensatory)</span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <div style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:#7c3aed;"><?= number_format($aclAvailable,1) ?></div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;">Available</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:var(--red);"><?= number_format($aclUsedDays,1) ?></div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;">Used</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:var(--green-text);"><?= number_format($aclEarnedDays,1) ?></div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;">Earned</div>
          </div>
        </div>
      </div>
      <?php foreach($leaveTypes as $lt):
        $b = $balances[$lt['id']] ?? ['balance'=>0,'used'=>0];
        $total = $b['balance'] + $b['used'];
        $pct   = $total > 0 ? min(100, round(($b['used']/$total)*100)) : 0;
      ?>
      <div class="card" style="padding:20px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($lt['color']) ?>;flex-shrink:0;"></span>
          <span style="font-size:13px;font-weight:700;color:var(--text);"><?= htmlspecialchars($lt['name']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
          <div style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:var(--text);"><?= number_format($b['balance'],1) ?></div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Available</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:var(--red);"><?= number_format($b['used'],1) ?></div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Used</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:var(--muted);"><?= number_format($total,1) ?></div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Total</div>
          </div>
        </div>
        <!-- Progress bar -->
        <div style="height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= htmlspecialchars($lt['color']) ?>;border-radius:3px;transition:width .3s;"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:right;"><?= $pct ?>% used</div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($leaveTypes)): ?>
        <div style="grid-column:1/-1;text-align:center;padding:32px;color:var(--muted);font-size:13.5px;">No leave types configured yet. Contact your HR administrator.</div>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:380px 1fr;gap:20px;align-items:start;">

      <!-- Apply Leave Form -->
      <div class="card">
        <div class="card-header"><div><h2>Apply for Leave</h2><p>Submit a new leave request</p></div></div>
        <div class="card-body">
          <?php if(empty($leaveTypes)): ?>
            <p style="color:var(--muted);font-size:13.5px;">No leave types available.</p>
          <?php else: ?>
          <form method="POST" onsubmit="return validateLeaveForm()">
            <input type="hidden" name="action" value="apply_leave">
            <div class="form-group" style="margin-bottom:14px;">
              <label>Leave Type <span class="req">*</span></label>
              <select name="leave_type_id" id="applyType" class="form-control" required onchange="updateBalance()">
                <option value="">Select type…</option>
                <option value="9999" data-bal="<?= $aclAvailable ?>" style="font-weight:700;color:#7c3aed;">ACL (<?= $aclAvailable ?> days available)</option>
                <?php foreach($leaveTypes as $lt): ?>
                  <option value="<?= $lt['id'] ?>" data-balance="<?= $balances[$lt['id']]['balance'] ?? 0 ?>">
                    <?= htmlspecialchars($lt['name']) ?> (<?= number_format($balances[$lt['id']]['balance']??0,1) ?> days available)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
              <div class="form-group">
                <label>From Date <span class="req">*</span></label>
                <input type="date" name="from_date" id="applyFrom" class="form-control" min="<?= date('Y-m-d') ?>" required onchange="calcDays()">
              </div>
              <div class="form-group">
                <label>To Date <span class="req">*</span></label>
                <input type="date" name="to_date" id="applyTo" class="form-control" min="<?= date('Y-m-d') ?>" required onchange="calcDays()">
              </div>
            </div>
            <div id="daysPreview" style="display:none;background:var(--brand-light);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:13px;font-weight:600;color:var(--brand);"></div>
            <div class="form-group" style="margin-bottom:20px;">
              <label>Reason</label>
              <textarea name="reason" class="form-control" rows="3" placeholder="Optional reason for leave…" style="resize:vertical;"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Submit Application
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Applications List -->
      <div class="table-wrap">
        <div class="table-toolbar"><h2>My Applications <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($applications) ?>)</span></h2></div>
        <table>
          <thead>
            <tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Applied</th><th></th></tr>
          </thead>
          <tbody>
            <?php if(empty($applications)): ?>
              <tr class="empty-row"><td colspan="7">No leave applications yet.</td></tr>
            <?php else: foreach($applications as $app):
              $sm = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red','cancelled'=>'badge-gray'];
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($app['color']) ?>;flex-shrink:0;"></span>
                  <span class="font-semibold text-sm"><?= htmlspecialchars($app['type_name']) ?></span>
                </div>
              </td>
              <td class="text-sm"><?= date('d M Y', strtotime($app['from_date'])) ?></td>
              <td class="text-sm"><?= date('d M Y', strtotime($app['to_date'])) ?></td>
              <td class="font-semibold"><?= $app['days'] ?></td>
              <td><span class="badge <?= $sm[$app['status']]??'badge-gray' ?>"><?= ucfirst($app['status']) ?></span></td>
              <td class="text-muted text-sm"><?= date('d M Y', strtotime($app['created_at'])) ?></td>
              <td>
                <?php if($app['status'] === 'pending'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this leave application?')">
                  <input type="hidden" name="action" value="cancel_leave">
                  <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Cancel</button>
                </form>
                <?php elseif($app['review_note']): ?>
                  <span style="font-size:12px;color:var(--muted);" title="<?= htmlspecialchars($app['review_note']) ?>">Note ℹ</span>
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

<script>
function calcDays() {
  const from = document.getElementById('applyFrom').value;
  const to   = document.getElementById('applyTo').value;
  const prev = document.getElementById('daysPreview');
  if (!from || !to || from > to) { prev.style.display='none'; return; }
  let days = 0, d = new Date(from), e = new Date(to);
  while (d <= e) { if (d.getDay() !== 0) days++; d.setDate(d.getDate()+1); } // Sun=0 excluded
  const sel = document.getElementById('applyType');
  const bal = sel.options[sel.selectedIndex]?.dataset.balance || 0;
  prev.style.display = 'block';
  prev.style.background = days > bal ? 'var(--red-bg)' : 'var(--brand-light)';
  prev.style.color = days > bal ? 'var(--red)' : 'var(--brand)';
  prev.textContent = `${days} working day(s) selected` + (bal > 0 ? ` · ${bal} available` : '');
}

function updateBalance() { calcDays(); }

function validateLeaveForm() {
  const type = document.getElementById('applyType').value;
  const from = document.getElementById('applyFrom').value;
  const to   = document.getElementById('applyTo').value;
  if (!type) { alert('Please select a leave type.'); return false; }
  if (!from || !to) { alert('Please select both dates.'); return false; }
  if (from > to) { alert('From date must be before To date.'); return false; }
  return true;
}
</script>
</body>
</html>
