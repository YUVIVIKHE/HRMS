<?php $mp = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div class="logo-text"><strong>HRMS Portal</strong><span>Manager Panel</span></div>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Main</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="<?= $mp==='dashboard.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
    </nav>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Team</div>
    <nav class="sidebar-nav">
      <a href="my_team.php" class="<?= $mp==='my_team.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        My Team
      </a>
      <a href="my_leaves.php" class="<?= $mp==='my_leaves.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        My Leaves
      </a>
      <a href="leave_requests.php" class="<?= $mp==='leave_requests.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Leave Requests
      </a>
      <!-- Regularizations with sub-menu -->
      <?php $regPages = ['regularizations.php','my_regularizations.php']; $regOpen = in_array($mp,$regPages); ?>
      <a href="#" class="has-sub <?= $regOpen?'open':'' ?>" onclick="toggleSub('regSub',this);return false;">
        <span style="display:flex;align-items:center;gap:10px;">
          <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Regularizations
        </span>
        <svg class="sub-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </a>
      <div class="sidebar-submenu <?= $regOpen?'open':'' ?>" id="regSub">
        <a href="regularizations.php" class="<?= $mp==='regularizations.php'?'active':'' ?>">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Team Requests
        </a>
        <a href="my_regularizations.php" class="<?= $mp==='my_regularizations.php'?'active':'' ?>">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          My Requests
        </a>
      </div>
      <a href="attendance.php" class="<?= $mp==='attendance.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Attendance
      </a>
      <a href="tasks.php" class="<?= $mp==='tasks.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Tasks
      </a>
    </nav>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">Account</div>
    <nav class="sidebar-nav">
      <a href="profile.php" class="<?= $mp==='profile.php'?'active':'' ?>">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        My Profile
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

<script>
function toggleSub(id, el) {
  const sub = document.getElementById(id);
  const isOpen = sub.classList.toggle('open');
  el.classList.toggle('open', isOpen);
}
// Auto-open sub if current page is inside it
document.querySelectorAll('.sidebar-submenu').forEach(sub => {
  if (sub.querySelector('a.active')) {
    sub.classList.add('open');
    sub.previousElementSibling?.classList.add('open');
  }
});
</script>
