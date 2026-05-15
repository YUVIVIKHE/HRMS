<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

$filterMonth = (int)($_GET['month'] ?? date('n'));
$filterYear  = (int)($_GET['year'] ?? date('Y'));

// Required hours = 9, Overtime threshold = 2 hrs extra (total > 11 hrs = 39600 sec)
$REQUIRED_SEC = 9 * 3600;  // 32400
$MIN_OT_SEC = 11 * 3600;   // 39600 sec = 11 hrs (only for working days)

// Get holidays for the month
$monthHolidays = [];
try {
    $hStmt = $db->prepare("SELECT holiday_date FROM holidays WHERE DATE_FORMAT(holiday_date,'%Y-%m')=?");
    $hStmt->execute([sprintf('%04d-%02d', $filterYear, $filterMonth)]);
    $monthHolidays = $hStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// 1. OT on WORKING DAYS: work > 11 hrs (no request needed, auto-counted)
$logs = $db->prepare("
    SELECT log_date, clock_in, clock_out, work_seconds
    FROM attendance_logs
    WHERE user_id = ? AND DATE_FORMAT(log_date,'%Y-%m') = ? AND work_seconds > ?
    ORDER BY log_date ASC
");
$logs->execute([$uid, sprintf('%04d-%02d', $filterYear, $filterMonth), $MIN_OT_SEC]);
$logs = $logs->fetchAll();

$totalOTSeconds = 0;
$aclEntries = [];
foreach ($logs as $l) {
    $dow = (int)date('N', strtotime($l['log_date']));
    $isWeekendOrHoliday = ($dow === 6 || $dow === 7 || in_array($l['log_date'], $monthHolidays));
    // Only count OT for working days (not weekends/holidays — those go through ACL requests)
    if (!$isWeekendOrHoliday) {
        $otSec = (int)$l['work_seconds'] - $REQUIRED_SEC;
        $totalOTSeconds += $otSec;
        $aclEntries[] = [
            'date' => $l['log_date'],
            'clock_in' => $l['clock_in'],
            'clock_out' => $l['clock_out'],
            'total_hrs' => round((int)$l['work_seconds'] / 3600, 2),
            'ot_hrs' => round($otSec / 3600, 2),
            'type' => 'OT (Working Day)',
        ];
    }
}
$totalOTHrs = round($totalOTSeconds / 3600, 2);

// 2. Weekend/Holiday work: Only count APPROVED ACL requests
$approvedACL = $db->prepare("
    SELECT work_date, hours FROM acl_requests
    WHERE user_id = ? AND status = 'approved' AND DATE_FORMAT(work_date,'%Y-%m') = ?
");
$approvedACL->execute([$uid, sprintf('%04d-%02d', $filterYear, $filterMonth)]);
$approvedACL = $approvedACL->fetchAll();
$aclFromRequests = 0;
foreach ($approvedACL as $ar) {
    $aclFromRequests += (float)$ar['hours'];
    $aclEntries[] = [
        'date' => $ar['work_date'],
        'clock_in' => '-',
        'clock_out' => '-',
        'total_hrs' => (float)$ar['hours'],
        'ot_hrs' => (float)$ar['hours'],
        'type' => 'Weekend/Holiday (Approved)',
    ];
}

// 3. Pending ACL requests (show but don't count)
$pendingACL = $db->prepare("
    SELECT work_date, hours FROM acl_requests
    WHERE user_id = ? AND status = 'pending' AND DATE_FORMAT(work_date,'%Y-%m') = ?
");
$pendingACL->execute([$uid, sprintf('%04d-%02d', $filterYear, $filterMonth)]);
$pendingACL = $pendingACL->fetchAll();
$pendingHrs = array_sum(array_column($pendingACL, 'hours'));

$totalACLHrs = $totalOTHrs + $aclFromRequests;
$aclDays = round($totalACLHrs / 9, 2);

// ACL utilized (leaves taken as ACL)
$aclUtilized = 0;
try {
    $au = $db->prepare("SELECT COALESCE(SUM(days),0) FROM leave_applications WHERE user_id=? AND leave_type_id=0 AND status IN ('approved','pending')");
    $au->execute([$uid]); $aclUtilized = (float)$au->fetchColumn();
} catch (Exception $e) {}

// Sort entries by date
usort($aclEntries, fn($a, $b) => strcmp($a['date'], $b['date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My ACL – HRMS Portal</title>
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
      <span class="page-title">My ACL</span>
      <span class="page-breadcrumb">Accumulated Compensatory Leave</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Employee</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <!-- Filter -->
    <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;align-items:center;">
      <select name="month" class="form-control" style="font-size:13px;padding:9px 12px;width:auto;" onchange="this.form.submit()">
        <?php for($m=1;$m<=12;$m++): ?>
          <option value="<?= $m ?>" <?= $m==$filterMonth?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
        <?php endfor; ?>
      </select>
      <select name="year" class="form-control" style="font-size:13px;padding:9px 12px;width:auto;" onchange="this.form.submit()">
        <?php for($y=(int)date('Y')-2;$y<=(int)date('Y');$y++): ?>
          <option value="<?= $y ?>" <?= $y==$filterYear?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <a href="acl_request.php" class="btn btn-primary btn-sm" style="margin-left:auto;">+ Request ACL</a>
    </form>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count($aclEntries) ?></div>
          <div class="stat-label">OT Days</div>
          <div class="stat-sub">Days with 2+ hrs extra</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= $totalOTHrs ?></div>
          <div class="stat-label">OT Hours</div>
          <div class="stat-sub"><?php if($aclFromRequests > 0): ?>+ <?= $aclFromRequests ?> hrs (approved requests)<?php else: ?>This month<?php endif; ?></div>
        </div>
      </div>
      <div class="stat-card" style="border-color:var(--brand);background:var(--brand-light);">
        <div class="stat-icon" style="background:var(--brand);color:#fff;">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value" style="color:var(--brand);"><?= $aclDays ?></div>
          <div class="stat-label">ACL Earned</div>
          <div class="stat-sub"><?= $totalACLHrs ?> hrs total ÷ 9<?php if($pendingHrs > 0): ?> · <span style="color:var(--yellow);"><?= $pendingHrs ?>h pending</span><?php endif; ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--red-bg);color:var(--red);">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value" style="color:var(--red);"><?= $aclUtilized ?></div>
          <div class="stat-label">ACL Used</div>
          <div class="stat-sub">Available: <?= max(0, $aclDays - $aclUtilized) ?> day(s)</div>
        </div>
      </div>
    </div>

    <!-- Info -->
    <div style="background:var(--brand-light);border:1px solid #c7d2fe;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--brand);font-weight:500;">
      ACL is calculated when you work more than 11 hours (9 required + 2 minimum overtime). Extra hours beyond 9 are accumulated. Every 9 OT hours = 1 compensatory leave day.
    </div>

    <!-- Table -->
    <?php if(empty($aclEntries)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:50px 20px;">
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No overtime recorded</div>
        <div style="font-size:13px;color:var(--muted);">Work more than 11 hours in a day to earn ACL.</div>
      </div></div>
    <?php else: ?>
    <div class="table-wrap">
      <div class="table-toolbar"><h2>ACL Log — <?= date('F Y', mktime(0,0,0,$filterMonth,1,$filterYear)) ?></h2></div>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th style="text-align:center;">Total Hrs</th>
            <th style="text-align:center;">ACL Hrs</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($aclEntries as $e): ?>
          <tr>
            <td class="font-semibold"><?= date('D, d M', strtotime($e['date'])) ?></td>
            <td class="text-sm"><?= $e['clock_in'] !== '-' ? date('h:i A', strtotime($e['clock_in'])) : '—' ?></td>
            <td class="text-sm"><?= $e['clock_out'] !== '-' ? date('h:i A', strtotime($e['clock_out'])) : '—' ?></td>
            <td style="text-align:center;font-weight:700;"><?= $e['total_hrs'] ?></td>
            <td style="text-align:center;font-weight:800;color:var(--green-text);"><?= $e['ot_hrs'] ?></td>
            <td><span class="badge <?= strpos($e['type'],'OT')!==false?'badge-blue':'badge-green' ?>" style="font-size:10.5px;"><?= $e['type'] ?></span></td>
          </tr>
          <?php endforeach; ?>
          <tr style="background:var(--surface-2);font-weight:800;">
            <td colspan="4">Total ACL Hours</td>
            <td style="text-align:center;color:var(--brand);"><?= $totalACLHrs ?></td>
            <td>= <?= $aclDays ?> day(s)</td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>
</body>
</html>
