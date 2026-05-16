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
$bdMap = []; // day → array of people
try {
    $bdRows = $db->prepare("
        SELECT e.first_name, e.last_name, e.personal_email, e.job_title,
               DAY(e.date_of_birth) AS bday
        FROM employees e
        WHERE MONTH(e.date_of_birth) = ?
          AND e.date_of_birth IS NOT NULL
        ORDER BY DAY(e.date_of_birth) ASC
    ");
    $bdRows->execute([$calMonth]);
    foreach ($bdRows->fetchAll() as $b) {
        $bdMap[(int)$b['bday']][] = $b;
    }
} catch (Exception $e) {}

// ── Calendar math ─────────────────────────────────────────────
$today       = date('Y-m-d');
$todayDay    = (int)date('j');
$daysInMonth = (int)date('t', mktime(0,0,0,$calMonth,1,$calYear));
$firstDow    = (int)date('N', mktime(0,0,0,$calMonth,1,$calYear));
$monthName   = date('F Y', mktime(0,0,0,$calMonth,1,$calYear));
$prevMonth   = $calMonth == 1  ? 12 : $calMonth - 1;
$prevYear    = $calMonth == 1  ? $calYear - 1 : $calYear;
$nextMonth   = $calMonth == 12 ? 1  : $calMonth + 1;
$nextYear    = $calMonth == 12 ? $calYear + 1 : $calYear;
$calBase     = basename($_SERVER['PHP_SELF']);
$isCurrentMonth = ($calYear == date('Y') && $calMonth == date('n'));

function _ct($dt) { return $dt ? date('h:i A', strtotime($dt)) : null; }
function _ch($sec) { if (!$sec) return null; return floor($sec/3600).'h '.str_pad(floor(($sec%3600)/60),2,'0',STR_PAD_LEFT).'m'; }
?>
<style>
/* ── Calendar + Birthday panel wrapper ── */
.cal-outer { display:grid; grid-template-columns:1fr 240px; gap:16px; align-items:start; }
@media(max-width:900px){ .cal-outer { grid-template-columns:1fr; } }

/* ── Calendar card ── */
.cal-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.cal-nav  { display:flex; align-items:center; justify-content:space-between; padding:13px 16px; border-bottom:1px solid var(--border-light); }
.cal-nav-btn { width:28px;height:28px;border-radius:6px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;text-decoration:none;color:var(--muted);transition:background .15s; }
.cal-nav-btn:hover { background:var(--surface-2); color:var(--text); }
.cal-nav-title { font-size:14px; font-weight:800; color:var(--text); }
.cal-dow { display:grid; grid-template-columns:repeat(7,1fr); padding:6px 10px 2px; }
.cal-dow span { text-align:center; font-size:10.5px; font-weight:700; color:#94a3b8; padding:4px 0; }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; padding:4px 10px 12px; }

/* ── Day cells ── */
.cal-cell {
  position:relative; text-align:center; padding:5px 2px;
  border-radius:7px; font-size:12.5px; font-weight:600;
  cursor:default; min-height:30px;
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;
  transition:transform .12s, box-shadow .12s;
}
.cal-cell:hover { transform:scale(1.15); z-index:20; box-shadow:0 4px 12px rgba(0,0,0,.15); }

/* Status colours — professional, not too dark */
.cc-present  { background:#dcfce7; color:#15803d; }
.cc-absent   { background:#fee2e2; color:#dc2626; }
.cc-late     { background:#fef3c7; color:#d97706; }
.cc-remote   { background:#dbeafe; color:#2563eb; }
.cc-holiday  { background:#ede9fe; color:#7c3aed; border:1.5px solid #c4b5fd; }
.cc-today    { background:#4f46e5; color:#fff; box-shadow:0 0 0 2px #a5b4fc; }
.cc-sunday   { color:#cbd5e1; }
.cc-future   { color:#94a3b8; }
.cc-none     { color:#64748b; }

/* Birthday dot on cell */
.bd-dot { width:5px;height:5px;border-radius:50%;background:#ec4899;flex-shrink:0; }

/* ── Tooltip ── */
.cal-tip {
  display:none; position:absolute; bottom:calc(100% + 7px); left:50%;
  transform:translateX(-50%);
  background:#1e293b; color:#f1f5f9;
  font-size:11.5px; font-weight:500; line-height:1.65;
  padding:8px 11px; border-radius:8px;
  white-space:nowrap; z-index:300;
  box-shadow:0 6px 20px rgba(0,0,0,.35);
  pointer-events:none; min-width:150px; text-align:left;
}
.cal-tip::after { content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%); border:5px solid transparent; border-top-color:#1e293b; }
.cal-cell:hover .cal-tip { display:block; }

/* ── Legend ── */
.cal-legend { display:flex; flex-wrap:wrap; gap:8px; padding:10px 14px; border-top:1px solid var(--border-light); background:var(--surface-2); }
.cal-legend-item { display:flex; align-items:center; gap:4px; font-size:11px; color:var(--muted); }
.cal-legend-dot  { width:10px;height:10px;border-radius:3px;flex-shrink:0; }

/* ── Birthday panel ── */
.bd-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.bd-panel-hdr { padding:13px 16px; border-bottom:1px solid var(--border-light); display:flex; align-items:center; gap:8px; }
.bd-panel-hdr h3 { font-size:13.5px; font-weight:700; color:var(--text); }
.bd-panel-body { padding:12px; display:flex; flex-direction:column; gap:8px; max-height:340px; overflow-y:auto; }
.bd-card {
  background:linear-gradient(135deg,#fdf2f8,#fce7f3);
  border:1px solid #fbcfe8; border-radius:10px;
  padding:10px 12px; display:flex; align-items:center; gap:10px;
}
.bd-avatar {
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,#ec4899,#f472b6);
  color:#fff; font-size:14px; font-weight:800;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.bd-info { flex:1; min-width:0; }
.bd-name  { font-size:12.5px; font-weight:700; color:#831843; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bd-meta  { font-size:11px; color:#9d174d; margin-top:1px; }
.bd-day   { font-size:11px; font-weight:700; color:#be185d; }
.wish-btn {
  font-size:11px; font-weight:700;
  background:#ec4899; color:#fff;
  border:none; border-radius:6px;
  padding:4px 8px; cursor:pointer;
  transition:background .15s; white-space:nowrap; flex-shrink:0;
}
.wish-btn:hover { background:#db2777; }
.wish-btn:disabled { opacity:.5; cursor:not-allowed; }
.bd-empty { text-align:center; padding:24px 12px; color:var(--muted); font-size:13px; }

/* Wish toast */
#wishToast {
  position:fixed; bottom:24px; right:24px;
  padding:12px 18px; border-radius:10px;
  font-size:13px; font-weight:600; color:#fff;
  box-shadow:0 4px 16px rgba(0,0,0,.25);
  display:none; z-index:9999; max-width:320px;
  animation:slideUp .2s ease;
}
@keyframes slideUp { from{transform:translateY(10px);opacity:0} to{transform:translateY(0);opacity:1} }
</style>

<div class="cal-outer">

  <!-- ── Calendar ── -->
  <div class="cal-card">
    <div class="cal-nav">
      <a href="<?= $calBase ?>?cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>" class="cal-nav-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <span class="cal-nav-title"><?= $monthName ?></span>
      <a href="<?= $calBase ?>?cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>" class="cal-nav-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
    </div>

    <div class="cal-dow">
      <?php foreach(['M','T','W','T','F','S','S'] as $d): ?>
        <span><?= $d ?></span>
      <?php endforeach; ?>
    </div>

    <div class="cal-grid">
      <?php
      for ($i = 1; $i < $firstDow; $i++) echo '<div class="cal-cell"></div>';

      for ($day = 1; $day <= $daysInMonth; $day++):
          $dateStr  = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
          $dow      = (int)date('N', mktime(0,0,0,$calMonth,$day,$calYear));
          $isSun    = $dow === 7;
          $isToday  = $dateStr === $today;
          $isFuture = $dateStr > $today;
          $isPast   = $dateStr < $today;
          $isHol    = isset($holMap[$dateStr]);
          $isBd     = isset($bdMap[$day]);
          $att      = $attMap[$dateStr] ?? null;
          $status   = $att['status'] ?? null;

          // Determine class — priority: today > holiday > attendance > absent > sunday
          $cls = 'cc-future';
          $tipLines = [];

          if ($isToday)  { $cls = 'cc-today'; $tipLines[] = '📅 Today'; }
          if ($isHol)    {
              if (!$isToday) $cls = 'cc-holiday';
              $h = $holMap[$dateStr];
              $tipLines[] = '🎉 '.$h['title'];
              if ($h['description']) $tipLines[] = $h['description'];
          }
          if ($att && !$isHol && !$isToday) {
              if ($status==='present')  $cls = 'cc-present';
              elseif ($status==='late') $cls = 'cc-late';
              elseif ($status==='remote') $cls = 'cc-remote';
              elseif ($status==='absent') $cls = 'cc-absent';
          }
          if ($isPast && !$isSun && !$isHol && !$att && !$isToday) {
              // Only mark absent if user has at least one attendance record
              if (!empty($attMap)) $cls = 'cc-absent';
              else $cls = 'cc-none';
          }
          if ($isSun && $cls === 'cc-future') $cls = 'cc-sunday';

          // Tooltip lines for attendance
          if ($att) {
              $ci = _ct($att['clock_in']); $co = _ct($att['clock_out']); $wh = _ch($att['work_seconds']);
              if ($ci) $tipLines[] = '⏰ In: '.$ci;
              if ($co) $tipLines[] = '🚪 Out: '.$co;
              if ($wh) $tipLines[] = '⏱ '.$wh;
              if (!$ci && $status==='absent') $tipLines[] = '❌ Absent';
          } elseif ($isPast && !$isSun && !$isHol) {
              $tipLines[] = '❌ No record';
          }

          $tipHtml = '';
          if (!empty($tipLines)) {
              $tipHtml = '<div class="cal-tip">'.implode('<br>', array_map('htmlspecialchars', $tipLines)).'</div>';
          }

          // Birthday dot
          $bdDot = $isBd ? '<span class="bd-dot"></span>' : '';

          echo '<div class="cal-cell '.$cls.'">'.$day.$bdDot.$tipHtml.'</div>';
      endfor;
      ?>
    </div>

    <!-- Legend -->
    <div class="cal-legend">
      <?php
      $leg = [
          ['#dcfce7','#15803d','Present'],
          ['#fee2e2','#dc2626','Absent'],
          ['#fef3c7','#d97706','Late'],
          ['#dbeafe','#2563eb','Remote'],
          ['#ede9fe','#7c3aed','Holiday'],
          ['#4f46e5','#fff','Today'],
      ];
      foreach ($leg as [$bg,$c,$l]):
      ?>
      <div class="cal-legend-item">
        <span class="cal-legend-dot" style="background:<?= $bg ?>;border:1px solid <?= $c ?>20;"></span><?= $l ?>
      </div>
      <?php endforeach; ?>
      <div class="cal-legend-item">
        <span class="bd-dot" style="display:inline-block;"></span> Birthday
      </div>
    </div>
  </div>

  <!-- ── Birthday Panel ── -->
  <div class="bd-panel">
    <div class="bd-panel-hdr">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <h3>Birthdays in <?= date('F', mktime(0,0,0,$calMonth,1,$calYear)) ?></h3>
    </div>
    <div class="bd-panel-body">
      <?php if (empty($bdMap)): ?>
        <div class="bd-empty">🎂<br>No birthdays this month</div>
      <?php else:
        // Flatten and sort by day
        $allBd = [];
        foreach ($bdMap as $day => $people) {
            foreach ($people as $p) $allBd[] = array_merge($p, ['bday' => $day]);
        }
        usort($allBd, fn($a,$b) => $a['bday'] <=> $b['bday']);
        foreach ($allBd as $b):
            $bdDate   = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $b['bday']);
            $isToday  = $bdDate === $today;
            $dayLabel = $isToday ? '🎉 Today!' : date('jS', mktime(0,0,0,$calMonth,$b['bday'],$calYear));
      ?>
      <div class="bd-card" style="<?= $isToday?'border-color:#f9a8d4;background:linear-gradient(135deg,#fce7f3,#fdf4ff);':'' ?>">
        <div class="bd-avatar"><?= strtoupper(substr($b['first_name'],0,1)) ?></div>
        <div class="bd-info">
          <div class="bd-name"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
          <div class="bd-meta"><?= htmlspecialchars($b['job_title'] ?: 'Employee') ?></div>
          <div class="bd-day"><?= $dayLabel ?></div>
        </div>
        <?php if (!empty($b['personal_email'])): ?>
        <button class="wish-btn" id="wb_<?= $b['bday'].'_'.md5($b['personal_email']) ?>"
          onclick="sendWish(this,'<?= addslashes($b['first_name'].' '.$b['last_name']) ?>','<?= addslashes($b['personal_email']) ?>')">
          🎁 Wish
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div><!-- /cal-outer -->

<div id="wishToast"></div>

<script>
function sendWish(btn, name, email) {
  btn.disabled = true;
  btn.textContent = '⏳';
  fetch('../shared/send_wish.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'name='+encodeURIComponent(name)+'&email='+encodeURIComponent(email)
  })
  .then(r=>r.json())
  .then(d=>{
    showWishToast(d.ok?'🎂 Wish sent to '+name+'!':'❌ '+d.msg, d.ok);
    btn.textContent = d.ok ? '✓ Sent' : '🎁 Wish';
    if (!d.ok) btn.disabled = false;
  })
  .catch(()=>{ showWishToast('❌ Network error',false); btn.textContent='🎁 Wish'; btn.disabled=false; });
}
function showWishToast(msg, ok) {
  const t = document.getElementById('wishToast');
  t.textContent = msg;
  t.style.background = ok ? '#15803d' : '#dc2626';
  t.style.display = 'block';
  setTimeout(()=>t.style.display='none', 4000);
}
</script>
