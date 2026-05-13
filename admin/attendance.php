<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Filters ──────────────────────────────────────────────────
$filterFrom = $_GET['from']    ?? date('Y-m-01');          // default: 1st of current month
$filterTo   = $_GET['to']      ?? date('Y-m-d');           // default: today
$filterUser = (int)($_GET['user_id'] ?? 0);
$filterRole = $_GET['role']    ?? '';
$export     = isset($_GET['export']);

// Clamp dates
if ($filterFrom > $filterTo) $filterFrom = $filterTo;

$logs    = [];
$users   = [];
$allRows = [];   // final rows including absent fill-in
$dbError = null;

if (!function_exists('fmtTime')) {
    function fmtTime($dt) { return $dt ? date('h:i A', strtotime($dt)) : '—'; }
}
if (!function_exists('fmtHrs')) {
    function fmtHrs($sec) {
        if (!$sec) return '—';
        return sprintf('%dh %02dm', floor($sec/3600), floor(($sec%3600)/60));
    }
}

try {
    $users = $db->query("SELECT id, name, email, role FROM users WHERE role IN ('employee','manager') ORDER BY name")->fetchAll();

    // Build query
    $where  = ["al.log_date BETWEEN ? AND ?"];
    $params = [$filterFrom, $filterTo];
    if ($filterUser > 0) { $where[] = "al.user_id = ?"; $params[] = $filterUser; }
    if ($filterRole)     { $where[] = "u.role = ?";     $params[] = $filterRole; }

    $stmt = $db->prepare("
        SELECT al.*, u.name AS user_name, u.role AS user_role,
               loc.name AS location_name
        FROM attendance_logs al
        JOIN users u ON al.user_id = u.id
        LEFT JOIN attendance_locations loc ON al.location_id = loc.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY al.log_date ASC, u.name ASC
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // ── Fill absent days ─────────────────────────────────────
    // Build a set of (user_id, log_date) that exist
    $existing = [];
    foreach ($logs as $l) $existing[$l['user_id'].'_'.$l['log_date']] = true;

    // Determine which users to fill
    $fillUsers = [];
    if ($filterUser > 0) {
        foreach ($users as $u) { if ($u['id'] === $filterUser) { $fillUsers[] = $u; break; } }
    } elseif ($filterRole) {
        foreach ($users as $u) { if ($u['role'] === $filterRole) $fillUsers[] = $u; }
    } else {
        $fillUsers = $users;
    }

    // Generate absent rows for every working day (Mon–Sat) with no log
    $absentRows = [];
    $d = new DateTime($filterFrom);
    $end = new DateTime($filterTo);
    while ($d <= $end) {
        $dow  = (int)$d->format('N'); // 1=Mon … 7=Sun
        $date = $d->format('Y-m-d');
        if ($dow <= 6) { // Mon–Sat
            foreach ($fillUsers as $u) {
                $key = $u['id'].'_'.$date;
                if (!isset($existing[$key])) {
                    $absentRows[] = [
                        'id'            => null,
                        'user_id'       => $u['id'],
                        'user_name'     => $u['name'],
                        'user_role'     => $u['role'],
                        'log_date'      => $date,
                        'clock_in'      => null,
                        'clock_out'     => null,
                        'work_seconds'  => 0,
                        'status'        => 'absent',
                        'location_name' => '—',
                    ];
                }
            }
        }
        $d->modify('+1 day');
    }

    // Merge and sort
    $allRows = array_merge($logs, $absentRows);
    usort($allRows, fn($a,$b) => $a['log_date'] <=> $b['log_date'] ?: strcmp($a['user_name'], $b['user_name']));

} catch (PDOException $e) {
    $dbError = $e->getMessage();
    error_log('Admin attendance error: ' . $e->getMessage());
}

// ── Export CSV ───────────────────────────────────────────────
if ($export && empty($dbError)) {
    $fname = 'attendance_' . $filterFrom . '_to_' . $filterTo . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM so Excel opens correctly
    fputs($out, "\xEF\xBB\xBF");

    // Group allRows by user
    $byUser = [];
    foreach ($allRows as $r) {
        $byUser[$r['user_id']][] = $r;
    }

    foreach ($byUser as $uid => $rows) {
        $uName = $rows[0]['user_name'];
        $uRole = ucfirst($rows[0]['user_role']);

        // Employee header block
        fputcsv($out, ['Employee', $uName, 'Role', $uRole]);
        fputcsv($out, ['Period', $filterFrom . ' to ' . $filterTo]);
        fputcsv($out, []); // blank line

        // Column headers
        fputcsv($out, ['Date', 'Day', 'Clock In', 'Clock Out', 'Work Hours', 'Status', 'Location']);

        $totalSec     = 0;
        $presentDays  = 0;
        $absentDays   = 0;
        $lateDays     = 0;
        $shortDays    = 0;

        foreach ($rows as $r) {
            $sec    = (int)($r['work_seconds'] ?? 0);
            $status = $r['status'];
            $isAbs  = $status === 'absent';

            // Work hours as plain text — no special chars
            if ($sec > 0) {
                $wh = floor($sec/3600) . 'h ' . str_pad(floor(($sec%3600)/60), 2, '0', STR_PAD_LEFT) . 'm';
            } else {
                $wh = $isAbs ? '' : '0h 00m';
            }

            // Clock times — plain empty string for absent/missing
            $ci = ($r['clock_in']  && !$isAbs) ? date('h:i A', strtotime($r['clock_in']))  : '';
            $co = ($r['clock_out'] && !$isAbs) ? date('h:i A', strtotime($r['clock_out'])) : '';

            fputcsv($out, [
                date('d-M-Y', strtotime($r['log_date'])),
                date('D',     strtotime($r['log_date'])),
                $ci,
                $co,
                $wh,
                ucfirst(str_replace('_', ' ', $status)),
                $isAbs ? '' : ($r['location_name'] ?? ''),
            ]);

            $totalSec += $sec;
            if (in_array($status, ['present','remote'])) $presentDays++;
            if ($status === 'absent')   $absentDays++;
            if ($status === 'late')     { $presentDays++; $lateDays++; }
            if ($sec > 0 && $sec < 32400) $shortDays++;
        }

        // Summary row
        $totalH = floor($totalSec/3600);
        $totalM = str_pad(floor(($totalSec%3600)/60), 2, '0', STR_PAD_LEFT);
        fputcsv($out, []); // blank
        fputcsv($out, [
            'SUMMARY',
            'Present: ' . $presentDays . ' days',
            'Absent: '  . $absentDays  . ' days',
            'Late: '    . $lateDays    . ' days',
            'Short (<9h): ' . $shortDays . ' days',
            'Total Hours: ' . $totalH . 'h ' . $totalM . 'm',
            '',
        ]);
        fputcsv($out, []); // blank separator between employees
        fputcsv($out, []); // extra blank
    }

    fclose($out); exit;
}

$statusMap = ['present'=>'badge-green','remote'=>'badge-blue','half_day'=>'badge-yellow','late'=>'badge-yellow','absent'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attendance – HRMS Portal</title>
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
      <span class="page-title">Attendance</span>
      <span class="page-breadcrumb">Logs &amp; Reports</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?>
      <div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if($dbError): ?>
      <div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Database error: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <div class="page-header" style="margin-bottom:20px;">
      <div class="page-header-text">
        <h1>Attendance Logs</h1>
        <p>Filter by date range and employee. Absent days are auto-filled for working days (Mon–Sat).</p>
      </div>
      <div class="page-header-actions">
        <a href="locations.php" class="btn btn-secondary">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Manage Locations
        </a>
      </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body" style="padding:16px 20px;">
        <form method="GET" id="filterForm" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
          <div class="form-group" style="margin:0;min-width:150px;">
            <label style="font-size:12px;font-weight:600;">From Date</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filterFrom) ?>">
          </div>
          <div class="form-group" style="margin:0;min-width:150px;">
            <label style="font-size:12px;font-weight:600;">To Date</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filterTo) ?>">
          </div>
          <div class="form-group" style="margin:0;min-width:210px;">
            <label style="font-size:12px;font-weight:600;">Employee / Manager</label>
            <select name="user_id" class="form-control">
              <option value="">All Users</option>
              <?php foreach($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filterUser==$u['id']?'selected':'' ?>>
                  <?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0;min-width:130px;">
            <label style="font-size:12px;font-weight:600;">Role</label>
            <select name="role" class="form-control">
              <option value="">All Roles</option>
              <option value="employee" <?= $filterRole==='employee'?'selected':'' ?>>Employee</option>
              <option value="manager"  <?= $filterRole==='manager' ?'selected':'' ?>>Manager</option>
            </select>
          </div>
          <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="submit" class="btn btn-primary">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              Filter
            </button>
            <a href="attendance.php" class="btn btn-secondary">Reset</a>
            <a href="attendance.php?from=<?= urlencode($filterFrom) ?>&to=<?= urlencode($filterTo) ?>&user_id=<?= $filterUser ?>&role=<?= urlencode($filterRole) ?>&export=1"
               class="btn btn-success">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export CSV
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Quick date range shortcuts -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
      <?php
      $shortcuts = [
        'Today'        => [date('Y-m-d'), date('Y-m-d')],
        'This Week'    => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
        'This Month'   => [date('Y-m-01'), date('Y-m-d')],
        'Last Month'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
      ];
      foreach ($shortcuts as $label => [$f, $t]):
      ?>
        <a href="attendance.php?from=<?= $f ?>&to=<?= $t ?>&user_id=<?= $filterUser ?>&role=<?= urlencode($filterRole) ?>"
           class="btn btn-ghost btn-sm <?= ($filterFrom===$f && $filterTo===$t)?'active':'' ?>"
           style="<?= ($filterFrom===$f && $filterTo===$t)?'background:var(--brand-light);color:var(--brand);border-color:var(--brand);':'' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Stats summary -->
    <?php
    $totalRows    = count($allRows);
    $presentCount = count(array_filter($allRows, fn($r) => in_array($r['status'], ['present','remote','late'])));
    $absentCount  = count(array_filter($allRows, fn($r) => $r['status'] === 'absent'));
    $lateCount    = count(array_filter($allRows, fn($r) => $r['status'] === 'late'));
    $totalSec     = array_sum(array_column($allRows, 'work_seconds'));
    ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= $totalRows ?></div><div class="stat-label">Total Records</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= $presentCount ?></div><div class="stat-label">Present Days</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--red-bg);color:var(--red);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= $absentCount ?></div><div class="stat-label">Absent Days</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= fmtHrs($totalSec) ?></div><div class="stat-label">Total Hours</div></div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Attendance Records
          <span style="font-weight:400;color:var(--muted);font-size:13px;">
            (<?= $filterFrom ?> → <?= $filterTo ?>  ·  <?= count($allRows) ?> records)
          </span>
        </h2>
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="attSearch" placeholder="Search name…" oninput="filterAtt(this.value)">
        </div>
      </div>
      <table id="attTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Employee</th>
            <th>Role</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Work Hours</th>
            <th>Status</th>
            <th>Location</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($allRows)): ?>
            <tr class="empty-row"><td colspan="8">No records for this period.</td></tr>
          <?php else: foreach($allRows as $r):
            $hrs   = $r['work_seconds'] ?? 0;
            $short = $hrs > 0 && $hrs < 32400;
            $isAbsent = $r['status'] === 'absent';
          ?>
          <tr style="<?= $isAbsent ? 'background:#fff8f8;' : '' ?>">
            <td class="text-sm font-semibold"><?= date('D, d M Y', strtotime($r['log_date'])) ?></td>
            <td>
              <div class="td-user">
                <div class="td-avatar" style="<?= $isAbsent?'background:var(--red-bg);color:var(--red);':'' ?>">
                  <?= strtoupper(substr($r['user_name'],0,1)) ?>
                </div>
                <div class="td-name"><?= htmlspecialchars($r['user_name']) ?></div>
              </div>
            </td>
            <td><span class="badge badge-gray"><?= ucfirst($r['user_role']) ?></span></td>
            <td class="text-sm"><?= fmtTime($r['clock_in']) ?></td>
            <td class="text-sm"><?= fmtTime($r['clock_out']) ?></td>
            <td>
              <?php if($isAbsent): ?>
                <span style="font-size:12.5px;color:var(--muted-light);">—</span>
              <?php else: ?>
                <span style="font-size:13px;font-weight:600;color:<?= $short?'var(--red)':'var(--green)' ?>;">
                  <?= fmtHrs($hrs) ?>
                </span>
                <?php if($short && $hrs > 0): ?>
                  <span style="font-size:11px;color:var(--red);margin-left:3px;">(&lt;9h)</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $statusMap[$r['status']] ?? 'badge-gray' ?>">
                <?= ucfirst(str_replace('_',' ',$r['status'])) ?>
              </span>
            </td>
            <td class="text-muted text-sm"><?= htmlspecialchars($r['location_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>

<script>
function filterAtt(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#attTable tbody tr:not(.empty-row)').forEach(r => {
    r.style.display = !q || r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
