<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $roleRedirects = ['admin' => 'admin/dashboard.php', 'manager' => 'manager/dashboard.php', 'employee' => 'employee/dashboard.php'];
    header('Location: ' . ($roleRedirects[$_SESSION['role']] ?? 'index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS Portal - Sign In</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6b4fa0 0%, #8b5dbf 40%, #a070d4 100%);
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .card {
            display: flex;
            width: 860px;
            max-width: 98vw;
            min-height: 480px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(60, 30, 90, 0.35);
        }

        /* ---- LEFT PANEL ---- */
        .left-panel {
            flex: 1;
            background: linear-gradient(160deg, #1565c0 0%, #1976d2 60%, #1a88e0 100%);
            padding: 44px 40px 36px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
        }

        .logo-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.18);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
        }

        .logo-icon svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }

        .brand-title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .brand-sub {
            font-size: 0.92rem;
            color: rgba(255,255,255,0.78);
            font-weight: 400;
        }

        .features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-top: 40px;
        }

        .features li {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.97rem;
            color: rgba(255,255,255,0.92);
        }

        .check-circle {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .check-circle svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: #fff;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* ---- RIGHT PANEL ---- */
        .right-panel {
            flex: 1;
            background: #fff;
            padding: 52px 48px 36px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .form-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 0.88rem;
            color: #7a7a9a;
        }

        .form-group {
            position: relative;
            margin-top: 26px;
        }

        .form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: #3a3a5c;
            margin-bottom: 7px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            display: flex;
            align-items: center;
        }

        .input-icon svg {
            width: 18px;
            height: 18px;
            stroke: #8899b0;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .form-control {
            width: 100%;
            padding: 13px 14px 13px 44px;
            border: none;
            border-radius: 10px;
            background: #f0f4fa;
            font-size: 0.97rem;
            color: #1a1a2e;
            outline: none;
            transition: box-shadow 0.2s, background 0.2s;
        }

        .form-control:focus {
            background: #e8eef8;
            box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.15);
        }

        .toggle-pw {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 0;
        }

        .toggle-pw svg {
            width: 18px;
            height: 18px;
            stroke: #8899b0;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 18px;
        }

        .remember-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-wrap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #1565c0;
            cursor: pointer;
        }

        .remember-wrap label {
            font-size: 0.88rem;
            color: #4a4a6a;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 0.88rem;
            color: #1565c0;
            font-weight: 600;
            text-decoration: none;
        }

        .forgot-link:hover { text-decoration: underline; }

        .btn-signin {
            margin-top: 24px;
            width: 100%;
            padding: 15px;
            background: #1565c0;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.2s, box-shadow 0.2s;
            letter-spacing: 0.3px;
        }

        .btn-signin:hover {
            background: #0d47a1;
            box-shadow: 0 6px 20px rgba(21, 101, 192, 0.35);
        }

        .btn-signin svg {
            width: 18px;
            height: 18px;
            stroke: #fff;
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .copyright {
            text-align: center;
            font-size: 0.8rem;
            color: #a0a0b8;
            margin-top: 22px;
        }

        .alert {
            margin-top: 16px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .alert-error {
            background: #fde8e8;
            color: #c0392b;
            border-left: 4px solid #c0392b;
        }

        @media (max-width: 680px) {
            .card { flex-direction: column; min-height: unset; }
            .left-panel { padding: 28px 24px 24px 24px; }
            .right-panel { padding: 32px 24px 24px 24px; }
            .features { margin-top: 20px; }
        }
    </style>
</head>
<body>
<div class="card">
    <!-- LEFT PANEL -->
    <div class="left-panel">
        <div>
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="6" width="18" height="2.5" rx="1.2"/>
                    <rect x="3" y="11" width="18" height="2.5" rx="1.2"/>
                    <rect x="3" y="16" width="12" height="2.5" rx="1.2"/>
                </svg>
            </div>
            <div class="brand-title">HRMS Portal</div>
            <div class="brand-sub">Human Resource Management System</div>
        </div>
        <ul class="features">
            <li>
                <span class="check-circle">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                Secure Authentication
            </li>
            <li>
                <span class="check-circle">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                Real-time Analytics
            </li>
            <li>
                <span class="check-circle">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                Cloud-based Solution
            </li>
        </ul>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <div>
            <div class="form-header">
                <h1>Sign In</h1>
                <p>Enter your credentials to access your account</p>
            </div>

            <div id="error-box" class="alert alert-error" style="display:none;"></div>

            <form id="loginForm" method="POST" action="auth/login.php" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
                        </span>
                        <input type="email" id="email" name="email" class="form-control" placeholder="admin@hrms.com" autocomplete="username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password">
                            <svg id="eyeIcon" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <div class="remember-wrap">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="auth/forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-signin">
                    Sign In
                    <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
        </div>

        <div class="copyright">&copy; 2026 HRMS. All rights reserved.</div>
    </div>
</div>

<script>
    // Toggle password visibility
    const togglePw = document.getElementById('togglePw');
    const pwInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    const eyeOpen = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    const eyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;

    togglePw.addEventListener('click', () => {
        const isHidden = pwInput.type === 'password';
        pwInput.type = isHidden ? 'text' : 'password';
        eyeIcon.innerHTML = isHidden ? eyeClosed : eyeOpen;
    });

    // Client-side validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const errBox = document.getElementById('error-box');

        if (!email || !password) {
            e.preventDefault();
            errBox.textContent = 'Please enter your username and password.';
            errBox.style.display = 'block';
            return;
        }
        errBox.style.display = 'none';
    });

    // Show PHP error from query string if exists
    const params = new URLSearchParams(window.location.search);
    if (params.get('error')) {
        const errBox = document.getElementById('error-box');
        const msg = params.get('error');
        const messages = {
            'invalid': 'Invalid username or password. Please try again.',
            'inactive': 'Your account is inactive. Contact administrator.',
            'server': 'Server error. Please try again later.'
        };
        errBox.textContent = messages[msg] || 'Login failed. Please try again.';
        errBox.style.display = 'block';
    }
</script>
</body>
</html>
