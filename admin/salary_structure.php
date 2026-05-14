<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$userId = (int)($_GET['user_id'] ?? 0);
$isEdit = isset($_GET['edit']);
if (!$userId) { header("Location: payroll.php"); exit; }

// Get employee info
$emp = $db->prepare("
    SELECT e.*, u.name AS full_name, u.id AS uid, d.name AS dept_name
    FROM employees e
    JOIN users u ON e.email = u.email
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE u.id = ?
");
$emp->execute([$userId]);
$emp = $emp->fetch();
if (!$emp) { header("Location: payroll.php"); exit; }

// Get existing salary structure
$salary = $db->prepare("SELECT * FROM salary_structures WHERE user_id=?");
$salary->execute([$userId]);
$salary = $salary->fetch();

// Get gross from employee data (annual CTC if available)
$grossFromEmp = 0;
try {
    $gStmt = $db->prepare("SELECT gross_salary FROM employees WHERE id=?");
    $gStmt->execute([$emp['id']]);
    $grossFromEmp = (float)$gStmt->fetchColumn();
} catch (Exception $e) {}

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// POST: Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'save_salary') {
    $gross = (float)($_POST['gross_salary'] ?? 0);
    $monthly = $gross / 12;
    $basic = round($monthly / 2, 2);
    $hra = round($basic / 2, 2);

    $data = [
        'user_id' => $userId,
        'employee_id' => $emp['id'],
        'gross_salary' => $gross,
        'basic_salary' => $basic,
        'hra' => $hra,
        'special_allowance' => (float)($_POST['special_allowance'] ?? 0),
        'conveyance' => (float)($_POST['conveyance'] ?? 0),
        'education_allowance' => (float)($_POST['education_allowance'] ?? 0),
        'lta' => (float)($_POST['lta'] ?? 0),
        'mediclaim_insurance' => (float)($_POST['mediclaim_insurance'] ?? 0),
        'medical_reimbursement' => (float)($_POST['medical_reimbursement'] ?? 0),
        'mobile_internet' => (float)($_POST['mobile_internet'] ?? 0),
        'personal_allowance' => (float)($_POST['personal_allowance'] ?? 0),
        'bonus' => (float)($_POST['bonus'] ?? 0),
        'tax_regime' => in_array($_POST['tax_regime']??'',['old','new']) ? $_POST['tax_regime'] : 'new',
        'esi_rate' => (float)($_POST['esi_rate'] ?? 0.75),
        'pf_rate' => (float)($_POST['pf_rate'] ?? 12),
    ];

    // Professional tax calculation
    if ($gross <= 250000) $data['professional_tax'] = 0;
    elseif ($gross <= 500000) $data['professional_tax'] = round($gross * 0.05, 2);
    elseif ($gross <= 1000000) $data['professional_tax'] = round($gross * 0.20, 2);
    else $data['professional_tax'] = round($gross * 0.30, 2);

    // Custom deductions
    $customDed = [];
    if (!empty($_POST['custom_ded_name'])) {
        foreach ($_POST['custom_ded_name'] as $i => $name) {
            $name = trim($name);
            $amt = (float)($_POST['custom_ded_amount'][$i] ?? 0);
            if ($name && $amt > 0) $customDed[] = ['name' => $name, 'amount' => $amt];
        }
    }
    $data['custom_deductions'] = json_encode($customDed);

    if ($salary) {
        $sets = []; $vals = [];
        foreach ($data as $k => $v) { if ($k !== 'user_id' && $k !== 'employee_id') { $sets[] = "$k=?"; $vals[] = $v; } }
        $vals[] = $salary['id'];
        $db->prepare("UPDATE salary_structures SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    } else {
        $cols = implode(',', array_keys($data));
        $phs = implode(',', array_fill(0, count($data), '?'));
        $db->prepare("INSERT INTO salary_structures ($cols) VALUES ($phs)")->execute(array_values($data));
    }

    $_SESSION['flash_success'] = "Salary structure saved.";
    header("Location: salary_structure.php?user_id=$userId"); exit;
}

// Calc display values
$s = $salary ?: [];
$gross = (float)($s['gross_salary'] ?? $grossFromEmp);
$monthly = $gross / 12;
$basic = round($monthly / 2, 2);
$hra = round($basic / 2, 2);
$customDed = json_decode($s['custom_deductions'] ?? '[]', true) ?: [];

// Professional tax
if ($gross <= 250000) $profTax = 0;
elseif ($gross <= 500000) $profTax = round($gross * 0.05, 2);
elseif ($gross <= 1000000) $profTax = round($gross * 0.20, 2);
else $profTax = round($gross * 0.30, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Salary Structure – <?= htmlspecialchars($emp['full_name']) ?></title>
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
      <span class="page-title">Salary Structure</span>
      <span class="page-breadcrumb"><a href="payroll.php" style="color:var(--muted);text-decoration:none;">Payroll</a> / <?= htmlspecialchars($emp['full_name']) ?></span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?><div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <!-- Employee Info -->
    <div style="background:linear-gradient(135deg,var(--brand),var(--brand-mid));border-radius:12px;padding:18px 24px;color:#fff;margin-bottom:24px;display:flex;align-items:center;gap:16px;">
      <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;"><?= strtoupper(substr($emp['full_name'],0,1)) ?></div>
      <div>
        <div style="font-size:18px;font-weight:800;"><?= htmlspecialchars($emp['full_name']) ?></div>
        <div style="font-size:13px;opacity:.85;"><?= htmlspecialchars($emp['employee_id'] ?? '') ?> · <?= htmlspecialchars($emp['job_title'] ?? '') ?> · <?= htmlspecialchars($emp['dept_name'] ?? '') ?></div>
      </div>
      <?php if($salary && !$isEdit): ?>
      <a href="salary_structure.php?user_id=<?= $userId ?>&edit=1" class="btn" style="margin-left:auto;background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);">Edit</a>
      <?php endif; ?>
    </div>

    <?php if(!$isEdit && $salary): ?>
    <!-- VIEW MODE -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <!-- Earnings -->
      <div class="card">
        <div class="card-header"><h2>Earnings (Monthly)</h2></div>
        <div class="card-body">
          <table style="width:100%;font-size:13px;">
            <tr><td style="padding:8px 0;color:var(--muted);">Gross Salary (Annual)</td><td style="text-align:right;font-weight:700;color:var(--brand);">₹<?= number_format($s['gross_salary'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Basic Salary</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['basic_salary'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">HRA</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['hra'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Special Allowance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['special_allowance'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Conveyance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['conveyance'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Education Allowance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['education_allowance'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">LTA</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['lta'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Mediclaim Insurance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['mediclaim_insurance'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Medical Reimbursement</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['medical_reimbursement'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Mobile & Internet</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['mobile_internet'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Personal Allowance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['personal_allowance'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Bonus (Yearly - Dec)</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['bonus'],0) ?></td></tr>
          </table>
        </div>
      </div>
      <!-- Deductions -->
      <div class="card">
        <div class="card-header"><h2>Deductions</h2></div>
        <div class="card-body">
          <table style="width:100%;font-size:13px;">
            <tr><td style="padding:8px 0;color:var(--muted);">Professional Tax (Annual)</td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($s['professional_tax'],0) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Tax Regime</td><td style="text-align:right;font-weight:700;"><?= ucfirst($s['tax_regime']) ?></td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Employee ESI Rate</td><td style="text-align:right;font-weight:700;"><?= $s['esi_rate'] ?>%</td></tr>
            <tr><td style="padding:8px 0;color:var(--muted);">Employee PF Rate</td><td style="text-align:right;font-weight:700;"><?= $s['pf_rate'] ?>%</td></tr>
            <?php foreach($customDed as $cd): ?>
            <tr><td style="padding:8px 0;color:var(--muted);"><?= htmlspecialchars($cd['name']) ?></td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($cd['amount'],0) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
    </div>
    <div style="margin-top:20px;"><a href="payroll.php" class="btn btn-secondary">← Back to Payroll</a></div>

    <?php else: ?>
    <!-- EDIT/ADD MODE -->
    <form method="POST">
      <input type="hidden" name="action" value="save_salary">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <!-- Earnings -->
        <div class="card">
          <div class="card-header"><h2>Earnings</h2></div>
          <div class="card-body">
            <div class="form-group" style="margin-bottom:14px;">
              <label>Gross Salary (Annual CTC) <span class="req">*</span></label>
              <input type="number" name="gross_salary" id="grossInput" class="form-control" value="<?= $s['gross_salary'] ?? $grossFromEmp ?>" required min="0" step="1" oninput="calcAuto()">
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Auto-calculated:</div>
              <div style="font-size:13px;">Monthly: ₹<strong id="dispMonthly">0</strong> · Basic: ₹<strong id="dispBasic">0</strong> · HRA: ₹<strong id="dispHRA">0</strong></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Special Allowance</label>
              <input type="number" name="special_allowance" class="form-control" value="<?= $s['special_allowance'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Conveyance</label>
              <input type="number" name="conveyance" class="form-control" value="<?= $s['conveyance'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Education Allowance</label>
              <input type="number" name="education_allowance" class="form-control" value="<?= $s['education_allowance'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Leave Travel Allowance (LTA)</label>
              <input type="number" name="lta" class="form-control" value="<?= $s['lta'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Mediclaim Insurance</label>
              <input type="number" name="mediclaim_insurance" class="form-control" value="<?= $s['mediclaim_insurance'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Medical Reimbursement</label>
              <input type="number" name="medical_reimbursement" class="form-control" value="<?= $s['medical_reimbursement'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Mobile & Internet Allowance</label>
              <input type="number" name="mobile_internet" class="form-control" value="<?= $s['mobile_internet'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Personal Allowance</label>
              <input type="number" name="personal_allowance" class="form-control" value="<?= $s['personal_allowance'] ?? 0 ?>" min="0" step="1">
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label>Bonus (Yearly - added in Dec payslip)</label>
              <input type="number" name="bonus" class="form-control" value="<?= $s['bonus'] ?? 0 ?>" min="0" step="1">
            </div>
          </div>
        </div>

        <!-- Deductions -->
        <div class="card">
          <div class="card-header"><h2>Deductions</h2></div>
          <div class="card-body">
            <div style="background:var(--surface-2);border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Professional Tax (auto-calculated):</div>
              <div style="font-size:14px;font-weight:700;color:var(--red);">₹<span id="dispProfTax"><?= number_format($profTax,0) ?></span></div>
              <div style="font-size:11px;color:var(--muted);margin-top:4px;">≤2.5L: Nil | 2.5-5L: 5% | 5-10L: 20% | >10L: 30%</div>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
              <label>Tax Regime</label>
              <select name="tax_regime" class="form-control">
                <option value="new" <?= ($s['tax_regime']??'new')==='new'?'selected':'' ?>>New Regime</option>
                <option value="old" <?= ($s['tax_regime']??'')==='old'?'selected':'' ?>>Old Regime</option>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
              <label>Employee ESI Rate (%)</label>
              <input type="number" name="esi_rate" class="form-control" value="<?= $s['esi_rate'] ?? 0.75 ?>" min="0" max="100" step="0.01">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
              <label>Employee PF Rate (%)</label>
              <input type="number" name="pf_rate" class="form-control" value="<?= $s['pf_rate'] ?? 12 ?>" min="0" max="100" step="0.01">
            </div>

            <!-- Custom Deductions -->
            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <label style="font-weight:700;font-size:13px;">Custom Deductions</label>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addCustomDed()">+ Add</button>
              </div>
              <div id="customDedList">
                <?php foreach($customDed as $i => $cd): ?>
                <div class="form-grid" style="margin-bottom:8px;" id="ced-<?= $i ?>">
                  <input type="text" name="custom_ded_name[]" class="form-control" value="<?= htmlspecialchars($cd['name']) ?>" placeholder="Deduction name" style="font-size:12.5px;">
                  <div style="display:flex;gap:6px;align-items:center;">
                    <input type="number" name="custom_ded_amount[]" class="form-control" value="<?= $cd['amount'] ?>" placeholder="Amount" min="0" step="1" style="font-size:12.5px;">
                    <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;padding:4px 8px;" onclick="this.parentElement.parentElement.remove()">✕</button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-bottom:32px;">
        <a href="payroll.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Salary Structure</button>
      </div>
    </form>
    <?php endif; ?>

  </div>
</div>
</div>

<script>
function calcAuto() {
  const gross = parseFloat(document.getElementById('grossInput').value) || 0;
  const monthly = gross / 12;
  const basic = monthly / 2;
  const hra = basic / 2;
  document.getElementById('dispMonthly').textContent = Math.round(monthly).toLocaleString('en-IN');
  document.getElementById('dispBasic').textContent = Math.round(basic).toLocaleString('en-IN');
  document.getElementById('dispHRA').textContent = Math.round(hra).toLocaleString('en-IN');

  let pt = 0;
  if (gross <= 250000) pt = 0;
  else if (gross <= 500000) pt = gross * 0.05;
  else if (gross <= 1000000) pt = gross * 0.20;
  else pt = gross * 0.30;
  document.getElementById('dispProfTax').textContent = Math.round(pt).toLocaleString('en-IN');
}

let cedIdx = <?= count($customDed) ?>;
function addCustomDed() {
  const list = document.getElementById('customDedList');
  const div = document.createElement('div');
  div.className = 'form-grid';
  div.style.marginBottom = '8px';
  div.innerHTML = `<input type="text" name="custom_ded_name[]" class="form-control" placeholder="Deduction name" style="font-size:12.5px;">
    <div style="display:flex;gap:6px;align-items:center;">
      <input type="number" name="custom_ded_amount[]" class="form-control" placeholder="Amount" min="0" step="1" style="font-size:12.5px;">
      <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;padding:4px 8px;" onclick="this.parentElement.parentElement.remove()">✕</button>
    </div>`;
  list.appendChild(div);
}

// Init
calcAuto();
</script>
</body>
</html>
