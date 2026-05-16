<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('employee');
$db  = getDB();
$uid = $_SESSION['user_id'];

// My ACL requests (auto-generated when clocking out on weekends/holidays)
$requests = $db->prepare("SELECT * FROM acl_requests WHERE user_id=? ORDER BY created_at DESC");
$requests->execute([$uid]);
$requests = $requests->fetchAll();

$pendingCount = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
$approvedHrs = array_sum(array_map(fn($r) => $r['status']==='approved' ? (float)$r['hours'] : 0, $requests));

$statusMap = ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ACL Requests – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left"><span class="page-title">ACL Requests</span><span class="page-breadcrumb">Auto-generated when you work on weekends/holidays</span></div>
    <div class="topbar-right"><span class="role-chip">Employee</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">

    <!-- Info -->
    <div style="background:var(--brand-light);border:1px solid #c7d2fe;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--brand)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      <span style="font-size:13px;color:var(--brand);font-weight:500;">ACL requests are automatically created when you clock out on a Saturday, Sunday, or holiday. Your manager will approve them.</span>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--yellow);"><?= $pendingCount ?></div><div class="stat-label">Pending</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--green-text);"><?= $approvedCount ?></div><div class="stat-label">Approved</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--brand);"><?= number_format($approvedHrs,1) ?></div><div class="stat-label">Approved Hrs</div></div></div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar"><h2>My ACL Requests <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($requests) ?>)</span></h2></div>
      <table>
        <thead><tr><th>Work Date</th><th>Day</th><th style="text-align:center;">Hours</th><th>Reason</th><th>Status</th><th>Reviewed</th></tr></thead>
        <tbody>
          <?php if(empty($requests)): ?>
            <tr class="empty-row"><td colspan="6">No ACL requests yet. Work on a weekend or holiday and clock out to auto-generate one.</td></tr>
          <?php else: foreach($requests as $r): ?>
          <tr>
            <td class="font-semibold"><?= date('d M Y', strtotime($r['work_date'])) ?></td>
            <td class="text-sm"><?= date('l', strtotime($r['work_date'])) ?></td>
            <td style="text-align:center;font-weight:700;color:var(--brand);"><?= number_format($r['hours'],1) ?></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($r['reason']) ?></td>
            <td><span class="badge <?= $statusMap[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
            <td class="text-sm text-muted"><?= $r['reviewed_at'] ? date('d M Y', strtotime($r['reviewed_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:16px;"><a href="my_acl.php" class="btn btn-secondary">← Back to My ACL</a></div>
  </div>
</div>
</div>
</body>
</html>
