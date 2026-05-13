<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();
$totalEmployees  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='employee'")->fetchColumn();
$totalManagers   = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='manager'")->fetchColumn();
$pendingLeaves   = 12; // Static placeholder
$todayAttendance = 45; // Static placeholder
$recentUsers  = $db->query("SELECT name,email,role,status,created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();
$recentLeaves = []; // Static placeholder
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard – HRMS Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f0e17;--surface:#1a1828;--surface2:#221f33;--border:rgba(255,255,255,.07);--accent:#7c5cbf;--accent2:#a855f7;--red:#ef4444;--green:#22c55e;--yellow:#eab308;--blue:#3b82f6;--text:#e8e6f0;--muted:#9890b0;--sidebar:240px;--r:14px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);min-width:var(--sidebar);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:24px 0;position:fixed;top:0;left:0;height:100vh;z-index:100}
.s-logo{display:flex;align-items:center;gap:10px;padding:0 22px 24px;border-bottom:1px solid var(--border)}
.s-logo .lb{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:1.3rem}
.s-logo strong{font-size:1rem;font-weight:800} .s-logo small{display:block;font-size:.67rem;color:var(--muted)}
nav{flex:1;padding:14px 0;overflow-y:auto}
.ns{font-size:.67rem;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:1px;padding:14px 22px 5px}
nav a{display:flex;align-items:center;gap:11px;padding:10px 22px;font-size:.87rem;font-weight:500;color:var(--muted);text-decoration:none;border-left:3px solid transparent;transition:.2s}
nav a:hover,nav a.active{background:rgba(168,85,247,.1);color:var(--text);border-left-color:var(--accent2)}
nav a svg{width:17px;height:17px;flex-shrink:0}
.s-foot{padding:16px 22px;border-top:1px solid var(--border)}
.s-foot a{display:flex;align-items:center;gap:10px;color:var(--red);font-size:.85rem;font-weight:600;text-decoration:none;padding:8px 12px;border-radius:8px;transition:.2s}
.s-foot a:hover{background:rgba(239,68,68,.1)}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.topbar-r{display:flex;align-items:center;gap:14px}
.av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem}
.badge{background:var(--accent2);color:#fff;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:20px}
.content{padding:28px;flex:1}
.ph{margin-bottom:24px} .ph h1{font-size:1.6rem;font-weight:800;letter-spacing:-.4px} .ph p{color:var(--muted);font-size:.88rem;margin-top:4px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.sc{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:20px;display:flex;align-items:flex-start;gap:14px;transition:.2s}
.sc:hover{transform:translateY(-3px);box-shadow:0 12px 36px rgba(0,0,0,.4)}
.si{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.3rem}
.sc .val{font-size:1.8rem;font-weight:800;line-height:1} .sc .lbl{font-size:.76rem;color:var(--muted);margin-top:4px} .sc .chg{font-size:.74rem;margin-top:5px;font-weight:600}
.panels{display:grid;grid-template-columns:1.4fr 1fr;gap:18px}
.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.ph2{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.ph2 h2{font-size:.93rem;font-weight:700} .ph2 a{font-size:.77rem;color:var(--accent2);text-decoration:none;font-weight:600}
table{width:100%;border-collapse:collapse}
th{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);padding:9px 20px;text-align:left;border-bottom:1px solid var(--border)}
td{padding:10px 20px;font-size:.84rem;border-bottom:1px solid var(--border)}
tr:last-child td{border-bottom:none} tr:hover td{background:rgba(255,255,255,.025)}
.pill{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:capitalize}
.pill-active,.pill-approved{background:rgba(34,197,94,.15);color:#4ade80}
.pill-inactive,.pill-rejected{background:rgba(239,68,68,.15);color:#f87171}
.pill-pending{background:rgba(234,179,8,.15);color:#fbbf24}
.pill-admin{background:rgba(168,85,247,.15);color:#c084fc}
.pill-manager{background:rgba(59,130,246,.15);color:#60a5fa}
.pill-employee{background:rgba(34,197,94,.15);color:#4ade80}
.ag{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:18px}
.ab{display:flex;flex-direction:column;align-items:center;gap:7px;padding:16px 10px;border-radius:11px;border:1px solid var(--border);background:var(--surface2);text-decoration:none;color:var(--text);font-size:.78rem;font-weight:600;text-align:center;transition:.2s}
.ab:hover{background:rgba(168,85,247,.15);transform:translateY(-2px)} .ab .ic{font-size:1.5rem}
@media(max-width:1100px){.stats{grid-template-columns:repeat(2,1fr)}.panels{grid-template-columns:1fr}}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="s-logo"><div class="lb">⚡</div><div><strong>HRMS</strong><small>Admin Panel</small></div></div>
  <nav>
    <div class="ns">Main</div>
    <a href="dashboard.php" class="active"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Dashboard</span></a>
    <div class="ns">Management</div>
    <a href="employees.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Employees</span></a>
    <a href="managers.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Managers</span></a>
    <a href="leaves.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>Leave Requests</span></a>
    <a href="attendance.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>Attendance</span></a>
    <a href="payroll.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Payroll</span></a>
    <div class="ns">Settings</div>
    <a href="settings.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Settings</span></a>
  </nav>
  <div class="s-foot"><a href="../auth/logout.php"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a></div>
</aside>

<div class="main">
  <div class="topbar">
    <strong>Admin Dashboard</strong>
    <div class="topbar-r">
      <span class="badge">Admin</span>
      <div class="av"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span style="font-size:.86rem;color:var(--muted)"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </div>

  <div class="content">
    <div class="ph">
      <h1>Welcome back, <?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?> 👋</h1>
      <p><?= date('l, F j, Y') ?> — Here's your organization overview.</p>
    </div>

    <div class="stats">
      <div class="sc"><div class="si" style="background:rgba(168,85,247,.15);color:#c084fc">👥</div><div><div class="val"><?= $totalEmployees ?></div><div class="lbl">Total Employees</div><div class="chg" style="color:#4ade80">↑ Active workforce</div></div></div>
      <div class="sc"><div class="si" style="background:rgba(59,130,246,.15);color:#60a5fa">🎯</div><div><div class="val"><?= $totalManagers ?></div><div class="lbl">Total Managers</div><div class="chg" style="color:#60a5fa">↑ Team leaders</div></div></div>
      <div class="sc"><div class="si" style="background:rgba(234,179,8,.15);color:#fbbf24">📋</div><div><div class="val"><?= $pendingLeaves ?></div><div class="lbl">Pending Leaves</div><div class="chg" style="color:#fbbf24">⚠ Needs review</div></div></div>
      <div class="sc"><div class="si" style="background:rgba(34,197,94,.15);color:#4ade80">✅</div><div><div class="val"><?= $todayAttendance ?></div><div class="lbl">Present Today</div><div class="chg" style="color:#4ade80">↑ Checked in</div></div></div>
    </div>

    <div class="panels">
      <div class="panel">
        <div class="ph2"><h2>Recent Users</h2><a href="employees.php">View all →</a></div>
        <table>
          <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
          <tbody>
          <?php if(empty($recentUsers)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:28px">No users yet</td></tr>
          <?php else: foreach($recentUsers as $u): ?>
            <tr>
              <td><div style="font-weight:600"><?= htmlspecialchars($u['name']) ?></div><div style="font-size:.73rem;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></div></td>
              <td><span class="pill pill-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
              <td><span class="pill pill-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
              <td style="color:var(--muted);font-size:.78rem"><?= date('M d, Y',strtotime($u['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div style="display:flex;flex-direction:column;gap:18px">
        <div class="panel">
          <div class="ph2"><h2>Quick Actions</h2></div>
          <div class="ag">
            <a href="employees.php?action=add" class="ab"><span class="ic">➕</span><span>Add Employee</span></a>
            <a href="payroll.php" class="ab"><span class="ic">💰</span><span>Run Payroll</span></a>
            <a href="leaves.php" class="ab"><span class="ic">📅</span><span>Review Leaves</span></a>
            <a href="attendance.php" class="ab"><span class="ic">📊</span><span>Reports</span></a>
          </div>
        </div>
        <div class="panel">
          <div class="ph2"><h2>Recent Leave Requests</h2><a href="leaves.php">View all →</a></div>
          <table>
            <thead><tr><th>Employee</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
            <?php if(empty($recentLeaves)): ?>
              <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:22px">No requests</td></tr>
            <?php else: foreach($recentLeaves as $l): ?>
              <tr>
                <td style="font-weight:600;font-size:.82rem"><?= htmlspecialchars($l['emp_name']) ?></td>
                <td style="font-size:.79rem;color:var(--muted)"><?= htmlspecialchars($l['leave_type']) ?></td>
                <td><span class="pill pill-<?= $l['status'] ?>"><?= $l['status'] ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
