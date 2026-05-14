<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$selMonth = (int)($_GET['month'] ?? date('n'));
$selYear  = (int)($_GET['year'] ?? date('Y'));

// Generate payslips for selected month
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'generate') {
    $gMonth = (int)$_POST['gen_month'];
    $gYear  = (int)$_POST['gen_year'];

    $salaries = $db->query("SELECT ss.*, u.id AS uid FROM salary_structures ss JOIN users u ON ss.user_id = u.id")->fetchAll();

    $count = 0;
    $stmt = $db->prepare("INSERT IGNORE INTO payslips (user_id, month, year, gross_salary, basic_salary, hra, special_allowance, conveyance, education_allowance, lta, mediclaim_insurance, medical_reimbursement, mobile_internet, personal_allowance, bonus, total_earnings, income_tax, professional_tax, epf_employee, esi_employee, eps_employer, edli_employer, epf_admin, esi_employer, custom_deductions, custom_additions, total_deductions, total_employer_cost, net_payable, days_payable, generated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    foreach ($salaries as $s) {
        $basic = (float)$s['basic_salary'];
        $totalEarnings = $basic + (float)$s['hra'] + (float)$s['special_allowance'] + (float)$s['conveyance'] + (float)$s['education_allowance'] + (float)$s['lta'] + (float)$s['mediclaim_insurance'] + (float)$s['medical_reimbursement'] + (float)$s['mobile_internet'] + (float)$s['personal_allowance'];

        // Custom additions
        $customAdds = json_decode($s['custom_additions'] ?? '[]', true) ?: [];
        $customAddTotal = 0;
        foreach ($customAdds as $ca) $customAddTotal += (float)$ca['amount'];
        $totalEarnings += $customAddTotal;

        $bonus = ($gMonth === 12) ? (float)$s['bonus'] : 0;
        $totalEarnings += $bonus;

        $monthlyIT = round((float)$s['income_tax_annual'] / 12, 2);
        $monthlyPT = round(2500 / 12, 2);
        $epfEmp = round(($basic * (float)$s['epf_employee_rate']) / 100, 2);
        $esiEmp = round(($basic * (float)$s['esi_employee_rate']) / 100, 2);

        $epsEmployer = round(($basic * (float)$s['eps_employer_rate']) / 100, 2);
        $edliEmployer = round(($basic * (float)$s['edli_employer_rate']) / 100, 2);
        $epfAdmin = round(($basic * (float)$s['epf_admin_rate']) / 100, 2);
        $esiEmployer = round(($basic * (float)$s['esi_employer_rate']) / 100, 2);

        $customDeds = json_decode($s['custom_deductions'] ?? '[]', true) ?: [];
        $customTotal = 0;
        foreach ($customDeds as $cd) $customTotal += (float)$cd['amount'];

        $totalDeductions = $monthlyIT + $monthlyPT + $epfEmp + $esiEmp + $customTotal;
        $totalEmployerCost = $epsEmployer + $edliEmployer + $epfAdmin + $esiEmployer;
        $netPayable = $totalEarnings - $totalDeductions - $totalEmployerCost;

        // Days payable (working days in month excluding Sundays)
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $gMonth, $gYear);
        $workDays = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = date('N', mktime(0,0,0,$gMonth,$d,$gYear));
            if ($dow < 7) $workDays++; // Mon-Sat
        }

        try {
            $stmt->execute([
                $s['user_id'], $gMonth, $gYear,
                (float)$s['gross_salary'], $basic, (float)$s['hra'],
                (float)$s['special_allowance'], (float)$s['conveyance'], (float)$s['education_allowance'],
                (float)$s['lta'], (float)$s['mediclaim_insurance'], (float)$s['medical_reimbursement'],
                (float)$s['mobile_internet'], (float)$s['personal_allowance'], $bonus,
                $totalEarnings, $monthlyIT, $monthlyPT, $epfEmp, $esiEmp,
                $epsEmployer, $edliEmployer, $epfAdmin, $esiEmployer,
                json_encode($customDeds), json_encode($customAdds),
                $totalDeductions, $totalEmployerCost, $netPayable, $workDays,
                $_SESSION['user_id']
            ]);
            $count++;
        } catch (Exception $e) {}
    }

    $_SESSION['flash_success'] = "$count payslip(s) generated for " . date('M Y', mktime(0,0,0,$gMonth,1,$gYear)) . ".";
    header("Location: payslips.php?month=$gMonth&year=$gYear"); exit;
}

// Fetch payslips for selected month
$payslips = $db->prepare("
    SELECT ps.*, u.name AS emp_name, e.employee_id AS emp_code, e.job_title, d.name AS dept_name
    FROM payslips ps
    JOIN users u ON ps.user_id = u.id
    JOIN employees e ON e.email = u.email
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE ps.month = ? AND ps.year = ?
    ORDER BY u.name ASC
");
$payslips->execute([$selMonth, $selYear]);
$payslips = $payslips->fetchAll();

$totalNet = array_sum(array_column($payslips, 'net_payable'));
$totalEarn = array_sum(array_column($payslips, 'total_earnings'));
$totalDed = array_sum(array_column($payslips, 'total_deductions'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payslips – HRMS Portal</title>
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
      <span class="page-title">Payslips</span>
      <span class="page-breadcrumb"><?= date('F Y', mktime(0,0,0,$selMonth,1,$selYear)) ?></span>
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

    <!-- Controls -->
    <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
      <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <select name="month" class="form-control" style="font-size:13px;padding:9px 12px;width:auto;">
          <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $m==$selMonth?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>
        <select name="year" class="form-control" style="font-size:13px;padding:9px 12px;width:auto;">
          <?php for($y=(int)date('Y')-2;$y<=(int)date('Y')+1;$y++): ?>
            <option value="<?= $y ?>" <?= $y==$selYear?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">View</button>
      </form>

      <form method="POST" style="margin-left:auto;display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="gen_month" value="<?= $selMonth ?>">
        <input type="hidden" name="gen_year" value="<?= $selYear ?>">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Generate payslips for <?= date('M Y', mktime(0,0,0,$selMonth,1,$selYear)) ?>? This will create payslips for all employees with salary structure.')">
          Generate Payslips — <?= date('M Y', mktime(0,0,0,$selMonth,1,$selYear)) ?>
        </button>
      </form>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-body"><div class="stat-value"><?= count($payslips) ?></div><div class="stat-label">Payslips</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-body"><div class="stat-value" style="color:var(--green-text);">₹<?= number_format($totalEarn,0) ?></div><div class="stat-label">Total Earnings</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-body"><div class="stat-value" style="color:var(--red);">₹<?= number_format($totalDed,0) ?></div><div class="stat-label">Total Deductions</div></div>
      </div>
      <div class="stat-card" style="border-color:var(--brand);background:var(--brand-light);">
        <div class="stat-body"><div class="stat-value" style="color:var(--brand);">₹<?= number_format($totalNet,0) ?></div><div class="stat-label">Net Payable</div></div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Payslips — <?= date('F Y', mktime(0,0,0,$selMonth,1,$selYear)) ?> <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($payslips) ?>)</span></h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th style="text-align:center;">Earnings</th>
            <th style="text-align:center;">Deductions</th>
            <th style="text-align:center;">Net Payable</th>
            <th style="width:70px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($payslips)): ?>
            <tr class="empty-row"><td colspan="6">No payslips for this month. Click "Generate Payslips" to create them.</td></tr>
          <?php else: foreach($payslips as $ps): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($ps['emp_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($ps['emp_name']) ?></div>
                  <div class="td-sub"><?= htmlspecialchars($ps['emp_code'] ?: '') ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm text-muted"><?= htmlspecialchars($ps['dept_name'] ?: '—') ?></td>
            <td style="text-align:center;font-weight:700;color:var(--green-text);">₹<?= number_format($ps['total_earnings'],0) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--red);">₹<?= number_format($ps['total_deductions'],0) ?></td>
            <td style="text-align:center;font-weight:800;color:var(--brand);">₹<?= number_format($ps['net_payable'],0) ?></td>
            <td><a href="view_payslip.php?id=<?= $ps['id'] ?>" class="btn btn-sm" style="background:var(--brand-light);color:var(--brand);border:1px solid #c7d2fe;">View</a></td>
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
