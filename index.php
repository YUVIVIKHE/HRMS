<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $r = ['admin'=>'admin/dashboard.php','manager'=>'manager/dashboard.php','employee'=>'employee/dashboard.php'];
    header('Location: '.($r[$_SESSION['role']] ?? 'index.php')); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;background:#f5f6fa;-webkit-font-smoothing:antialiased}

.login-left{
  flex:1;
  background:linear-gradient(145deg,#312e81 0%,#4338ca 50%,#6366f1 100%);
  display:flex;flex-direction:column;justify-content:space-between;
  padding:48px;color:#fff;position:relative;overflow:hidden;
}
.login-left::before{
  content:'';position:absolute;top:-120px;right:-120px;
  width:400px;height:400px;border-radius:50%;
  background:rgba(255,255,255,.06);
}
.login-left::after{
  content:'';position:absolute;bottom:-80px;left:-80px;
  width:300px;height:300px;border-radius:50%;
  background:rgba(255,255,255,.04);
}
.ll-logo{display:flex;align-items:center;gap:12px;position:relative;z-index:1;}
.ll-logo .mark{width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;}
.ll-logo .mark svg{width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2;}
.ll-logo strong{font-size:16px;font-weight:700;}
.ll-hero{position:relative;z-index:1;}
.ll-hero h1{font-size:32px;font-weight:800;line-height:1.2;letter-spacing:-.5px;margin-bottom:14px;}
.ll-hero p{font-size:15px;color:rgba(255,255,255,.75);line-height:1.6;max-width:340px;}
.ll-features{list-style:none;display:flex;flex-direction:column;gap:14px;position:relative;z-index:1;}
.ll-features li{display:flex;align-items:center;gap:12px;font-size:14px;color:rgba(255,255,255,.88);}
.ll-features li .dot{width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ll-features li .dot svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2.5;}

.login-right{
  width:480px;min-width:480px;
  background:#fff;
  display:flex;flex-direction:column;justify-content:center;
  padding:60px 52px;
}
.lr-header{margin-bottom:36px;}
.lr-header h2{font-size:26px;font-weight:800;color:#111827;letter-spacing:-.4px;margin-bottom:6px;}
.lr-header p{font-size:14px;color:#6b7280;}

.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;}
.input-wrap{position:relative;display:flex;align-items:center;}
.input-icon{position:absolute;left:13px;display:flex;align-items:center;pointer-events:none;}
.input-icon svg{width:16px;height:16px;stroke:#9ca3af;fill:none;stroke-width:2;}
.form-control{
  width:100%;padding:11px 13px 11px 40px;
  border:1px solid #e5e7eb;border-radius:9px;
  font-size:14px;font-family:inherit;color:#111827;
  background:#f9fafb;outline:none;
  transition:border-color .15s,box-shadow .15s,background .15s;
}
.form-control:focus{border-color:#4f46e5;background:#fff;box-shadow:0 0 0 3px rgba(79,70,229,.12);}
.form-control::placeholder{color:#d1d5db;}
.toggle-pw{position:absolute;right:12px;background:none;border:none;cursor:pointer;display:flex;align-items:center;padding:4px;}
.toggle-pw svg{width:16px;height:16px;stroke:#9ca3af;fill:none;stroke-width:2;}

.form-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;}
.remember{display:flex;align-items:center;gap:7px;font-size:13px;color:#4b5563;cursor:pointer;}
.remember input{width:15px;height:15px;accent-color:#4f46e5;cursor:pointer;}
.forgot{font-size:13px;font-weight:600;color:#4f46e5;text-decoration:none;}
.forgot:hover{text-decoration:underline;}

.btn-login{
  width:100%;padding:13px;
  background:#4f46e5;color:#fff;
  border:none;border-radius:9px;
  font-size:15px;font-weight:700;font-family:inherit;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
  transition:background .15s,box-shadow .15s;
}
.btn-login:hover{background:#3730a3;box-shadow:0 6px 20px rgba(79,70,229,.35);}
.btn-login svg{width:17px;height:17px;stroke:#fff;fill:none;stroke-width:2.5;}

.alert-error{
  margin-bottom:20px;padding:11px 14px;
  background:#fee2e2;color:#991b1b;
  border:1px solid #fca5a5;border-radius:8px;
  font-size:13.5px;font-weight:500;
  display:flex;align-items:center;gap:8px;
}
.alert-error svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}

.lr-footer{margin-top:32px;text-align:center;font-size:12px;color:#9ca3af;}

@media(max-width:860px){
  .login-left{display:none;}
  .login-right{width:100%;min-width:0;padding:40px 28px;}
}
</style>
</head>
<body>

<div class="login-left">
  <div class="ll-logo">
    <div class="mark">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <strong>HRMS Portal</strong>
  </div>

  <div class="ll-hero">
    <h1>Manage your workforce with confidence</h1>
    <p>A modern HR platform built for teams that move fast — from onboarding to payroll, all in one place.</p>
  </div>

  <ul class="ll-features">
    <li>
      <span class="dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
      Role-based access for Admin, Manager & Employee
    </li>
    <li>
      <span class="dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
      Custom fields, bulk import & real-time data
    </li>
    <li>
      <span class="dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
      Secure, session-based authentication
    </li>
  </ul>
</div>

<div class="login-right">
  <div class="lr-header">
    <h2>Welcome back</h2>
    <p>Sign in to your account to continue</p>
  </div>

  <div id="error-box" class="alert-error" style="display:none;">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span id="error-msg"></span>
  </div>

  <form id="loginForm" method="POST" action="auth/login.php" novalidate>
    <div class="form-group">
      <label for="email">Email Address</label>
      <div class="input-wrap">
        <span class="input-icon"><svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg></span>
        <input type="email" id="email" name="email" class="form-control" placeholder="you@company.com" autocomplete="username" required>
      </div>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="input-wrap">
        <span class="input-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required>
        <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password visibility">
          <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <div class="form-row">
      <label class="remember">
        <input type="checkbox" name="remember"> Remember me
      </label>
      <a href="auth/forgot_password.php" class="forgot">Forgot password?</a>
    </div>

    <button type="submit" class="btn-login">
      Sign In
      <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </button>
  </form>

  <div class="lr-footer">&copy; <?= date('Y') ?> HRMS Portal. All rights reserved.</div>
</div>

<script>
const togglePw = document.getElementById('togglePw');
const pwInput  = document.getElementById('password');
const eyeIcon  = document.getElementById('eyeIcon');
const eyeOpen  = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
const eyeClosed= `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;
togglePw.addEventListener('click',()=>{
  const hidden = pwInput.type==='password';
  pwInput.type = hidden?'text':'password';
  eyeIcon.innerHTML = hidden?eyeClosed:eyeOpen;
});

document.getElementById('loginForm').addEventListener('submit',function(e){
  const email = document.getElementById('email').value.trim();
  const pw    = document.getElementById('password').value;
  if(!email||!pw){
    e.preventDefault();
    showError('Please enter your email and password.');
  }
});

const params = new URLSearchParams(window.location.search);
if(params.get('error')){
  const msgs = {invalid:'Invalid email or password.',inactive:'Your account is inactive. Contact your administrator.',server:'Server error. Please try again.'};
  showError(msgs[params.get('error')]||'Login failed. Please try again.');
}

function showError(msg){
  const box = document.getElementById('error-box');
  document.getElementById('error-msg').textContent = msg;
  box.style.display = 'flex';
}
</script>
</body>
</html>
