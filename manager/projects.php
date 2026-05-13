<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
$db  = getDB();
$uid = $_SESSION['user_id'];

// Fetch projects assigned to this manager
$filterStatus = $_GET['status'] ?? '';
$where  = ["p.manager_id = ?"];
$params = [$uid];
if ($filterStatus) { $where[] = "p.status = ?"; $params[] = $filterStatus; }

$projects = $db->prepare("
    SELECT p.*
    FROM projects p
    WHERE " . implode(' AND ', $where) . "
    ORDER BY
        FIELD(p.status,'Active','Planning','On Hold','Completed','Cancelled'),
        p.deadline_date ASC
");
$projects->execute($params);
$projects = $projects->fetchAll();

// Counts for this manager
$counts = [];
foreach (['Planning','Active','On Hold','Completed','Cancelled'] as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM projects WHERE manager_id=? AND status=?");
    $c->execute([$uid, $s]); $counts[$s] = (int)$c->fetchColumn();
}
$total = array_sum($counts);

$priorityBadge = ['Low'=>'badge-gray','Medium'=>'badge-blue','High'=>'badge-yellow','Critical'=>'badge-red'];
$statusBadge   = ['Planning'=>'badge-gray','Active'=>'badge-green','On Hold'=>'badge-yellow','Completed'=>'badge-blue','Cancelled'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Projects – HRMS Portal</title>
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
      <span class="page-title">My Projects</span>
      <span class="page-breadcrumb">Projects assigned to you</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Manager</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <div class="page-header">
      <div class="page-header-text">
        <h1>My Projects</h1>
        <p>Projects assigned to you by admin. <?= $total ?> total project<?= $total!==1?'s':'' ?>.</p>
      </div>
    </div>

    <!-- Stats -->
    <?php if($total > 0): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
      <?php foreach(['Planning'=>'badge-gray','Active'=>'badge-green','On Hold'=>'badge-yellow','Completed'=>'badge-blue','Cancelled'=>'badge-red'] as $s=>$b): ?>
      <a href="projects.php?status=<?= urlencode($s) ?>" style="text-decoration:none;">
        <div class="stat-card" style="<?= $filterStatus===$s?'border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-light);':'' ?>">
          <div class="stat-body"><div class="stat-value"><?= $counts[$s] ?></div><div class="stat-label"><?= $s ?></div></div>
          <span class="badge <?= $b ?>" style="align-self:flex-start;"><?= $s ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
      <a href="projects.php" class="btn btn-sm <?= !$filterStatus?'btn-primary':'btn-ghost' ?>">All (<?= $total ?>)</a>
      <?php foreach(array_keys($counts) as $s): if(!$counts[$s]) continue; ?>
        <a href="projects.php?status=<?= urlencode($s) ?>" class="btn btn-sm <?= $filterStatus===$s?'btn-primary':'btn-ghost' ?>"><?= $s ?> (<?= $counts[$s] ?>)</a>
      <?php endforeach; ?>
      <div style="margin-left:auto;">
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="pSearch" placeholder="Search projects…" oninput="filterP(this.value)">
        </div>
      </div>
    </div>

    <?php if(empty($projects)): ?>
      <div class="card">
        <div class="card-body" style="text-align:center;padding:60px 20px;">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--muted-light)" stroke-width="1.5" style="display:block;margin:0 auto 16px;"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
          <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">No projects assigned</div>
          <div style="font-size:13.5px;color:var(--muted);">Projects assigned to you by admin will appear here.</div>
        </div>
      </div>
    <?php else: ?>

    <!-- Project cards -->
    <div id="pGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;">
      <?php foreach($projects as $p):
        $overdue    = $p['status']==='Active' && $p['deadline_date'] < date('Y-m-d');
        $daysLeft   = (int)ceil((strtotime($p['deadline_date']) - time()) / 86400);
        $estCost    = $p['total_hours'] * $p['hr_rate'];
        $progress   = 0;
        if ($p['status']==='Completed') $progress = 100;
        elseif ($p['status']==='Active') {
            $total_d = max(1, (strtotime($p['deadline_date']) - strtotime($p['start_date'])) / 86400);
            $elapsed = max(0, (time() - strtotime($p['start_date'])) / 86400);
            $progress = min(95, round(($elapsed / $total_d) * 100));
        }
      ?>
      <div class="card p-card" data-name="<?= htmlspecialchars(strtolower($p['project_name'].' '.$p['project_code'].' '.($p['client_name']??''))) ?>"
           style="<?= $overdue?'border-color:#fca5a5;':'' ?>">
        <div style="padding:18px 20px;">

          <!-- Header -->
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px;">
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <code style="font-size:11px;background:var(--surface-2);padding:2px 7px;border-radius:5px;color:var(--muted);flex-shrink:0;"><?= htmlspecialchars($p['project_code']) ?></code>
                <span class="badge <?= $priorityBadge[$p['priority']]??'badge-gray' ?>" style="font-size:10.5px;"><?= $p['priority'] ?></span>
              </div>
              <div style="font-size:15px;font-weight:800;color:var(--text);line-height:1.3;"><?= htmlspecialchars($p['project_name']) ?></div>
              <?php if($p['client_name']): ?>
                <div style="font-size:12.5px;color:var(--muted);margin-top:2px;">Client: <?= htmlspecialchars($p['client_name']) ?></div>
              <?php endif; ?>
            </div>
            <span class="badge <?= $statusBadge[$p['status']]??'badge-gray' ?>"><?= $p['status'] ?></span>
          </div>

          <!-- Progress bar -->
          <?php if(in_array($p['status'],['Active','Completed'])): ?>
          <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--muted);margin-bottom:4px;">
              <span>Timeline Progress</span><span><?= $progress ?>%</span>
            </div>
            <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden;">
              <div style="height:100%;width:<?= $progress ?>%;background:<?= $p['status']==='Completed'?'var(--green)':($overdue?'var(--red)':'var(--brand)') ?>;border-radius:3px;transition:width .3s;"></div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Meta grid -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
            <div style="background:var(--surface-2);border-radius:8px;padding:10px 12px;">
              <div style="font-size:10.5px;font-weight:700;color:var(--muted-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px;">Start</div>
              <div style="font-size:13px;font-weight:600;color:var(--text);"><?= date('d M Y', strtotime($p['start_date'])) ?></div>
            </div>
            <div style="background:<?= $overdue?'var(--red-bg)':'var(--surface-2)' ?>;border-radius:8px;padding:10px 12px;">
              <div style="font-size:10.5px;font-weight:700;color:var(--muted-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px;">Deadline</div>
              <div style="font-size:13px;font-weight:600;color:<?= $overdue?'var(--red)':'var(--text)' ?>;">
                <?= date('d M Y', strtotime($p['deadline_date'])) ?>
                <?php if($overdue): ?>
                  <span style="font-size:11px;display:block;color:var(--red);">⚠ Overdue</span>
                <?php elseif($p['status']==='Active' && $daysLeft >= 0): ?>
                  <span style="font-size:11px;display:block;color:<?= $daysLeft<=7?'var(--yellow)':'var(--muted)' ?>;"><?= $daysLeft ?> day<?= $daysLeft!==1?'s':'' ?> left</span>
                <?php endif; ?>
              </div>
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:10px 12px;">
              <div style="font-size:10.5px;font-weight:700;color:var(--muted-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px;">Working Hours</div>
              <div style="font-size:13px;font-weight:700;color:var(--brand);"><?= number_format($p['total_hours'],1) ?> hrs</div>
            </div>
            <div style="background:var(--surface-2);border-radius:8px;padding:10px 12px;">
              <div style="font-size:10.5px;font-weight:700;color:var(--muted-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px;">Est. Cost</div>
              <div style="font-size:13px;font-weight:700;color:var(--green-text);">
                <?= $estCost > 0 ? '₹'.number_format($estCost,0) : '—' ?>
              </div>
            </div>
          </div>

          <?php if($p['description']): ?>
          <div style="font-size:12.5px;color:var(--muted);line-height:1.5;border-top:1px solid var(--border-light);padding-top:10px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
            <?= htmlspecialchars($p['description']) ?>
          </div>
          <?php endif; ?>

        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>

  </div>
</div>
</div>

<script>
function filterP(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.p-card').forEach(c => {
    c.style.display = !q || c.dataset.name.includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
