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
<link rel="stylesheet" href="style.css">
<style>

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
