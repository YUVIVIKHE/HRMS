<?php
/**
 * Attendance Calendar Widget
 * Include this in employee/attendance.php and manager/attendance.php
 * Requires: $db, $uid, and optionally $calMonth/$calYear from GET params
 */
$acMonth = (int)($_GET['ac_month'] ?? date('n'));
$acYear  = (int)($_GET['ac_year'] ?? date('Y'));
if ($acMonth < 1 || $acMonth > 12) $acMonth = (int)date('n');
if ($acYear < 2000 || $acYear > 2100) $acYear = (int)date('Y');

$acDaysInMonth = cal_days_in_month(CAL_GREGORIAN, $acMonth, $acYear);
$acFirstDow = (int)date('N', mktime(0,0,0,$acMonth,1,$acYear)); // 1=Mon

// Get attendance for this month
$acLogs = [];
try {
    $acStmt = $db->prepare("SELECT log_date, work_seconds, status FROM attendance_logs WHERE user_id=? AND DATE_FORMAT(log_date,'%Y-%m')=?");
    $acStmt->execute([$uid, sprintf('%04d-%02d', $acYear, $acMonth)]);
    foreach ($acStmt->fetchAll() as $r) $acLogs[$r['log_date']] = $r;
} catch (Exception $e) {}

// Get employee's first attendance date (to avoid marking absent before they started)
$acStartDate = null;
try {
    $firstLogStmt = $db->prepare("SELECT MIN(log_date) FROM attendance_logs WHERE user_id=?");
    $firstLogStmt->execute([$uid]);
    $acStartDate = $firstLogStmt->fetchColumn() ?: null;
} catch (Exception $e) {}

// Holidays
$acHolidays = [];
try {
    $hStmt = $db->prepare("SELECT holiday_date FROM holidays WHERE DATE_FORMAT(holiday_date,'%Y-%m')=?");
    $hStmt->execute([sprintf('%04d-%02d', $acYear, $acMonth)]);
    $acHolidays = $hStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$acMonthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$acPrevMonth = $acMonth - 1; $acPrevYear = $acYear;
if ($acPrevMonth < 1) { $acPrevMonth = 12; $acPrevYear--; }
$acNextMonth = $acMonth + 1; $acNextYear = $acYear;
if ($acNextMonth > 12) { $acNextMonth = 1; $acNextYear++; }

// Build current URL params without ac_month/ac_year
$acParams = $_GET; unset($acParams['ac_month'], $acParams['ac_year']);
$acBase = '?' . http_build_query($acParams) . (empty($acParams) ? '' : '&');
?>
<div class="card" style="margin-top:20px;">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;">
    <a href="<?= $acBase ?>ac_month=<?= $acPrevMonth ?>&ac_year=<?= $acPrevYear ?>" class="btn btn-ghost btn-sm">←</a>
    <h2 style="font-size:14px;margin:0;"><?= $acMonthNames[$acMonth] ?> <?= $acYear ?></h2>
    <a href="<?= $acBase ?>ac_month=<?= $acNextMonth ?>&ac_year=<?= $acNextYear ?>" class="btn btn-ghost btn-sm">→</a>
  </div>
  <div style="padding:8px 12px;">
    <!-- Legend -->
    <div style="display:flex;gap:12px;margin-bottom:8px;font-size:10.5px;color:var(--muted);flex-wrap:wrap;">
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#d1fae5;border:1px solid #a7f3d0;"></span> Present</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#fee2e2;border:1px solid #fca5a5;"></span> Absent</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#fce7f3;border:1px solid #f9a8d4;"></span> Partial (&lt;9h)</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#e5e7eb;border:1px solid #d1d5db;"></span> Weekend</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#fef3c7;border:1px solid #fcd34d;"></span> Holiday</span>
    </div>
    <!-- Calendar Grid -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;">
      <?php foreach(['M','T','W','T','F','S','S'] as $dh): ?>
        <div style="text-align:center;font-size:10px;font-weight:700;color:var(--muted);padding:4px 0;"><?= $dh ?></div>
      <?php endforeach; ?>
      <?php for($i=1;$i<$acFirstDow;$i++): ?>
        <div style="min-height:36px;"></div>
      <?php endfor; ?>
      <?php for($d=1;$d<=$acDaysInMonth;$d++):
        $ds = sprintf('%04d-%02d-%02d', $acYear, $acMonth, $d);
        $dow = (int)date('N', strtotime($ds));
        $isSat = ($dow === 6); $isSun = ($dow === 7);
        $isHol = in_array($ds, $acHolidays);
        $log = $acLogs[$ds] ?? null;
        $isPast = $ds <= date('Y-m-d');

        $bg = '#fff'; $border = '#e5e7eb'; $color = 'var(--text)';
        if ($isSat || $isSun) { $bg = '#f3f4f6'; $border = '#d1d5db'; $color = '#6b7280'; }
        elseif ($isHol) { $bg = '#fef3c7'; $border = '#fcd34d'; $color = '#92400e'; }
        elseif ($log && $log['work_seconds'] >= 32400) { $bg = '#d1fae5'; $border = '#a7f3d0'; $color = '#059669'; } // >=9h = green
        elseif ($log && $log['work_seconds'] > 0 && $log['work_seconds'] < 32400) { $bg = '#fce7f3'; $border = '#f9a8d4'; $color = '#be185d'; } // <9h = pink
        elseif ($isPast && !$isSat && !$isSun && !$isHol && $acStartDate && $ds >= $acStartDate) { $bg = '#fee2e2'; $border = '#fca5a5'; $color = '#dc2626'; } // absent = red (only after joining)
      ?>
        <div style="min-height:36px;background:<?=$bg?>;border:1px solid <?=$border?>;border-radius:6px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:<?=$color?>;" title="<?=$ds?>">
          <?= $d ?>
          <?php if($log && $log['work_seconds']): ?>
            <span style="font-size:8px;font-weight:600;opacity:.7;"><?= floor($log['work_seconds']/3600) ?>h</span>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</div>
