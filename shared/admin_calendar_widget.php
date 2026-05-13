<?php
/**
 * shared/admin_calendar_widget.php
 * Admin calendar — shows holidays + birthdays (no attendance tracking)
 * Requires: $db (PDO), $calYear (int), $calMonth (int)
 */

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
        SELECT e.first_name, e.last_name, e.personal_email, e.job_title,
               d.name AS department, DAY(e.date_of_birth) AS bday
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE MONTH(e.date_of_birth) = ?
          AND e.date_of_birth IS NOT NULL
        ORDER BY DAY(e.date_of_birth) ASC
    ");
    $bdRows->execute([$calMonth]);
    foreach ($bdRows->fetchAll() as $b) $bdMap[(int)$b['bday']][] = $b;
} catch (Exception $e) {}

// ── Calendar math ─────────────────────────────────────────────
$today       = date('Y-m-d');
$daysInMonth = (int)date('t', mktime(0,0,0,$calMonth,1,$calYear));
$firstDow    = (int)date('N', mktime(0,0,0,$calMonth,1,$calYear));
$monthName   = date('F Y', mktime(0,0,0,$calMonth,1,$calYear));
$prevMonth   = $calMonth == 1  ? 12 : $calMonth - 1;
$prevYear    = $calMonth == 1  ? $calYear - 1 : $calYear;
$nextMonth   = $calMonth == 12 ? 1  : $calMonth + 1;
$nextYear    = $calMonth == 12 ? $calYear + 1 : $calYear;
$calBase     = basename($_SERVER['PHP_SELF']);
?>
<style>
.acal-outer { display:grid; grid-template-columns:1fr 260px; gap:16px; align-items:start; }
@media(max-width:960px){ .acal-outer { grid-template-columns:1fr; } }

.acal-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.acal-nav  { display:flex; align-items:center; justify-content:space-between; padding:13px 16px; border-bottom:1px solid var(--border-light); }
.acal-nav-btn { width:28px;height:28px;border-radius:6px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;text-decoration:none;color:var(--muted);transition:background .15s; }
.acal-nav-btn:hover { background:var(--surface-2); color:var(--text); }
.acal-dow { display:grid; grid-template-columns:repeat(7,1fr); padding:6px 10px 2px; }
.acal-dow span { text-align:center; font-size:10.5px; font-weight:700; color:#94a3b8; padding:4px 0; }
.acal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; padding:4px 10px 12px; }

.acal-cell {
  position:relative; text-align:center; padding:6px 2px;
  border-radius:7px; font-size:12.5px; font-weight:600;
  cursor:default; min-height:32px;
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;
  transition:transform .12s, box-shadow .12s;
  color:#64748b;
}
.acal-cell:hover { transform:scale(1.12); z-index:20; box-shadow:0 4px 12px rgba(0,0,0,.12); }

.acc-today   { background:#4f46e5; color:#fff; box-shadow:0 0 0 2px #a5b4fc; }
.acc-holiday { background:#ede9fe; color:#7c3aed; border:1.5px solid #c4b5fd; }
.acc-sunday  { color:#cbd5e1; }
.acc-normal  { color:#374151; }

.acal-bd-dot { width:5px;height:5px;border-radius:50%;background:#ec4899;flex-shrink:0; }

/* Tooltip */
.acal-tip {
  display:none; position:absolute; bottom:calc(100% + 7px); left:50%;
  transform:translateX(-50%);
  background:#1e293b; color:#f1f5f9;
  font-size:11.5px; font-weight:500; line-height:1.65;
  padding:8px 11px; border-radius:8px;
  white-space:nowrap; z-index:300;
  box-shadow:0 6px 20px rgba(0,0,0,.3);
  pointer-events:none; min-width:150px; text-align:left;
}
.acal-tip::after { content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%); border:5px solid transparent; border-top-color:#1e293b; }
.acal-cell:hover .acal-tip { display:block; }

/* Legend */
.acal-legend { display:flex; flex-wrap:wrap; gap:8px; padding:10px 14px; border-top:1px solid var(--border-light); background:var(--surface-2); }
.acal-legend-item { display:flex; align-items:center; gap:4px; font-size:11px; color:var(--muted); }
.acal-legend-dot  { width:10px;height:10px;border-radius:3px;flex-shrink:0; }

/* Birthday panel */
.abd-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.abd-panel-hdr { padding:13px 16px; border-bottom:1px solid var(--border-light); display:flex; align-items:center; gap:8px; }
.abd-panel-hdr h3 { font-size:13.5px; font-weight:700; color:var(--text); }
.abd-panel-body { padding:12px; display:flex; flex-direction:column; gap:8px; max-height:360px; overflow-y:auto; }
.abd-card {
  background:linear-gradient(135deg,#fdf2f8,#fce7f3);
  border:1px solid #fbcfe8; border-radius:10px;
  padding:10px 12px; display:flex; align-items:center; gap:10px;
}
.abd-card.today-bd { background:linear-gradient(135deg,#fce7f3,#fdf4ff); border-color:#f9a8d4; }
.abd-avatar { width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#ec4899,#f472b6);color:#fff;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.abd-info { flex:1; min-width:0; }
.abd-name { font-size:12.5px; font-weight:700; color:#831843; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.abd-meta { font-size:11px; color:#9d174d; margin-top:1px; }
.abd-day  { font-size:11px; font-weight:700; color:#be185d; }
.abd-wish-btn { font-size:11px;font-weight:700;background:#ec4899;color:#fff;border:none;border-radius:6px;padding:4px 8px;cursor:pointer;transition:background .15s;white-space:nowrap;flex-shrink:0; }
.abd-wish-btn:hover { background:#db2777; }
.abd-wish-btn:disabled { opacity:.5; cursor:not-allowed; }
.abd-empty { text-align:center; padding:24px 12px; color:var(--muted); font-size:13px; }

#adminWishToast { position:fixed;bottom:24px;right:24px;padding:12px 18px;border-radius:10px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.25);display:none;z-index:9999;max-width:320px; }
</style>

<div class="acal-outer">

  <!-- ── Calendar ── -->
  <div class="acal-card">
    <div class="acal-nav">
      <a href="<?= $calBase ?>?cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>" class="acal-nav-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <span style="font-size:14px;font-weight:800;color:var(--text);"><?= $monthName ?></span>
      <a href="<?= $calBase ?>?cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>" class="acal-nav-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
    </div>

    <div class="acal-dow">
      <?php foreach(['M','T','W','T','F','S','S'] as $d): ?>
        <span><?= $d ?></span>
      <?php endforeach; ?>
    </div>

    <div class="acal-grid">
      <?php
      for ($i = 1; $i < $firstDow; $i++) echo '<div class="acal-cell"></div>';

      for ($day = 1; $day <= $daysInMonth; $day++):
          $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
          $dow     = (int)date('N', mktime(0,0,0,$calMonth,$day,$calYear));
          $isSun   = $dow === 7;
          $isToday = $dateStr === $today;
          $isHol   = isset($holMap[$dateStr]);
          $isBd    = isset($bdMap[$day]);

          $cls = $isSun ? 'acc-sunday' : 'acc-normal';
          if ($isHol)  $cls = 'acc-holiday';
          if ($isToday) $cls = 'acc-today';

          $tipLines = [];
          if ($isToday) $tipLines[] = '📅 Today';
          if ($isHol) {
              $h = $holMap[$dateStr];
              $tipLines[] = '🎉 '.$h['title'];
              if ($h['description']) $tipLines[] = $h['description'];
          }
          if ($isBd) {
              foreach ($bdMap[$day] as $b) {
                  $tipLines[] = '🎂 '.htmlspecialchars($b['first_name'].' '.$b['last_name']);
              }
          }

          $tipHtml = '';
          if (!empty($tipLines)) {
              $tipHtml = '<div class="acal-tip">'.implode('<br>', array_map('htmlspecialchars', $tipLines)).'</div>';
          }

          $bdDot = $isBd ? '<span class="acal-bd-dot"></span>' : '';
          echo '<div class="acal-cell '.$cls.'">'.$day.$bdDot.$tipHtml.'</div>';
      endfor;
      ?>
    </div>

    <!-- Legend -->
    <div class="acal-legend">
      <div class="acal-legend-item"><span class="acal-legend-dot" style="background:#ede9fe;border:1px solid #c4b5fd;"></span>Holiday</div>
      <div class="acal-legend-item"><span class="acal-legend-dot" style="background:#4f46e5;"></span>Today</div>
      <div class="acal-legend-item"><span class="acal-bd-dot" style="display:inline-block;"></span>Birthday</div>
    </div>
  </div>

  <!-- ── Birthday Panel ── -->
  <div class="abd-panel">
    <div class="abd-panel-hdr">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <h3>Birthdays — <?= date('F', mktime(0,0,0,$calMonth,1,$calYear)) ?></h3>
    </div>
    <div class="abd-panel-body">
      <?php if (empty($bdMap)): ?>
        <div class="abd-empty">🎂<br>No birthdays this month</div>
      <?php else:
        $allBd = [];
        foreach ($bdMap as $day => $people) foreach ($people as $p) $allBd[] = array_merge($p, ['bday'=>$day]);
        usort($allBd, fn($a,$b) => $a['bday'] <=> $b['bday']);
        foreach ($allBd as $b):
            $bdDate  = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $b['bday']);
            $isToday = $bdDate === $today;
            $dayLbl  = $isToday ? '🎉 Today!' : date('jS', mktime(0,0,0,$calMonth,$b['bday'],$calYear));
      ?>
      <div class="abd-card <?= $isToday?'today-bd':'' ?>">
        <div class="abd-avatar"><?= strtoupper(substr($b['first_name'],0,1)) ?></div>
        <div class="abd-info">
          <div class="abd-name"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
          <div class="abd-meta"><?= htmlspecialchars($b['department'] ?: ($b['job_title'] ?: 'Employee')) ?></div>
          <div class="abd-day"><?= $dayLbl ?></div>
        </div>
        <?php if (!empty($b['personal_email'])): ?>
        <button class="abd-wish-btn"
          onclick="adminSendWish(this,'<?= addslashes($b['first_name'].' '.$b['last_name']) ?>','<?= addslashes($b['personal_email']) ?>')">
          🎁 Wish
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div><!-- /acal-outer -->

<div id="adminWishToast"></div>

<script>
function adminSendWish(btn, name, email) {
  btn.disabled = true; btn.textContent = '⏳';
  fetch('../shared/send_wish.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'name='+encodeURIComponent(name)+'&email='+encodeURIComponent(email)
  })
  .then(r=>r.json())
  .then(d=>{
    const t = document.getElementById('adminWishToast');
    t.textContent = d.ok ? '🎂 Wish sent to '+name+'!' : '❌ '+d.msg;
    t.style.background = d.ok ? '#15803d' : '#dc2626';
    t.style.display = 'block';
    setTimeout(()=>t.style.display='none', 4000);
    btn.textContent = d.ok ? '✓ Sent' : '🎁 Wish';
    if (!d.ok) btn.disabled = false;
  })
  .catch(()=>{ btn.textContent='🎁 Wish'; btn.disabled=false; });
}
</script>
