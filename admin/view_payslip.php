<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: payslips.php"); exit; }

$ps = $db->prepare("
    SELECT ps.*, u.name AS emp_name, u.email AS emp_email,
           e.employee_id AS emp_code, e.job_title, e.date_of_joining,
           d.name AS dept_name
    FROM payslips ps
    JOIN users u ON ps.user_id = u.id
    JOIN employees e ON e.email = u.email
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE ps.id = ?
");
$ps->execute([$id]);
$ps = $ps->fetch();
if (!$ps) { header("Location: payslips.php"); exit; }

// Access control
if ($_SESSION['role'] === 'employee' && $ps['user_id'] != $_SESSION['user_id']) { header("Location: ../employee/dashboard.php"); exit; }
if ($_SESSION['role'] === 'manager' && $ps['user_id'] != $_SESSION['user_id']) { header("Location: ../manager/dashboard.php"); exit; }

$customDeds = json_decode($ps['custom_deductions'] ?? '[]', true) ?: [];
$monthName = date('F Y', mktime(0,0,0,$ps['month'],1,$ps['year']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payslip — <?= htmlspecialchars($ps['emp_name']) ?> — <?= $monthName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.payslip{max-width:700px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;}
.ps-header{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:24px;color:#fff;}
.ps-header h1{font-size:20px;font-weight:800;margin:0 0 4px;}
.ps-header p{font-size:13px;opacity:.85;margin:0;}
.ps-info{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:20px 24px;background:#f9fafb;border-bottom:1px solid #e5e7eb;}
.ps-info-item{font-size:12.5px;}
.ps-info-item .label{color:#6b7280;font-weight:600;margin-bottom:2px;}
.ps-info-item .value{color:#1e293b;font-weight:700;}
.ps-body{padding:24px;}
.ps-table{width:100%;font-size:13px;border-collapse:collapse;}
.ps-table td{padding:8px 0;border-bottom:1px solid #f3f4f6;}
.ps-table .section-head{font-weight:800;color:#4f46e5;font-size:12px;text-transform:uppercase;letter-spacing:.04em;padding-top:16px;}
.ps-table .total-row td{border-top:2px solid #e5e7eb;font-weight:800;font-size:14px;padding-top:12px;}
.ps-net{background:#ede9fe;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;border-top:2px solid #c7d2fe;}
.ps-net .label{font-size:14px;font-weight:700;color:#4f46e5;}
.ps-net .amount{font-size:22px;font-weight:800;color:#4f46e5;}
@media print{.no-print{display:none!important;} .payslip{border:none;box-shadow:none;}}
</style>
</head>
<body>
<div class="app-shell">
<?php
if ($_SESSION['role'] === 'admin') include __DIR__ . '/sidebar.php';
elseif ($_SESSION['role'] === 'manager') include __DIR__ . '/../manager/sidebar.php';
else include __DIR__ . '/../employee/sidebar.php';
?>
<div class="main-content">

  <header class="topbar no-print">
    <div class="topbar-left">
      <span class="page-title">Payslip</span>
      <span class="page-breadcrumb"><?= $monthName ?></span>
    </div>
    <div class="topbar-right">
      <button class="btn btn-primary btn-sm" onclick="window.print()">Print / Download</button>
    </div>
  </header>

  <div class="page-body">
    <div class="payslip">
      <!-- Header -->
      <div class="ps-header">
        <h1>Payslip — <?= $monthName ?></h1>
        <p>HRMS Portal</p>
      </div>

      <!-- Employee Info -->
      <div class="ps-info">
        <div class="ps-info-item"><div class="label">Employee Name</div><div class="value"><?= htmlspecialchars($ps['emp_name']) ?></div></div>
        <div class="ps-info-item"><div class="label">Employee ID</div><div class="value"><?= htmlspecialchars($ps['emp_code'] ?: '—') ?></div></div>
        <div class="ps-info-item"><div class="label">Department</div><div class="value"><?= htmlspecialchars($ps['dept_name'] ?: '—') ?></div></div>
        <div class="ps-info-item"><div class="label">Designation</div><div class="value"><?= htmlspecialchars($ps['job_title'] ?: '—') ?></div></div>
        <div class="ps-info-item"><div class="label">Date of Joining</div><div class="value"><?= $ps['date_of_joining'] ? date('d M Y', strtotime($ps['date_of_joining'])) : '—' ?></div></div>
        <div class="ps-info-item"><div class="label">Pay Period</div><div class="value"><?= $monthName ?></div></div>
      </div>

      <!-- Body -->
      <div class="ps-body">
        <table class="ps-table">
          <tr><td class="section-head" colspan="2">Earnings</td></tr>
          <tr><td>Basic Salary</td><td style="text-align:right;">₹<?= number_format($ps['basic_salary'],2) ?></td></tr>
          <tr><td>HRA</td><td style="text-align:right;">₹<?= number_format($ps['hra'],2) ?></td></tr>
          <?php if($ps['special_allowance'] > 0): ?><tr><td>Special Allowance</td><td style="text-align:right;">₹<?= number_format($ps['special_allowance'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['conveyance'] > 0): ?><tr><td>Conveyance</td><td style="text-align:right;">₹<?= number_format($ps['conveyance'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['education_allowance'] > 0): ?><tr><td>Education Allowance</td><td style="text-align:right;">₹<?= number_format($ps['education_allowance'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['lta'] > 0): ?><tr><td>LTA</td><td style="text-align:right;">₹<?= number_format($ps['lta'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['mediclaim_insurance'] > 0): ?><tr><td>Mediclaim Insurance</td><td style="text-align:right;">₹<?= number_format($ps['mediclaim_insurance'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['medical_reimbursement'] > 0): ?><tr><td>Medical Reimbursement</td><td style="text-align:right;">₹<?= number_format($ps['medical_reimbursement'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['mobile_internet'] > 0): ?><tr><td>Mobile & Internet</td><td style="text-align:right;">₹<?= number_format($ps['mobile_internet'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['personal_allowance'] > 0): ?><tr><td>Personal Allowance</td><td style="text-align:right;">₹<?= number_format($ps['personal_allowance'],2) ?></td></tr><?php endif; ?>
          <?php if($ps['bonus'] > 0): ?><tr><td>Bonus</td><td style="text-align:right;">₹<?= number_format($ps['bonus'],2) ?></td></tr><?php endif; ?>
          <tr class="total-row"><td style="color:#059669;">Total Earnings</td><td style="text-align:right;color:#059669;">₹<?= number_format($ps['total_earnings'],2) ?></td></tr>

          <tr><td class="section-head" colspan="2" style="padding-top:24px;">Deductions</td></tr>
          <tr><td>Income Tax (TDS)</td><td style="text-align:right;">₹<?= number_format($ps['income_tax'],2) ?></td></tr>
          <tr><td>Employee ESI</td><td style="text-align:right;">₹<?= number_format($ps['esi'],2) ?></td></tr>
          <tr><td>Employee PF</td><td style="text-align:right;">₹<?= number_format($ps['pf'],2) ?></td></tr>
          <?php foreach($customDeds as $cd): ?>
          <tr><td><?= htmlspecialchars($cd['name']) ?></td><td style="text-align:right;">₹<?= number_format($cd['amount'],2) ?></td></tr>
          <?php endforeach; ?>
          <tr class="total-row"><td style="color:#dc2626;">Total Deductions</td><td style="text-align:right;color:#dc2626;">₹<?= number_format($ps['total_deductions'],2) ?></td></tr>
        </table>
      </div>

      <!-- Net -->
      <div class="ps-net">
        <div class="label">Net Payable</div>
        <div class="amount">₹<?= number_format($ps['net_payable'],2) ?></div>
      </div>
    </div>

    <div style="margin-top:20px;text-align:center;" class="no-print">
      <?php if($_SESSION['role'] === 'admin'): ?>
        <a href="payslips.php?month=<?= $ps['month'] ?>&year=<?= $ps['year'] ?>" class="btn btn-secondary">← Back to Payslips</a>
      <?php else: ?>
        <a href="javascript:history.back()" class="btn btn-secondary">← Back</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
</body>
</html>
