<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: payslips.php"); exit; }

$ps = $db->prepare("
    SELECT ps.*, u.name AS emp_name, u.email AS emp_email,
           e.employee_id AS emp_code, e.job_title, e.date_of_joining, e.location, e.base_location,
           e.pan, e.uan_number, e.pf_account_number, e.esi_number, e.account_number, e.ifsc_code,
           d.name AS dept_name
    FROM payslips ps
    JOIN users u ON ps.user_id = u.id
    JOIN employees e ON e.email = u.email
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE ps.id = ?
");
$ps->execute([$id]); $ps = $ps->fetch();
if (!$ps) { header("Location: payslips.php"); exit; }

if ($_SESSION['role'] === 'employee' && $ps['user_id'] != $_SESSION['user_id']) { header("Location: ../employee/dashboard.php"); exit; }
if ($_SESSION['role'] === 'manager' && $ps['user_id'] != $_SESSION['user_id']) { header("Location: ../manager/dashboard.php"); exit; }

$customDeds = json_decode($ps['custom_deductions'] ?? '[]', true) ?: [];
$customAdds = json_decode($ps['custom_additions'] ?? '[]', true) ?: [];
$monthName = date('F Y', mktime(0,0,0,$ps['month'],1,$ps['year']));
$totalEmployerCost = (float)($ps['total_employer_cost'] ?? 0);
$daysPayable = (float)($ps['days_payable'] ?? 0);

// Build earnings array
$earnings = [];
$earnings[] = ['Basic', $ps['basic_salary']];
if ($ps['hra'] > 0) $earnings[] = ['House Rent Allowance', $ps['hra']];
if ($ps['conveyance'] > 0) $earnings[] = ['Conveyance Allowance', $ps['conveyance']];
if ($ps['education_allowance'] > 0) $earnings[] = ['Education Allowance', $ps['education_allowance']];
if ($ps['special_allowance'] > 0) $earnings[] = ['Special Allowance', $ps['special_allowance']];
if ($ps['lta'] > 0) $earnings[] = ['Leave Travel Allowance', $ps['lta']];
if ($ps['mediclaim_insurance'] > 0) $earnings[] = ['Mediclaim Insurance', $ps['mediclaim_insurance']];
if ($ps['medical_reimbursement'] > 0) $earnings[] = ['Medical Reimbursement', $ps['medical_reimbursement']];
if ($ps['mobile_internet'] > 0) $earnings[] = ['Mobile & Internet Allowance', $ps['mobile_internet']];
if ($ps['personal_allowance'] > 0) $earnings[] = ['Personal Allowance', $ps['personal_allowance']];
foreach ($customAdds as $ca) { if ($ca['amount'] > 0) $earnings[] = [$ca['name'], $ca['amount']]; }
if ($ps['bonus'] > 0) $earnings[] = ['Bonus', $ps['bonus']];

// Build deductions array
$deductions = [];
if ($ps['epf_employee'] > 0) $deductions[] = ['EPF – Employee (3.67%)', $ps['epf_employee']];
if ($ps['eps_employer'] > 0) $deductions[] = ['EPS – Employer (8.33%)', $ps['eps_employer']];
if ($ps['edli_employer'] > 0) $deductions[] = ['EDLI – Employer (0.5%)', $ps['edli_employer']];
if ($ps['epf_admin'] > 0) $deductions[] = ['EPF Admin Charges (0.5%)', $ps['epf_admin']];
if ($ps['professional_tax'] > 0) $deductions[] = ['PT (Professional Tax)', $ps['professional_tax']];
if ($ps['income_tax'] > 0) $deductions[] = ['Income Tax (TDS)', $ps['income_tax']];
if ($ps['esi_employee'] > 0) $deductions[] = ['ESI – Employee (0.75%)', $ps['esi_employee']];
if ($ps['esi_employer'] > 0) $deductions[] = ['ESI – Employer (3.25%)', $ps['esi_employer']];
foreach ($customDeds as $cd) { if ($cd['amount'] > 0) $deductions[] = [$cd['name'], $cd['amount']]; }

$maxRows = max(count($earnings), count($deductions));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payslip — <?= htmlspecialchars($ps['emp_name']) ?> — <?= $monthName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:#f1f5f9;padding:20px;}
.slip-wrap{max-width:900px;margin:0 auto;}
.slip{background:#fff;border:1.5px solid #1e293b;padding:0;}
.slip-title{padding:12px 20px;font-size:16px;font-weight:800;border-bottom:1.5px solid #1e293b;}
.info-table{width:100%;border-collapse:collapse;}
.info-table td{padding:6px 12px;font-size:12px;border:1px solid #cbd5e1;}
.info-table .lbl{font-weight:700;background:#f8fafc;width:140px;}
.info-table .val{min-width:160px;}
.pay-table{width:100%;border-collapse:collapse;font-size:12px;}
.pay-table th,.pay-table td{padding:6px 10px;border:1px solid #cbd5e1;}
.pay-table th{background:#f1f5f9;font-weight:700;text-align:left;}
.pay-table .num{text-align:right;font-variant-numeric:tabular-nums;}
.pay-table .total-row{background:#f1f5f9;font-weight:800;}
.pay-table .net-row{background:#ede9fe;font-weight:800;font-size:13px;}
.footer-note{padding:8px 20px;font-size:11px;color:#64748b;font-style:italic;border-top:1px solid #cbd5e1;}
.no-print{margin-top:16px;text-align:center;}
@media print{.no-print{display:none!important;}body{background:#fff;padding:0;}.slip{border:none;}}
</style>
</head>
<body>
<div class="slip-wrap">
  <div class="slip">
    <!-- Title -->
    <div class="slip-title">PAY SLIP FOR THE MONTH OF : <?= strtoupper($monthName) ?></div>

    <!-- Employee Info -->
    <table class="info-table">
      <tr><td class="lbl">Name</td><td class="val"><?= htmlspecialchars($ps['emp_name']) ?></td><td class="lbl">PAN No.</td><td class="val"><?= htmlspecialchars($ps['pan'] ?? '') ?></td></tr>
      <tr><td class="lbl">Employee Code</td><td class="val"><?= htmlspecialchars($ps['emp_code'] ?: '') ?></td><td class="lbl">ESI No.</td><td class="val"><?= htmlspecialchars($ps['esi_number'] ?? '') ?></td></tr>
      <tr><td class="lbl">Designation</td><td class="val"><?= htmlspecialchars($ps['job_title'] ?: '') ?></td><td class="lbl">PF No.</td><td class="val"><?= htmlspecialchars($ps['pf_account_number'] ?? '') ?></td></tr>
      <tr><td class="lbl">Bank Name</td><td class="val"><?= htmlspecialchars($ps['ifsc_code'] ?? '') ?></td><td class="lbl">Bank Account No.</td><td class="val"><?= htmlspecialchars($ps['account_number'] ?? '') ?></td></tr>
      <tr><td class="lbl">Department</td><td class="val"><?= htmlspecialchars($ps['dept_name'] ?: '') ?></td><td class="lbl">Location</td><td class="val"><?= htmlspecialchars($ps['location'] ?? $ps['base_location'] ?? '') ?></td></tr>
      <tr><td class="lbl">UAN NO.</td><td class="val" colspan="3"><?= htmlspecialchars($ps['uan_number'] ?? '') ?></td></tr>
    </table>

    <!-- Earnings & Deductions side by side -->
    <table class="pay-table">
      <tr>
        <th style="width:35%;">Earnings</th>
        <th class="num" style="width:15%;">Earned</th>
        <th style="width:35%;">Deductions</th>
        <th class="num" style="width:15%;">Earned</th>
      </tr>
      <?php for ($i = 0; $i < $maxRows; $i++): ?>
      <tr>
        <td><?= isset($earnings[$i]) ? htmlspecialchars($earnings[$i][0]) : '' ?></td>
        <td class="num"><?= isset($earnings[$i]) ? number_format($earnings[$i][1], 2) : '' ?></td>
        <td><?= isset($deductions[$i]) ? htmlspecialchars($deductions[$i][0]) : '' ?></td>
        <td class="num"><?= isset($deductions[$i]) ? number_format($deductions[$i][1], 2) : '' ?></td>
      </tr>
      <?php endfor; ?>
      <!-- Totals -->
      <tr class="total-row">
        <td>Total Earnings</td>
        <td class="num"><?= number_format($ps['total_earnings'], 2) ?></td>
        <td>Total Deductions</td>
        <td class="num"><?= number_format((float)$ps['total_deductions'] + $totalEmployerCost, 2) ?></td>
      </tr>
      <!-- Net Pay -->
      <tr class="net-row">
        <td>Net Pay :</td>
        <td class="num" colspan="1"><?= number_format($ps['net_payable'], 2) ?></td>
        <td>Days Payable :</td>
        <td class="num"><?= number_format($daysPayable, 1) ?></td>
      </tr>
    </table>

    <div class="footer-note">* Computer generated salary slip. Signature not required.</div>
  </div>

  <div class="no-print">
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Print / Download PDF</button>
    <?php if($_SESSION['role'] === 'admin'): ?>
      <a href="payslips.php?month=<?= $ps['month'] ?>&year=<?= $ps['year'] ?>" style="margin-left:10px;color:#4f46e5;font-size:13px;font-weight:600;text-decoration:none;">← Back to Payslips</a>
    <?php else: ?>
      <a href="javascript:history.back()" style="margin-left:10px;color:#4f46e5;font-size:13px;font-weight:600;text-decoration:none;">← Back</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
