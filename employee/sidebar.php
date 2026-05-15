<?php $ep = basename($_SERVER['PHP_SELF']);
$_companyLogo = '';
try { $__db = getDB(); $__ls = $__db->prepare("SELECT setting_value FROM app_settings WHERE setting_key='company_logo'"); $__ls->execute(); $_companyLogo = $__ls->fetchColumn() ?: ''; } catch (Exception $e) {}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <?php if($_companyLogo): ?>
      <img src="../<?= htmlspecialchars($_companyLogo) ?>" alt="Logo" style="max-height:36px;max-width:140px;">
    <?php else: ?>
    <div class="logo-mark" style="background:linear-gradient(135deg,#059669,#10b981);">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <?php endif; ?>
    <div class="logo-text"><strong>HRMS Portal</strong><span>My Workspace</span></div>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Main</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="<?= $ep==='dashboard.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
    </nav>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">My Work</div>
    <nav class="sidebar-nav">
      <a href="my_leaves.php" class="<?= $ep==='my_leaves.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        My Leaves
      </a>
      <a href="my_regularizations.php" class="<?= $ep==='my_regularizations.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        My Regularizations
      </a>
      <a href="my_tasks.php" class="<?= $ep==='my_tasks.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        My Tasks
      </a>
      <a href="attendance.php" class="<?= $ep==='attendance.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Attendance
      </a>
      <a href="my_acl.php" class="<?= $ep==='my_acl.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/></svg>
        My ACL
      </a>
    </nav>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Account</div>
    <nav class="sidebar-nav">
      <a href="profile.php" class="<?= $ep==='profile.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        My Profile
      </a>
      <a href="payslip.php" class="<?= $ep==='payslip.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Payslip
      </a>
    </nav>
  </div>

  <div class="sidebar-footer">
    <a href="../auth/logout.php">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sign Out
    </a>
  </div>
</aside>
