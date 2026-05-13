<?php
/**
 * shared/calendar_widget.php
 * Renders a monthly attendance + holiday calendar.
 * Requires: $db (PDO), $uid (int), $calYear (int), $calMonth (int)
 */

// Fetch this month's attendance logs
$attLogs = $db->prepare("SELECT log_date, status FROM attendance_logs WHERE user_id=? AND YEAR(log_date)=? AND MONTH(log_date)=?");
$attLogs->execute([$uid, $calYear, $calMonth]);
$attMap = [];
foreach ($attLogs->fetchAll() as $l) $attMap[$l['log_date']] = $l['status'];

// Fetch holidays for this month
$holMap = [];
try {
    $holRows = $db->prepare("SELECT holiday_date, title FROM holidays WHERE YEAR(holiday_date)=? AND MONTH(holiday_date)=?");
    $holRows->execute([$calYear, $calMonth]);
    foreach ($holRows->fetchAll() as $h) $holMap[$h['holiday_date']] = $h['title'];
} catch (Exception $e) { /* table may not exist yet */ }

$today      = date('Y-m-d');
$daysInMonth = (int)date('t', mktime(0,0,0,$calMonth,1,$calYear));
$firstDow    = (int)date('N', mktime(0,0,0,$calMonth,1,$calYear)); // 1=Mon
$monthName   = date('F Y', mktime(0,0,0,$calMonth,1,$calYear));

$prevMonth = $calMonth == 1 ? 12 : $calMonth - 1;
$prevYear  = $calMonth == 1 ? $calYear - 1 : $calYear;
$nextMonth = $calMonth == 12 ? 1 : $calMonth + 1;
$nextYear  = $calMonth == 12 ? $calYear + 1 : $calYear;
?>
<?php
$calBase = basename($_SERVER['PHP_SELF']);
?>
<div class="cal-widget card">
  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-light);">
    <a href="<?= $calBase ?>?cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>" style="text-decoration:none;color:var(--muted);padding:4px 8px;border-radius:6px;transition:background .15s;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <span style="font-size:14px;font-weight:700;color:var(--text);"><?= $monthName ?></span>
    <a href="<?= $calBase ?>?cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>" style="text-decoration:none;color:var(--muted);padding:4px 8px;border-radius:6px;transition:background .15s;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <!-- Day labels -->
  <div style="display:grid;grid-template-columns:repeat(7,1fr);padding:8px 12px 4px;">
    <?php foreach(['M','T','W','T','F','S','S'] as $d): ?>
      <div style="text-align:center;font-size:11px;font-weight:700;color:var(--muted-light);padding:4px 0;"><?= $d ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Days grid -->
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;padding:4px 12px 14px;">
    <?php
    // Empty cells before first day
    for ($i = 1; $i < $firstDow; $i++) {
        echo '<div></div>';
    }

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
        $dow     = (int)date('N', mktime(0,0,0,$calMonth,$day,$calYear)); // 1=Mon,7=Sun
        $isSun   = $dow === 7;
        $isToday = $dateStr === $today;
        $isFuture= $dateStr > $today;
        $isHol   = isset($holMap[$dateStr]);
        $attStatus = $attMap[$dateStr] ?? null;

        // Determine cell style
        $bg = ''; $color = 'var(--text-2)'; $border = ''; $title = '';

        if ($isHol) {
            $bg    = '#fef3c7';
            $color = '#92400e';
            $border= '2px solid #fcd34d';
            $title = $holMap[$dateStr];
        } elseif ($isToday) {
            $bg    = 'var(--brand)';
            $color = '#fff';
            $border= '';
        } elseif ($isSun) {
            $color = 'var(--muted-light)';
        } elseif (!$isFuture && $attStatus) {
            // Past working day with log
            if (in_array($attStatus, ['present','remote','late'])) {
                $bg    = '#d1fae5';
                $color = '#065f46';
            } elseif ($attStatus === 'absent') {
                $bg    = '#fee2e2';
                $color = '#991b1b';
            }
        } elseif (!$isFuture && !$isSun && !$isHol && $dateStr < $today) {
            // Past working day with no log = absent
            $bg    = '#fee2e2';
            $color = '#991b1b';
        }

        $style = "text-align:center;padding:5px 2px;border-radius:6px;font-size:12.5px;font-weight:600;cursor:default;";
        if ($bg)     $style .= "background:$bg;";
        if ($color)  $style .= "color:$color;";
        if ($border) $style .= "border:$border;";

        $titleAttr = $title ? ' title="'.htmlspecialchars($title).'"' : '';
        echo '<div style="'.$style.'"'.$titleAttr.'>'.$day.'</div>';
    }
    ?>
  </div>

  <!-- Legend -->
  <div style="display:flex;gap:12px;flex-wrap:wrap;padding:10px 16px;border-top:1px solid var(--border-light);background:var(--surface-2);border-radius:0 0 var(--radius-lg) var(--radius-lg);">
    <div style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--muted);">
      <span style="width:10px;height:10px;border-radius:3px;background:#d1fae5;display:inline-block;"></span> Present
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--muted);">
      <span style="width:10px;height:10px;border-radius:3px;background:#fee2e2;display:inline-block;"></span> Absent
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--muted);">
      <span style="width:10px;height:10px;border-radius:3px;background:#fef3c7;border:1px solid #fcd34d;display:inline-block;"></span> Holiday
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--muted);">
      <span style="width:10px;height:10px;border-radius:3px;background:var(--brand);display:inline-block;"></span> Today
    </div>
  </div>
</div>
