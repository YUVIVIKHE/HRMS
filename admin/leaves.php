<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add leave type
    if ($action === 'add_leave_type') {
        $name     = trim($_POST['name'] ?? '');
        $days     = max(0.5, (float)($_POST['days_per_credit'] ?? 1));
        $cycle    = in_array($_POST['credit_cycle']??'', ['monthly','yearly','manual']) ? $_POST['credit_cycle'] : 'monthly';
        $day      = max(1, min(28, (int)($_POST['credit_day'] ?? 1)));
        $carry    = max(0, (float)($_POST['max_carry_fwd'] ?? 0));
        $color    = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color']??'') ? $_POST['color'] : '#4f46e5';
        if ($name) {
            $db->prepare("INSERT INTO leave_types (name,days_per_credit,credit_cycle,credit_day,max_carry_fwd,color) VALUES (?,?,?,?,?,?)")
               ->execute([$name,$days,$cycle,$day,$carry,$color]);
            $_SESSION['flash_success'] = "Leave type '$name' created.";
        } else { $_SESSION['flash_error'] = "Name is required."; }
        header("Location: leaves.php"); exit;
    }

    // Edit leave type
    if ($action === 'edit_leave_type') {
        $id    = (int)$_POST['type_id'];
        $name  = trim($_POST['name'] ?? '');
        $days  = max(0.5, (float)($_POST['days_per_credit'] ?? 1));
        $cycle = in_array($_POST['credit_cycle']??'', ['monthly','yearly','manual']) ? $_POST['credit_cycle'] : 'monthly';
        $day   = max(1, min(28, (int)($_POST['credit_day'] ?? 1)));
        $carry = max(0, (float)($_POST['max_carry_fwd'] ?? 0));
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color']??'') ? $_POST['color'] : '#4f46e5';
        if ($name && $id) {
            $db->prepare("UPDATE leave_types SET name=?,days_per_credit=?,credit_cycle=?,credit_day=?,max_carry_fwd=?,color=? WHERE id=?")
               ->execute([$name,$days,$cycle,$day,$carry,$color,$id]);
            $_SESSION['flash_success'] = "Leave type updated.";
        }
        header("Location: leaves.php"); exit;
    }

    // Toggle active
    if ($action === 'toggle_leave_type') {
        $id = (int)$_POST['type_id'];
        $db->prepare("UPDATE leave_types SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        header("Location: leaves.php"); exit;
    }

    // Auto-credit: credit all active employees for a leave type
    if ($action === 'auto_credit') {
        $typeId = (int)$_POST['type_id'];
        $type   = $db->prepare("SELECT * FROM leave_types WHERE id=?");
        $type->execute([$typeId]); $type = $type->fetch();
        if ($type) {
            $users = $db->query("SELECT u.id FROM users u INNER JOIN employees e ON e.email = u.email WHERE u.role IN ('employee','manager') AND u.status='active'")->fetchAll();
            $count = 0;
            foreach ($users as $u) {
                // Upsert balance
                $db->prepare("INSERT INTO leave_balances (user_id,leave_type_id,balance,used) VALUES (?,?,?,0)
                    ON DUPLICATE KEY UPDATE balance = balance + ?")
                   ->execute([$u['id'],$typeId,$type['days_per_credit'],$type['days_per_credit']]);
                $db->prepare("INSERT INTO leave_credit_log (user_id,leave_type_id,days,reason,credited_by) VALUES (?,?,?,?,?)")
                   ->execute([$u['id'],$typeId,$type['days_per_credit'],'Auto credit: '.$type['name'],$_SESSION['user_id']]);
                $count++;
            }
            $_SESSION['flash_success'] = "Credited {$type['days_per_credit']} day(s) of '{$type['name']}' to $count employees.";
        }
        header("Location: leaves.php"); exit;
    }

    // Manual credit to selected users
    if ($action === 'manual_credit') {
        $typeId  = (int)$_POST['type_id'];
        $days    = max(0.5, (float)($_POST['days'] ?? 1));
        $reason  = trim($_POST['reason'] ?? 'Manual credit');
        $userIds = array_map('intval', $_POST['user_ids'] ?? []);
        if ($typeId && !empty($userIds)) {
            foreach ($userIds as $uid) {
                $db->prepare("INSERT INTO leave_balances (user_id,leave_type_id,balance,used) VALUES (?,?,?,0)
                    ON DUPLICATE KEY UPDATE balance = balance + ?")
                   ->execute([$uid,$typeId,$days,$days]);
                $db->prepare("INSERT INTO leave_credit_log (user_id,leave_type_id,days,reason,credited_by) VALUES (?,?,?,?,?)")
                   ->execute([$uid,$typeId,$days,$reason,$_SESSION['user_id']]);
            }
            $_SESSION['flash_success'] = "Credited $days day(s) to ".count($userIds)." user(s).";
        } else { $_SESSION['flash_error'] = "Select at least one employee and a leave type."; }
        header("Location: leaves.php"); exit;
    }
}

// ── Data ─────────────────────────────────────────────────────
$leaveTypes = $db->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();
$users      = $db->query("SELECT u.id, u.name, u.role FROM users u INNER JOIN employees e ON e.email = u.email WHERE u.role IN ('employee','manager') AND u.status='active' ORDER BY u.name")->fetchAll();

// Balance summary per type
$balSummary = [];
foreach ($leaveTypes as $lt) {
    $r = $db->prepare("SELECT SUM(balance) as total_bal, SUM(used) as total_used, COUNT(*) as cnt FROM leave_balances WHERE leave_type_id=?");
    $r->execute([$lt['id']]); $r = $r->fetch();
    $balSummary[$lt['id']] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leave Management – HRMS Portal</title>
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
      <span class="page-title">Leave Management</span>
      <span class="page-breadcrumb">Types, Credits &amp; Balances</span>
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

    <div class="page-header">
      <div class="page-header-text">
        <h1>Leave Management</h1>
        <p>Create leave types, configure auto-credit schedules, and manually credit employees.</p>
      </div>
      <div class="page-header-actions">
        <a href="export_leaves.php" class="btn btn-success">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export Excel
        </a>
        <button class="btn btn-primary" onclick="openModal('addTypeModal')">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Leave Type
        </button>
      </div>
    </div>

    <!-- Leave Types -->
    <div class="table-wrap" style="margin-bottom:24px;">
      <div class="table-toolbar"><h2>Leave Types</h2></div>
      <table>
        <thead>
          <tr>
            <th>Type</th>
            <th>Credit</th>
            <th>Cycle</th>
            <th>Carry Fwd</th>
            <th>Total Balance</th>
            <th>Total Used</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($leaveTypes)): ?>
            <tr class="empty-row"><td colspan="8">No leave types yet.</td></tr>
          <?php else: foreach($leaveTypes as $lt):
            $bs = $balSummary[$lt['id']] ?? ['total_bal'=>0,'total_used'=>0,'cnt'=>0];
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($lt['color']) ?>;flex-shrink:0;display:inline-block;"></span>
                <span class="font-semibold"><?= htmlspecialchars($lt['name']) ?></span>
              </div>
            </td>
            <td class="text-sm"><?= $lt['days_per_credit'] ?> day(s)</td>
            <td>
              <span class="badge <?= $lt['credit_cycle']==='manual'?'badge-yellow':($lt['credit_cycle']==='yearly'?'badge-blue':'badge-green') ?>">
                <?= ucfirst($lt['credit_cycle']) ?>
              </span>
            </td>
            <td class="text-sm text-muted"><?= $lt['max_carry_fwd'] > 0 ? $lt['max_carry_fwd'].' days' : 'None' ?></td>
            <td class="font-semibold"><?= number_format((float)($bs['total_bal']??0),1) ?> days</td>
            <td class="text-muted text-sm"><?= number_format((float)($bs['total_used']??0),1) ?> days</td>
            <td><span class="badge <?= $lt['is_active']?'badge-green':'badge-red' ?>"><?= $lt['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button class="btn btn-ghost btn-sm" onclick="openEditType(<?= htmlspecialchars(json_encode($lt),ENT_QUOTES) ?>)">Edit</button>
                <?php if($lt['credit_cycle'] !== 'manual'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Credit <?= $lt['days_per_credit'] ?> day(s) of <?= addslashes($lt['name']) ?> to ALL active employees now?')">
                  <input type="hidden" name="action" value="auto_credit">
                  <input type="hidden" name="type_id" value="<?= $lt['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--green-bg);color:var(--green-text);border:1px solid #a7f3d0;">Credit All</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_leave_type">
                  <input type="hidden" name="type_id" value="<?= $lt['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= $lt['is_active']?'Disable':'Enable' ?></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Manual Credit -->
    <div class="card">
      <div class="card-header">
        <div>
          <h2>Manual Credit</h2>
          <p>Select employees, choose a leave type, enter days, and credit manually.</p>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" id="manualCreditForm" onsubmit="return validateManualCredit()">
          <input type="hidden" name="action" value="manual_credit">
          <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end;">
            <div class="form-group" style="margin:0;min-width:200px;">
              <label>Leave Type <span class="req">*</span></label>
              <select name="type_id" id="mc_type" class="form-control" required>
                <option value="">Select type…</option>
                <?php foreach($leaveTypes as $lt): if(!$lt['is_active']) continue; ?>
                  <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0;min-width:120px;">
              <label>Days <span class="req">*</span></label>
              <input type="number" name="days" id="mc_days" class="form-control" min="0.5" step="0.5" value="1" required>
            </div>
            <div class="form-group" style="margin:0;min-width:220px;">
              <label>Reason</label>
              <input type="text" name="reason" class="form-control" placeholder="e.g. Annual credit Q1" value="Manual credit">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:1px;">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Credit Selected
            </button>
          </div>

          <!-- Employee list with checkboxes -->
          <div class="table-wrap" style="box-shadow:none;max-height:380px;overflow-y:auto;">
            <div class="table-toolbar" style="padding:10px 16px;position:sticky;top:0;z-index:2;background:var(--surface);">
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="mcSelAll" style="width:15px;height:15px;accent-color:var(--brand);cursor:pointer;" onchange="toggleMcAll(this)">
                <span style="font-size:13px;font-weight:600;">Select All</span>
                <span id="mcSelCount" style="font-size:12px;color:var(--muted);margin-left:4px;"></span>
              </div>
              <div class="search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search…" oninput="filterMcUsers(this.value)">
              </div>
            </div>
            <table>
              <thead style="position:sticky;top:44px;z-index:1;">
                <tr>
                  <th style="width:40px;"></th>
                  <th>Employee</th>
                  <th>Role</th>
                  <?php foreach($leaveTypes as $lt): if(!$lt['is_active']) continue; ?>
                    <th style="font-size:11px;"><?= htmlspecialchars($lt['name']) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach($users as $u):
                  // Get current balances
                  $balRow = $db->prepare("SELECT lt.id, lt.name, lb.balance, lb.used FROM leave_types lt LEFT JOIN leave_balances lb ON lb.leave_type_id=lt.id AND lb.user_id=? WHERE lt.is_active=1 ORDER BY lt.name");
                  $balRow->execute([$u['id']]); $balRow = $balRow->fetchAll();
                ?>
                <tr class="mc-row" data-name="<?= htmlspecialchars(strtolower($u['name'])) ?>">
                  <td style="padding-left:16px;">
                    <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="mc-chk"
                      style="width:15px;height:15px;accent-color:var(--brand);cursor:pointer;" onchange="updateMcCount()">
                  </td>
                  <td>
                    <div class="td-user">
                      <div class="td-avatar"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                      <div class="td-name"><?= htmlspecialchars($u['name']) ?></div>
                    </div>
                  </td>
                  <td><span class="badge <?= $u['role']==='manager'?'badge-brand':'badge-gray' ?>"><?= ucfirst($u['role']) ?></span></td>
                  <?php foreach($balRow as $b): ?>
                    <td class="text-sm text-center">
                      <span style="color:var(--green-text);font-weight:600;"><?= number_format((float)($b['balance']??0),1) ?></span>
                      <span style="color:var(--muted);font-size:11px;"> / <?= number_format((float)($b['used']??0),1) ?> used</span>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>
</div>

<!-- Add Leave Type Modal -->
<div class="modal-overlay" id="addTypeModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Add Leave Type</h3>
      <button class="modal-close" onclick="closeModal('addTypeModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST" onsubmit="return validateTypeForm(this)">
      <input type="hidden" name="action" value="add_leave_type">
      <div class="modal-body">
        <?php include __DIR__ . '/leave_type_fields.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addTypeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Leave Type</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Leave Type Modal -->
<div class="modal-overlay" id="editTypeModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Leave Type</h3>
      <button class="modal-close" onclick="closeModal('editTypeModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST" onsubmit="return validateTypeForm(this)">
      <input type="hidden" name="action" value="edit_leave_type">
      <input type="hidden" name="type_id" id="edit_type_id">
      <div class="modal-body" id="editTypeBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editTypeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));

function openEditType(lt) {
  document.getElementById('edit_type_id').value = lt.id;
  document.getElementById('editTypeBody').innerHTML = buildTypeFields(lt);
  toggleCreditDay(document.getElementById('editTypeBody').querySelector('[name="credit_cycle"]'));
  openModal('editTypeModal');
}

function buildTypeFields(lt) {
  return `
    <div class="form-group" style="margin-bottom:14px;">
      <label>Leave Type Name <span class="req">*</span></label>
      <input type="text" name="name" class="form-control" value="${escHtml(lt.name)}" required>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
      <div class="form-group">
        <label>Days per Credit <span class="req">*</span></label>
        <input type="number" name="days_per_credit" class="form-control" min="0.5" step="0.5" value="${lt.days_per_credit}" required>
      </div>
      <div class="form-group">
        <label>Color</label>
        <input type="color" name="color" class="form-control" value="${lt.color}" style="height:40px;padding:4px;">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
      <div class="form-group">
        <label>Credit Cycle</label>
        <select name="credit_cycle" class="form-control" onchange="toggleCreditDay(this)">
          <option value="monthly" ${lt.credit_cycle==='monthly'?'selected':''}>Monthly</option>
          <option value="yearly"  ${lt.credit_cycle==='yearly' ?'selected':''}>Yearly</option>
          <option value="manual"  ${lt.credit_cycle==='manual' ?'selected':''}>Manual Only</option>
        </select>
      </div>
      <div class="form-group" id="creditDayWrap_edit" style="${lt.credit_cycle==='manual'?'display:none':''}">
        <label>Credit on Day</label>
        <input type="number" name="credit_day" class="form-control" min="1" max="28" value="${lt.credit_day}">
        <span style="font-size:11px;color:var(--muted-light);">Day of month/year</span>
      </div>
    </div>
    <div class="form-group">
      <label>Max Carry Forward (days, 0 = none)</label>
      <input type="number" name="max_carry_fwd" class="form-control" min="0" step="0.5" value="${lt.max_carry_fwd}">
    </div>`;
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function toggleCreditDay(sel) {
  const wrap = sel.closest('.modal-body, form').querySelector('[id^="creditDayWrap"]');
  if (wrap) wrap.style.display = sel.value === 'manual' ? 'none' : '';
}

function validateTypeForm(form) {
  const name = form.querySelector('[name="name"]').value.trim();
  const days = parseFloat(form.querySelector('[name="days_per_credit"]').value);
  if (!name) { alert('Leave type name is required.'); return false; }
  if (isNaN(days) || days < 0.5) { alert('Days per credit must be at least 0.5.'); return false; }
  return true;
}

function validateManualCredit() {
  const type = document.getElementById('mc_type').value;
  const days = parseFloat(document.getElementById('mc_days').value);
  const checked = document.querySelectorAll('.mc-chk:checked').length;
  if (!type) { alert('Please select a leave type.'); return false; }
  if (isNaN(days) || days < 0.5) { alert('Days must be at least 0.5.'); return false; }
  if (checked === 0) { alert('Please select at least one employee.'); return false; }
  return confirm(`Credit ${days} day(s) to ${checked} employee(s)?`);
}

function toggleMcAll(master) {
  document.querySelectorAll('.mc-chk').forEach(cb => { if(cb.closest('tr').style.display!=='none') cb.checked = master.checked; });
  updateMcCount();
}
function updateMcCount() {
  const n = document.querySelectorAll('.mc-chk:checked').length;
  document.getElementById('mcSelCount').textContent = n > 0 ? `(${n} selected)` : '';
}
function filterMcUsers(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.mc-row').forEach(r => { r.style.display = !q || r.dataset.name.includes(q) ? '' : 'none'; });
}

// Init add modal credit day toggle
document.querySelector('#addTypeModal [name="credit_cycle"]')?.addEventListener('change', function(){ toggleCreditDay(this); });
</script>
</body>
</html>
