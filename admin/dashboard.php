<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard – HRMS Portal</title>
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
.s-foot a{display:flex;align-items:center;justify-content:center;gap:10px;color:#ef4444;font-size:.9rem;font-weight:600;text-decoration:none;padding:12px 16px;border-radius:10px;background:#fef2f2;transition:all .2s ease}
.s-foot a:hover{background:#fee2e2}
.s-foot a svg{width:20px;height:20px;flex-shrink:0}

.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px}

.welcome-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 60px;
    text-align: center;
    max-width: 600px;
    width: 100%;
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
}

.welcome-icon {
    width: 80px;
    height: 80px;
    background: #eef2ff;
    color: var(--accent);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 24px;
}

.welcome-card h1 {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 12px;
    letter-spacing: -0.5px;
}

.welcome-card p {
    font-size: 1.1rem;
    color: var(--muted);
    line-height: 1.6;
}

.date-badge {
    display: inline-block;
    margin-top: 32px;
    padding: 8px 16px;
    background: #f8fafc;
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--muted);
}
</style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="welcome-card">
        <div class="welcome-icon">✨</div>
        <h1>Welcome, <?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?></h1>
        <p>Your admin workspace is currently clean and ready. Add new features and integrations here as your platform grows.</p>
        <div class="date-badge">
            <?= date('l, F j, Y') ?>
        </div>
    </div>
</div>
</body>
</html>
