<?php
/**
 * shared/calendar_widget.php
 * Requires: $db (PDO), $uid (int), $calYear (int), $calMonth (int)
 */

// ── Attendance logs ──────────────────────────────────────────
$attLogs = $db->prepare("SELECT log_date, status, clock_in, clock_out, work_seconds FROM attendance_logs WHERE user_id=? AND YEAR(log_date)=? AND MONTH(log_date)=?");
$attLogs->execute([$uid, $calYear, $calMonth]);
$attMap = [];
foreach ($attLogs->fetchAll() as $l) $attMap[$l['log_date']] = $l;

// ── Holidays ─────────────────────────────────────────────────
$holMap = [];
try {
    $holRows = $db->prepare("SELECT holiday_date, title, description FROM holidays WHERE YEAR(holiday_date)=? AND MONTH(holiday_date)=?");
    $holRows->execute([$calYear, $calMonth]);
    foreach ($holRows->fetchAll() as $h) $holMap[$h['holiday_date']] = $h;
} catch (Exception $e) {}

// ── Birthdays this month ──────────────────────────────────────
$bdMap = [];
try {
    $bdRows = $db->prepare("
        SELECT e.first_name, e.last_name, e.personal_email,
               DAY(e.date_of_birth) AS bday,
               u.id AS user_id
        FROM employees e
        LEFT JOIN users u ON u.email = e.email
        WHERE MONTH(e.date_of_birth) = ?
          AND e.date_of_birth IS NOT NULL
          AND e.personal_email IS NOT NULL
          AND e.personal_email != ''
    ");
    $bdRows->execute([$calMonth]);
    foreach ($bdRows->fetchAll() as $b) {
        $bdMap[$b['bday']][] = $b;
    }
} catch (Exception $e) {}

// ── Calendar math ─────────────────────────────────────────────
$today       = date('Y-m-d');
$daysInMonth = (int)date('t', mktime(0,0,0,$calMonth,1,$calYear));
$firstDow    = (int)date('N', mktime(0,0,0,$calMonth,1,$calYear)); // 1=Mon
$monthName   = date('F Y', mktime(0,0,0,$calMonth,1,$calYear));
$prevMonth   = $calMonth == 1  ? 12 : $calMonth - 1;
$prevYear    = $calMonth == 1  ? $calYear - 1 : $calYear;
$nextMonth   = $calMonth == 12 ? 1  : $calMonth + 1;
$nextYear    = $calMonth == 12 ? $calYear + 1 : $calYear;
$calBase     = basename($_SERVER['PHP_SELF']);

// ── Wish send handler (AJAX endpoint embedded) ────────────────
// Handled via send_wish.php (created separately)

function fmtCalTime($dt) { return $dt ? date('h:i A', strtotime($dt)) : null; }
function fmtCalHrs($sec) {
    if (!$sec) return null;
    return floor($sec/3600).'h '.str_pad(floor(($sec%3600)/60),2,'0',STR_PAD_LEFT).'m';
}
?>

<style>
.cal-widget { overflow: hidden; }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; padding:6px 10px 12px; }
.cal-day-label { text-align:center; font-size:11px; font-weight:700; color:#94a3b8; padding:6px 0; }
.cal-cell {
  position: relative;
  text-align: center;
  padding: 6px 2px;
  border-radius: 7px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: default;
  transition: transform .1s;
  min-height: 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
}
.cal-cell:hover { transform: scale(1.12); z-index: 10; }
.cal-cell .cal-dot {
  width: 5px; height: 5px; border-radius: 50%;
  flex-shrink: 0;
}

/* Tooltip */
.cal-cell .cal-tip {
  display: none;
  position: absolute;
  bottom: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  background: #1e293b;
  color: #f1f5f9;
  font-size: 11.5px;
  font-weight: 500;
  padding: 7px 10px;
  border-radius: 8px;
  white-space: nowrap;
  z-index: 200;
  box-shadow: 0 4px 12px rgba(0,0,0,.3);
  pointer-events: none;
  min-width: 140px;
  text-align: left;
  line-height: 1.6;
}
.cal-cell .cal-tip::after {
  content: '';
  position: absolute;
  top: 100%; left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #1e293b;
}
.cal-cell:hover .cal-tip { display: block; }

/* Status colours */
.cal-present  { background: #166534; color: #fff; }
.cal-absent   { background: #991b1b; color: #fff; }
.cal-late     { background: #854d0e; color: #fff; }
.cal-remote   { background: #1e40af; color: #fff; }
.cal-holiday  { background: #7c3aed; color: #fff; }
.cal-birthday { background: #be185d; color: #fff; }
.cal-today    { background: #0f172a; color: #fff; box-shadow: 0 0 0 2px #6366f1; }
.cal-sunday   { color: #94a3b8; }
.cal-future   { color: #64748b; }
.cal-empty    {}

/* Wish button */
.wish-btn {
  font-size: 10px; font-weight: 700;
  background: rgba(255,255,255,.25);
  border: 1px solid rgba(255,255,255,.4);
  color: #fff;
  border-radius: 4px;
  padding: 1px 5px;
  cursor: pointer;
  transition: background .15s;
  pointer-events: all;
}
.wish-btn:hover { background: rgba(255,255,255,.45); }
</style>

<div class="cal-widget card">
  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-light);background:var(--surface);">
    <a href="<?= $calBase ?>?cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>"
       style="text-decoration:none;color:var(--muted);width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;border:1px solid var(--border);">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div style="text-align:center;">
      <div style="font-size:14px;font-weight:800;color:var(--text);"><?= $monthName ?></div>
    </div>
    <a href="<?= $calBase ?>?cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>"
       style="text-decoration:none;color:var(--muted);width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;border:1px solid var(--border);">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <!-- Day labels -->
  <div style="display:grid;grid-template-columns:repeat(7,1fr);padding:8px 10px 0;">
    <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
      <div class="cal-day-label"><?= $d ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Days -->
  <div class="cal-grid">
    <?php
    // Empty leading cells
    for ($i = 1; $i < $firstDow; $i++) echo '<div class="cal-cell cal-empty"></div>';

    for ($day = 1; $day <= $daysInMonth; $day++):
        $dateStr  = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
        $dow      = (int)date('N', mktime(0,0,0,$calMonth,$day,$calYear));
        $isSun    = $dow === 7;
        $isToday  = $dateStr === $today;
        $isFuture = $dateStr > $today;
        $isPast   = $dateStr < $today;

        $isHol  = isset($holMap[$dateStr]);
        $isBd   = isset($bdMap[$day]);
        $att    = $attMap[$dateStr] ?? null;
        $status = $att['status'] ?? null;

        // Priority: today > holiday > birthday > attendance > absent > sunday > future
        $cls     = 'cal-future';
        $tipLines = [];
        $dots    = [];

        if ($isToday) {
            $cls = 'cal-today';
            $tipLines[] = '📅 Today';
        }

        if ($isHol) {
            $cls = $isToday ? 'cal-today' : 'cal-holiday';
            $h   = $holMap[$dateStr];
            $tipLines[] = '🎉 '.$h['title'];
            if ($h['description']) $tipLines[] = $h['description'];
        }

        if ($isBd) {
            if (!$isHol && !$isToday) $cls = 'cal-birthday';
            foreach ($bdMap[$day] as $b) {
                $tipLines[] = '🎂 '.htmlspecialchars($b['first_name'].' '.$b['last_name']).'\'s Birthday';
            }
        }

        if ($att && !$isHol) {
            if ($status === 'present')  { if (!$isToday && !$isBd) $cls = 'cal-present'; }
            elseif ($status === 'late') { if (!$isToday && !$isBd) $cls = 'cal-late'; }
            elseif ($status === 'remote'){ if (!$isToday && !$isBd) $cls = 'cal-remote'; }
            elseif ($status === 'absent'){ if (!$isToday && !$isBd) $cls = 'cal-absent'; }

            $ci = fmtCalTime($att['clock_in']);
            $co = fmtCalTime($att['clock_out']);
            $wh = fmtCalHrs($att['work_seconds']);
            if ($ci) $tipLines[] = '⏰ In: '.$ci;
            if ($co) $tipLines[] = '🚪 Out: '.$co;
            if ($wh) $tipLines[] = '⏱ '.$wh.' worked';
            if (!$ci && $status === 'absent') $tipLines[] = '❌ Absent';
        } elseif ($isPast && !$isSun && !$isHol && !$att) {
            if (!$isToday && !$isBd) $cls = 'cal-absent';
            $tipLines[] = '❌ No attendance record';
        }

        if ($isSun && $cls === 'cal-future') $cls = 'cal-sunday';
        if (!$isFuture && !$isPast && !$isToday) $cls = 'cal-future'; // shouldn't happen

        $tipHtml = '';
        if (!empty($tipLines)) {
            $tipHtml = '<div class="cal-tip">'.implode('<br>', array_map('htmlspecialchars', $tipLines)).'</div>';
        }

        // Birthday wish buttons (shown inside cell)
        $wishHtml = '';
        if ($isBd) {
            foreach ($bdMap[$day] as $b) {
                $name  = htmlspecialchars($b['first_name']);
                $email = htmlspecialchars($b['personal_email']);
                $wishHtml .= '<button class="wish-btn" onclick="sendWish(event,\''.addslashes($b['first_name'].' '.$b['last_name']).'\',\''.addslashes($b['personal_email']).'\')" title="Send birthday wish to '.$name.'">🎁</button>';
            }
        }

        echo '<div class="cal-cell '.$cls.'">'.$day.$wishHtml.$tipHtml.'</div>';
    endfor;
    ?>
  </div>

  <!-- Legend -->
  <div style="display:flex;gap:10px;flex-wrap:wrap;padding:10px 14px;border-top:1px solid var(--border-light);background:var(--surface-2);border-radius:0 0 var(--radius-lg) var(--radius-lg);">
    <?php
    $legend = [
        ['#166534','Present'],['#991b1b','Absent'],['#854d0e','Late'],
        ['#1e40af','Remote'],['#7c3aed','Holiday'],['#be185d','Birthday'],['#0f172a','Today'],
    ];
    foreach ($legend as [$c,$l]):
    ?>
    <div style="display:flex;align-items:center;gap:4px;font-size:11px;color:var(--muted);">
      <span style="width:10px;height:10px;border-radius:3px;background:<?= $c ?>;display:inline-block;flex-shrink:0;"></span><?= $l ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Wish toast -->
<div id="wishToast" style="position:fixed;bottom:24px;right:24px;background:#1e293b;color:#f1f5f9;padding:12px 18px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,.3);display:none;z-index:999;max-width:320px;"></div>

<script>
function sendWish(e, name, email) {
  e.stopPropagation();
  const btn = e.target;
  btn.disabled = true;
  btn.textContent = '⏳';

  fetch('../shared/send_wish.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'name='+encodeURIComponent(name)+'&email='+encodeURIComponent(email)
  })
  .then(r => r.json())
  .then(d => {
    showWishToast(d.ok ? '🎂 Birthday wish sent to '+name+'!' : '❌ '+d.msg, d.ok);
    btn.textContent = d.ok ? '✓' : '🎁';
  })
  .catch(() => { showWishToast('❌ Network error', false); btn.textContent = '🎁'; btn.disabled = false; });
}

function showWishToast(msg, ok) {
  const t = document.getElementById('wishToast');
  t.textContent = msg;
  t.style.background = ok ? '#166534' : '#991b1b';
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 4000);
}
</script>
