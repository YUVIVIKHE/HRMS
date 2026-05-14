<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$userId = (int)($_GET['user_id'] ?? 0);
$isEdit = isset($_GET['edit']);
if (!$userId) { header("Location: payroll.php"); exit; }

$emp = $db->prepare("SELECT e.*, u.name AS full_name, u.id AS uid, d.name AS dept_name FROM employees e JOIN users u ON e.email=u.email LEFT JOIN departments d ON e.department_id=d.id WHERE u.id=?");
$emp->execute([$userId]); $emp = $emp->fetch();
if (!$emp) { header("Location: payroll.php"); exit; }

$salary = $db->prepare("SELECT * FROM salary_structures WHERE user_id=?");
$salary->execute([$userId]); $salary = $salary->fetch();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Income tax calculation function (New Regime)
function calcIncomeTax($gross) {
    $tax = 0; $t = (float)$gross;
    if ($t > 2400000) { $tax += ($t - 2400000) * 0.30; $t = 2400000; }
    if ($t > 2000000) { $tax += ($t - 2000000) * 0.25; $t = 2000000; }
    if ($t > 1600000) { $tax += ($t - 1600000) * 0.20; $t = 1600000; }
    if ($t > 1200000) { $tax += ($t - 1200000) * 0.15; $t = 1200000; }
    if ($t > 800000)  { $tax += ($t - 800000) * 0.10; $t = 800000; }
    if ($t > 400000)  { $tax += ($t - 400000) * 0.05; }
    return round($tax, 2);
}

// POST: Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'save_salary') {
    $gross = (float)($_POST['gross_salary'] ?? 0);
    $monthly = $gross / 12;
    $basic = round($monthly / 2, 2);
    $hra = round($basic / 2, 2);
    $incomeTax = calcIncomeTax($gross);

    $customDedPost = [];
    if (!empty($_POST['custom_ded_name'])) {
        foreach ($_POST['custom_ded_name'] as $i => $name) {
            $name = trim($name); $amt = (float)($_POST['custom_ded_amount'][$i] ?? 0);
            if ($name && $amt > 0) $customDedPost[] = ['name' => $name, 'amount' => $amt];
        }
    }

    $customAddPost = [];
    if (!empty($_POST['custom_add_name'])) {
        foreach ($_POST['custom_add_name'] as $i => $name) {
            $name = trim($name); $amt = (float)($_POST['custom_add_amount'][$i] ?? 0);
            if ($name && $amt > 0) $customAddPost[] = ['name' => $name, 'amount' => $amt];
        }
    }

    try {
        if ($salary) {
            $db->prepare("UPDATE salary_structures SET
                gross_salary=?, basic_salary=?, hra=?, special_allowance=?, conveyance=?,
                education_allowance=?, lta=?, mediclaim_insurance=?, medical_reimbursement=?,
                mobile_internet=?, personal_allowance=?, bonus=?, income_tax_annual=?,
                professional_tax=?, epf_employee_rate=?, eps_employer_rate=?, edli_employer_rate=?,
                epf_admin_rate=?, esi_employee_rate=?, esi_employer_rate=?, tax_regime=?, custom_deductions=?, custom_additions=?
                WHERE id=?")->execute([
                $gross, $basic, $hra,
                (float)($_POST['special_allowance'] ?? 0), (float)($_POST['conveyance'] ?? 0),
                (float)($_POST['education_allowance'] ?? 0), (float)($_POST['lta'] ?? 0),
                (float)($_POST['mediclaim_insurance'] ?? 0), (float)($_POST['medical_reimbursement'] ?? 0),
                (float)($_POST['mobile_internet'] ?? 0), (float)($_POST['personal_allowance'] ?? 0),
                (float)($_POST['bonus'] ?? 0), $incomeTax, 2500,
                (float)($_POST['epf_employee_rate'] ?? 3.67), (float)($_POST['eps_employer_rate'] ?? 8.33),
                (float)($_POST['edli_employer_rate'] ?? 0.50), (float)($_POST['epf_admin_rate'] ?? 0.50),
                (float)($_POST['esi_employee_rate'] ?? 0.75), (float)($_POST['esi_employer_rate'] ?? 3.25),
                in_array($_POST['tax_regime']??'',['old','new']) ? $_POST['tax_regime'] : 'new',
                json_encode($customDedPost), json_encode($customAddPost),
                $salary['id']
            ]);
        } else {
            $db->prepare("INSERT INTO salary_structures
                (user_id, employee_id, gross_salary, basic_salary, hra, special_allowance, conveyance,
                education_allowance, lta, mediclaim_insurance, medical_reimbursement,
                mobile_internet, personal_allowance, bonus, income_tax_annual,
                professional_tax, epf_employee_rate, eps_employer_rate, edli_employer_rate,
                epf_admin_rate, esi_employee_rate, esi_employer_rate, tax_regime, custom_deductions, custom_additions)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                $userId, $emp['id'], $gross, $basic, $hra,
                (float)($_POST['special_allowance'] ?? 0), (float)($_POST['conveyance'] ?? 0),
                (float)($_POST['education_allowance'] ?? 0), (float)($_POST['lta'] ?? 0),
                (float)($_POST['mediclaim_insurance'] ?? 0), (float)($_POST['medical_reimbursement'] ?? 0),
                (float)($_POST['mobile_internet'] ?? 0), (float)($_POST['personal_allowance'] ?? 0),
                (float)($_POST['bonus'] ?? 0), $incomeTax, 2500,
                (float)($_POST['epf_employee_rate'] ?? 3.67), (float)($_POST['eps_employer_rate'] ?? 8.33),
                (float)($_POST['edli_employer_rate'] ?? 0.50), (float)($_POST['epf_admin_rate'] ?? 0.50),
                (float)($_POST['esi_employee_rate'] ?? 0.75), (float)($_POST['esi_employer_rate'] ?? 3.25),
                in_array($_POST['tax_regime']??'',['old','new']) ? $_POST['tax_regime'] : 'new',
                json_encode($customDedPost), json_encode($customAddPost)
            ]);
        }
        $_SESSION['flash_success'] = "Salary structure saved.";
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
    }
    header("Location: salary_structure.php?user_id=$userId"); exit;
}

$s = $salary ?: [];
$gross = (float)($s['gross_salary'] ?? 0);
$monthly = $gross / 12;
$basic = round($monthly / 2, 2);
$hra = round($basic / 2, 2);
$customDed = json_decode($s['custom_deductions'] ?? '[]', true) ?: [];
$customAdd = json_decode($s['custom_additions'] ?? '[]', true) ?: [];
$incomeTaxAnnual = (float)($s['income_tax_annual'] ?? calcIncomeTax($gross));
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

    <!-- Employee Banner -->
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
    <?php
      $totalEarnings = $s['basic_salary'] + $s['hra'] + $s['special_allowance'] + $s['conveyance'] + $s['education_allowance'] + $s['lta'] + $s['mediclaim_insurance'] + $s['medical_reimbursement'] + $s['mobile_internet'] + $s['personal_allowance'];
      $customAddTotal = 0; foreach ($customAdd as $ca) $customAddTotal += (float)$ca['amount'];
      $totalEarnings += $customAddTotal;
      $monthlyIT = round($s['income_tax_annual'] / 12, 2);
      $monthlyPT = round(2500 / 12, 2);
      $monthlyEPF = round(($s['basic_salary'] * $s['epf_employee_rate']) / 100, 2);
      $monthlyESI = round(($s['basic_salary'] * $s['esi_employee_rate']) / 100, 2);
      $customTotal = 0; foreach ($customDed as $cd) $customTotal += (float)$cd['amount'];
      $totalDeductions = $monthlyIT + $monthlyPT + $monthlyEPF + $monthlyESI + $customTotal;
      // Employer contributions
      $empEPS = round(($s['basic_salary'] * $s['eps_employer_rate']) / 100, 2);
      $empEDLI = round(($s['basic_salary'] * $s['edli_employer_rate']) / 100, 2);
      $empAdmin = round(($s['basic_salary'] * $s['epf_admin_rate']) / 100, 2);
      $empESI = round(($s['basic_salary'] * $s['esi_employer_rate']) / 100, 2);
      $totalEmployerCost = $empEPS + $empEDLI + $empAdmin + $empESI;
      $netPayable = $totalEarnings - $totalDeductions - $totalEmployerCost;
    ?>
    <!-- Summary -->
    <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--brand);">₹<?= number_format($s['gross_salary']/12,0) ?></div><div class="stat-label">Monthly CTC</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--green-text);">₹<?= number_format($totalEarnings,0) ?></div><div class="stat-label">Total Earnings</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--red);">₹<?= number_format($totalDeductions,0) ?></div><div class="stat-label">Emp. Deductions</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--yellow);">₹<?= number_format($totalEmployerCost,0) ?></div><div class="stat-label">Employer Cost</div></div></div>
      <div class="stat-card" style="border-color:var(--brand);background:var(--brand-light);"><div class="stat-body"><div class="stat-value" style="color:var(--brand);font-size:20px;">₹<?= number_format($netPayable,0) ?></div><div class="stat-label" style="font-weight:700;">Net Payable</div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <div class="card">
        <div class="card-header"><h2>Earnings (Monthly)</h2></div>
        <div class="card-body"><table style="width:100%;font-size:13px;">
          <tr><td style="padding:7px 0;color:var(--muted);">Gross (Annual)</td><td style="text-align:right;font-weight:700;color:var(--brand);">₹<?= number_format($s['gross_salary'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Basic Salary</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['basic_salary'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">HRA</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['hra'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Special Allowance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['special_allowance'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Conveyance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['conveyance'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Education Allowance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['education_allowance'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">LTA</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['lta'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Mediclaim</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['mediclaim_insurance'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Medical Reimb.</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['medical_reimbursement'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Mobile & Internet</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['mobile_internet'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Personal Allowance</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['personal_allowance'],0) ?></td></tr>
          <tr><td style="padding:7px 0;color:var(--muted);">Bonus (Dec only)</td><td style="text-align:right;font-weight:700;">₹<?= number_format($s['bonus'],0) ?></td></tr>
          <?php foreach($customAdd as $ca): ?>
          <tr><td style="padding:7px 0;color:var(--muted);"><?= htmlspecialchars($ca['name']) ?></td><td style="text-align:right;font-weight:700;color:var(--green-text);">₹<?= number_format($ca['amount'],0) ?></td></tr>
          <?php endforeach; ?>
          <tr style="border-top:2px solid var(--border);"><td style="padding:10px 0;font-weight:700;">Total Earnings</td><td style="text-align:right;font-weight:800;color:var(--green-text);">₹<?= number_format($totalEarnings,0) ?></td></tr>
        </table></div>
      </div>
      <div>
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><h2>Employee Deductions (Monthly)</h2></div>
          <div class="card-body"><table style="width:100%;font-size:13px;">
            <tr><td style="padding:7px 0;color:var(--muted);">Income Tax (₹<?= number_format($s['income_tax_annual'],0) ?>/yr ÷ 12)</td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($monthlyIT,0) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--muted);">Professional Tax (₹2500/yr ÷ 12)</td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($monthlyPT,0) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--muted);">EPF Employee (<?= $s['epf_employee_rate'] ?>% of Basic)</td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($monthlyEPF,0) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--muted);">ESI Employee (<?= $s['esi_employee_rate'] ?>% of Basic)</td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($monthlyESI,0) ?></td></tr>
            <?php foreach($customDed as $cd): ?>
            <tr><td style="padding:7px 0;color:var(--muted);"><?= htmlspecialchars($cd['name']) ?></td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($cd['amount'],0) ?></td></tr>
            <?php endforeach; ?>
            <tr style="border-top:2px solid var(--border);"><td style="padding:10px 0;font-weight:700;">Total Deductions</td><td style="text-align:right;font-weight:800;color:var(--red);">₹<?= number_format($totalDeductions,0) ?></td></tr>
          </table></div>
        </div>
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><h2>Employer Contributions (Monthly)</h2></div>
          <div class="card-body"><table style="width:100%;font-size:13px;">
            <tr><td style="padding:7px 0;color:var(--muted);">EPS Employer (<?= $s['eps_employer_rate'] ?>%)</td><td style="text-align:right;font-weight:700;">₹<?= number_format($empEPS,0) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--muted);">EDLI Employer (<?= $s['edli_employer_rate'] ?>%)</td><td style="text-align:right;font-weight:700;">₹<?= number_format($empEDLI,0) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--muted);">EPF Admin (<?= $s['epf_admin_rate'] ?>%)</td><td style="text-align:right;font-weight:700;">₹<?= number_format($empAdmin,0) ?></td></tr>
            <tr><td style="padding:7px 0;color:var(--muted);">ESI Employer (<?= $s['esi_employer_rate'] ?>%)</td><td style="text-align:right;font-weight:700;">₹<?= number_format($empESI,0) ?></td></tr>
            <tr style="border-top:2px solid var(--border);"><td style="padding:10px 0;font-weight:700;">Total Employer Cost</td><td style="text-align:right;font-weight:800;color:var(--yellow);">₹<?= number_format($totalEmployerCost,0) ?></td></tr>
          </table></div>
        </div>
        <div class="card" style="background:var(--brand-light);border-color:var(--brand);">
          <div class="card-body" style="padding:20px;">
            <table style="width:100%;font-size:14px;">
              <tr><td style="color:var(--muted);">Total Earnings</td><td style="text-align:right;font-weight:700;color:var(--green-text);">₹<?= number_format($totalEarnings,0) ?></td></tr>
              <tr><td style="color:var(--muted);">− Employee Deductions</td><td style="text-align:right;font-weight:700;color:var(--red);">₹<?= number_format($totalDeductions,0) ?></td></tr>
              <tr><td style="color:var(--muted);">− Employer Contributions</td><td style="text-align:right;font-weight:700;color:var(--yellow);">₹<?= number_format($totalEmployerCost,0) ?></td></tr>
              <tr style="border-top:2px solid var(--brand);"><td style="padding-top:12px;font-weight:800;color:var(--brand);font-size:16px;">NET PAYABLE</td><td style="text-align:right;padding-top:12px;font-weight:800;color:var(--brand);font-size:20px;">₹<?= number_format($netPayable,0) ?></td></tr>
            </table>
          </div>
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
              <input type="number" name="gross_salary" id="grossInput" class="form-control" value="<?= $s['gross_salary'] ?? 0 ?>" required min="0" step="1" oninput="calcAuto()">
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Auto-calculated:</div>
              <div style="font-size:13px;">Monthly: ₹<strong id="dispMonthly">0</strong> · Basic: ₹<strong id="dispBasic">0</strong> · HRA: ₹<strong id="dispHRA">0</strong></div>
            </div>
            <div class="form-group" style="margin-bottom:10px;"><label>Special Allowance</label><input type="number" name="special_allowance" class="form-control" value="<?= $s['special_allowance'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>Conveyance</label><input type="number" name="conveyance" class="form-control" value="<?= $s['conveyance'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>Education Allowance</label><input type="number" name="education_allowance" class="form-control" value="<?= $s['education_allowance'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>LTA</label><input type="number" name="lta" class="form-control" value="<?= $s['lta'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>Mediclaim Insurance</label><input type="number" name="mediclaim_insurance" class="form-control" value="<?= $s['mediclaim_insurance'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>Medical Reimbursement</label><input type="number" name="medical_reimbursement" class="form-control" value="<?= $s['medical_reimbursement'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>Mobile & Internet</label><input type="number" name="mobile_internet" class="form-control" value="<?= $s['mobile_internet'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>Personal Allowance</label><input type="number" name="personal_allowance" class="form-control" value="<?= $s['personal_allowance'] ?? 0 ?>" min="0"></div>
            <div class="form-group" style="margin-bottom:10px;"><label>Bonus (Yearly - Dec)</label><input type="number" name="bonus" class="form-control" value="<?= $s['bonus'] ?? 0 ?>" min="0"></div>
            <!-- Custom Additions -->
            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <label style="font-weight:700;font-size:13px;">Other Additions (Monthly)</label>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addCAdd()">+ Add</button>
              </div>
              <div id="cAddList">
                <?php foreach($customAdd as $ca): ?>
                <div class="form-grid" style="margin-bottom:8px;grid-template-columns:1fr auto;">
                  <input type="text" name="custom_add_name[]" class="form-control" value="<?= htmlspecialchars($ca['name']) ?>" placeholder="Name" style="font-size:12.5px;">
                  <div style="display:flex;gap:6px;align-items:center;">
                    <input type="number" name="custom_add_amount[]" class="form-control" value="<?= $ca['amount'] ?>" placeholder="₹" min="0" style="font-size:12.5px;width:100px;">
                    <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;padding:4px 8px;" onclick="this.parentElement.parentElement.remove()">✕</button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <!-- Deductions -->
        <div class="card">
          <div class="card-header"><h2>Deductions & Contributions</h2></div>
          <div class="card-body">
            <div style="background:var(--surface-2);border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Income Tax (New Regime - auto):</div>
              <div style="font-size:14px;font-weight:700;color:var(--red);">₹<span id="dispIT"><?= number_format($incomeTaxAnnual,0) ?></span>/yr → ₹<span id="dispITm"><?= number_format(round($incomeTaxAnnual/12),0) ?></span>/mo</div>
              <div style="font-size:10.5px;color:var(--muted);margin-top:4px;">≤4L:Nil | 4-8L:5% | 8-12L:10% | 12-16L:15% | 16-20L:20% | 20-24L:25% | >24L:30%</div>
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:12px;margin-bottom:14px;">
              <div style="font-size:12px;color:var(--muted);">Professional Tax: <strong>₹2,500/year → ₹208/month</strong> (fixed)</div>
            </div>
            <div class="form-group" style="margin-bottom:10px;"><label>Tax Regime</label>
              <select name="tax_regime" class="form-control"><option value="new" <?= ($s['tax_regime']??'new')==='new'?'selected':'' ?>>New Regime</option><option value="old" <?= ($s['tax_regime']??'')==='old'?'selected':'' ?>>Old Regime</option></select>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
              <div style="font-size:13px;font-weight:700;margin-bottom:10px;">EPF / ESI Rates</div>
              <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:10px;">
                <div class="form-group"><label>EPF Employee (%)</label><input type="number" name="epf_employee_rate" class="form-control" value="<?= $s['epf_employee_rate'] ?? 3.67 ?>" step="0.01" min="0"></div>
                <div class="form-group"><label>EPS Employer (%)</label><input type="number" name="eps_employer_rate" class="form-control" value="<?= $s['eps_employer_rate'] ?? 8.33 ?>" step="0.01" min="0"></div>
                <div class="form-group"><label>EDLI Employer (%)</label><input type="number" name="edli_employer_rate" class="form-control" value="<?= $s['edli_employer_rate'] ?? 0.50 ?>" step="0.01" min="0"></div>
                <div class="form-group"><label>EPF Admin (%)</label><input type="number" name="epf_admin_rate" class="form-control" value="<?= $s['epf_admin_rate'] ?? 0.50 ?>" step="0.01" min="0"></div>
                <div class="form-group"><label>ESI Employee (%)</label><input type="number" name="esi_employee_rate" class="form-control" value="<?= $s['esi_employee_rate'] ?? 0.75 ?>" step="0.01" min="0"></div>
                <div class="form-group"><label>ESI Employer (%)</label><input type="number" name="esi_employer_rate" class="form-control" value="<?= $s['esi_employer_rate'] ?? 3.25 ?>" step="0.01" min="0"></div>
              </div>
            </div>
            <!-- Custom Deductions -->
            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <label style="font-weight:700;font-size:13px;">Custom Deductions</label>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addCDed()">+ Add</button>
              </div>
              <div id="cDedList">
                <?php foreach($customDed as $cd): ?>
                <div class="form-grid" style="margin-bottom:8px;grid-template-columns:1fr auto;">
                  <input type="text" name="custom_ded_name[]" class="form-control" value="<?= htmlspecialchars($cd['name']) ?>" placeholder="Name" style="font-size:12.5px;">
                  <div style="display:flex;gap:6px;align-items:center;">
                    <input type="number" name="custom_ded_amount[]" class="form-control" value="<?= $cd['amount'] ?>" placeholder="₹" min="0" style="font-size:12.5px;width:100px;">
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
  const monthly = gross / 12, basic = monthly / 2, hra = basic / 2;
  document.getElementById('dispMonthly').textContent = Math.round(monthly).toLocaleString('en-IN');
  document.getElementById('dispBasic').textContent = Math.round(basic).toLocaleString('en-IN');
  document.getElementById('dispHRA').textContent = Math.round(hra).toLocaleString('en-IN');
  // Income tax
  let tax=0, t=gross;
  if(t>2400000){tax+=(t-2400000)*0.30;t=2400000;}
  if(t>2000000){tax+=(t-2000000)*0.25;t=2000000;}
  if(t>1600000){tax+=(t-1600000)*0.20;t=1600000;}
  if(t>1200000){tax+=(t-1200000)*0.15;t=1200000;}
  if(t>800000){tax+=(t-800000)*0.10;t=800000;}
  if(t>400000){tax+=(t-400000)*0.05;}
  document.getElementById('dispIT').textContent=Math.round(tax).toLocaleString('en-IN');
  document.getElementById('dispITm').textContent=Math.round(tax/12).toLocaleString('en-IN');
}
function addCDed(){
  const list=document.getElementById('cDedList');
  list.innerHTML+=`<div class="form-grid" style="margin-bottom:8px;grid-template-columns:1fr auto;"><input type="text" name="custom_ded_name[]" class="form-control" placeholder="Name" style="font-size:12.5px;"><div style="display:flex;gap:6px;align-items:center;"><input type="number" name="custom_ded_amount[]" class="form-control" placeholder="₹" min="0" style="font-size:12.5px;width:100px;"><button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;padding:4px 8px;" onclick="this.parentElement.parentElement.remove()">✕</button></div></div>`;
}
function addCAdd(){
  const list=document.getElementById('cAddList');
  list.innerHTML+=`<div class="form-grid" style="margin-bottom:8px;grid-template-columns:1fr auto;"><input type="text" name="custom_add_name[]" class="form-control" placeholder="Name" style="font-size:12.5px;"><div style="display:flex;gap:6px;align-items:center;"><input type="number" name="custom_add_amount[]" class="form-control" placeholder="₹" min="0" style="font-size:12.5px;width:100px;"><button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;padding:4px 8px;" onclick="this.parentElement.parentElement.remove()">✕</button></div></div>`;
}
calcAuto();
</script>
</body>
</html>
