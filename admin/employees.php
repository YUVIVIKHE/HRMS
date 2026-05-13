<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();
$employees = $db->query("SELECT id, name, email, status, created_at FROM users WHERE role='employee' ORDER BY created_at DESC")->fetchAll();
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

.page-header {
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.page-header h1 {
    font-size: 1.8rem;
    font-weight: 800;
    letter-spacing: -0.5px;
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
    <div class="page-header">
        <h1>Employees</h1>
        <button class="btn-primary">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add Employee
        </button>
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
