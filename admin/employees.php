<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

// Handle flashed messages
$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN employee_type = 'FTE' THEN 1 ELSE 0 END) as fte,
        SUM(CASE WHEN employee_type = 'External' THEN 1 ELSE 0 END) as external
    FROM employees
")->fetch(PDO::FETCH_ASSOC);

$totalEmp = $stats['total'] ?? 0;
$activeEmp = $stats['active'] ?? 0;
$fteEmp = $stats['fte'] ?? 0;
$extEmp = $stats['external'] ?? 0;

$employees = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, status, created_at FROM employees ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Employees – HRMS Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #f8fafc;
    --surface: #ffffff;
    --border: #e2e8f0;
    --accent: #4f46e5;
    --text: #0f172a;
    --muted: #64748b;
    --sidebar: 260px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}

/* Sidebar Styles */
.sidebar{width:var(--sidebar);min-width:var(--sidebar);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:32px 0;position:fixed;top:0;left:0;height:100vh;z-index:100}
.s-logo{display:flex;align-items:center;gap:12px;padding:0 28px 32px;border-bottom:1px solid var(--border)}
.s-logo .lb{width:42px;height:42px;border-radius:12px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:bold}
.s-logo strong{font-size:1.1rem;font-weight:800;color:var(--text)} 
.s-logo small{display:block;font-size:.75rem;color:var(--muted);margin-top:2px}

nav{flex:1;padding:24px 0;overflow-y:auto}
nav a{display:flex;align-items:center;gap:12px;padding:12px 28px;font-size:.95rem;font-weight:500;color:var(--muted);text-decoration:none;border-left:3px solid transparent;transition:all .2s ease}
nav a:hover, nav a.active{background:#f1f5f9;color:var(--accent);border-left-color:var(--accent)}
nav a svg{width:20px;height:20px;flex-shrink:0}

.s-foot{padding:24px 28px;border-top:1px solid var(--border)}
.s-foot a{display:flex;align-items:center;justify-content:flex-start;gap:10px;color:#ef4444;font-size:.9rem;font-weight:600;text-decoration:none;padding:12px 16px;border-radius:10px;background:#fef2f2;transition:all .2s ease}
.s-foot a:hover{background:#fee2e2}
.s-foot a svg{width:20px;height:20px;flex-shrink:0}

/* Main Content Styles */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;padding:40px 60px}

.header-card {
    background: linear-gradient(135deg, #1d4ed8, #3b82f6);
    color: white;
    padding: 32px 40px;
    border-radius: 16px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header-bg {
    position: absolute;
    right: -100px;
    top: -100px;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    z-index: 1;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
}
.stat-card-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.stat-card-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 8px;
    line-height: 1;
}
.stat-card-desc {
    font-size: 0.85rem;
    color: var(--muted);
    line-height: 1.4;
}

.badge-blue {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 10px 20px;
    border-radius: 999px;
    font-size: 0.95rem;
    font-weight: 600;
    backdrop-filter: blur(4px);
}
.btn-primary {
    background: var(--accent);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
}
.btn-primary:hover {
    background: #4338ca;
}

.table-container {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f8fafc;
    padding: 16px 24px;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
}

td {
    padding: 16px 24px;
    font-size: 0.9rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

tr:last-child td {
    border-bottom: none;
}

.user-info {
    display: flex;
    flex-direction: column;
}
.user-name {
    font-weight: 600;
    color: var(--text);
}
.user-email {
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: 2px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
}
.status-active {
    background: #dcfce7;
    color: #166534;
}
.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.empty-state {
    padding: 60px;
    text-align: center;
    color: var(--muted);
}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <?php if ($successMsg): ?>
        <div style="padding:16px; border-radius:8px; margin-bottom:24px; font-weight:500; font-size:0.95rem; background:#dcfce7; color:#166534; border:1px solid #bbf7d0;"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div style="padding:16px; border-radius:8px; margin-bottom:24px; font-weight:500; font-size:0.95rem; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="header-card">
        <div class="header-bg"></div>
        <div style="position:relative; z-index:2; max-width: 600px;">
            <div style="font-size:0.75rem; font-weight:700; letter-spacing:0.1em; opacity:0.8; margin-bottom:8px; text-transform:uppercase;">WORKFORCE DIRECTORY</div>
            <h1 style="font-size:2.2rem; font-weight:800; margin-bottom:12px; letter-spacing:-0.5px;">Employee Management</h1>
            <p style="font-size:1rem; opacity:0.9; line-height:1.6;">Review employee profiles, track active headcount, and manage promotion to manager from one polished admin workspace.</p>
        </div>
        <div style="position:relative; z-index:2; display:flex; align-items:center; gap:16px;">
            <a href="add_employee.php" style="background:white; color:#1d4ed8; padding:10px 20px; border-radius:999px; font-weight:700; font-size:0.95rem; text-decoration:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 6px rgba(0,0,0,0.1); transition:transform 0.2s;">
                + Add Employee
            </a>
            <div class="badge-blue"><?= htmlspecialchars($totalEmp) ?> Total Records</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-title">TOTAL EMPLOYEES</div>
            <div class="stat-card-value"><?= htmlspecialchars($totalEmp) ?></div>
            <div class="stat-card-desc">Current records in the employee directory.</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">ACTIVE EMPLOYEES</div>
            <div class="stat-card-value"><?= htmlspecialchars($activeEmp) ?></div>
            <div class="stat-card-desc">Employees currently marked active.</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">FTE EMPLOYEES</div>
            <div class="stat-card-value"><?= htmlspecialchars($fteEmp) ?></div>
            <div class="stat-card-desc">Full-time employee headcount.</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">EXTERNAL EMPLOYEES</div>
            <div class="stat-card-value"><?= htmlspecialchars($extEmp) ?></div>
            <div class="stat-card-desc">Contract or external workforce count.</div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Status</th>
                    <th>Date Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($employees)): ?>
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">No employees found.</div>
                        </td>
                    </tr>
                <?php else: foreach($employees as $emp): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <span class="user-name"><?= htmlspecialchars($emp['name']) ?></span>
                                <span class="user-email"><?= htmlspecialchars($emp['email']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $emp['status'] ?>">
                                <?= $emp['status'] ?>
                            </span>
                        </td>
                        <td style="color: var(--muted);">
                            <?= date('M d, Y', strtotime($emp['created_at'])) ?>
                        </td>
                        <td>
                            <a href="#" style="color: var(--accent); font-weight: 600; font-size: 0.85rem; text-decoration: none;">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
