<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_employee') {
        $id = (int)($_POST['employee_id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = "Employee deleted successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
            }
        }
        header("Location: employees.php"); exit;
    }

    if ($action === 'edit_employee') {
        $id = (int)($_POST['employee_id'] ?? 0);
        if ($id > 0) {
            try {
                $fields = ['first_name','last_name','email','phone','job_title',
                           'department_id','employee_type','status','date_of_joining',
                           'direct_manager_name','location','gross_salary'];
                $sets   = [];
                $params = [];
                foreach ($fields as $f) {
                    $sets[]   = "`$f` = ?";
                    $val      = trim($_POST[$f] ?? '');
                    $params[] = ($val === '') ? null : $val;
                }
                $params[] = $id;
                $db->prepare("UPDATE employees SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
                $_SESSION['flash_success'] = "Employee updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
            }
        }
        header("Location: employees.php"); exit;
    }}

// ── Data ─────────────────────────────────────────────────────
$stats = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN employee_type='FTE' THEN 1 ELSE 0 END) as fte,
        SUM(CASE WHEN employee_type='External' THEN 1 ELSE 0 END) as external
    FROM employees
")->fetch(PDO::FETCH_ASSOC);

$employees = $db->query("
    SELECT id, first_name, last_name, email, phone, job_title,
           department_id, status, employee_type, date_of_joining,
           direct_manager_name, location, gross_salary, created_at
    FROM employees ORDER BY created_at DESC
")->fetchAll();

$deptRows    = $db->query("SELECT id, name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
$departments = $deptRows ?: [];
$deptList    = $db->query("SELECT id, name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Employees – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
/* ── Delete confirm modal ── */
.del-modal {
  position: fixed; inset: 0;
  background: rgba(17,24,39,.5);
  backdrop-filter: blur(3px);
  z-index: 600;
  display: none; align-items: center; justify-content: center;
}
.del-modal.open { display: flex; }
.del-box {
  background: var(--surface);
  border-radius: var(--radius-xl);
  padding: 32px;
  width: 100%; max-width: 400px;
  box-shadow: var(--shadow-lg);
  text-align: center;
}
.del-icon {
  width: 52px; height: 52px;
  background: var(--red-bg);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 16px;
}
.del-icon svg { width: 24px; height: 24px; stroke: var(--red); fill: none; stroke-width: 2; }
.del-box h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
.del-box p  { font-size: 13.5px; color: var(--muted); margin-bottom: 24px; line-height: 1.5; }
.del-actions { display: flex; gap: 10px; justify-content: center; }
</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Employees</span>
      <span class="page-breadcrumb">Workforce Directory</span>
    </div>
    <div class="topbar-right">
      <span class="role-chip">Admin</span>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
      <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </header>

  <div class="page-body">

    <?php if($successMsg): ?>
      <div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
      <div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-text">
        <h1>Employee Directory</h1>
        <p>Manage your workforce — view profiles, track headcount, and onboard new hires.</p>
      </div>
      <div class="page-header-actions">
        <a href="add_employee.php" class="btn btn-primary">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Employee
        </a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:#eef2ff;color:var(--brand)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= $stats['total'] ?? 0 ?></div><div class="stat-label">Total</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green)">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= $stats['active'] ?? 0 ?></div><div class="stat-label">Active</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-bg);color:var(--blue)">
          <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= $stats['fte'] ?? 0 ?></div><div class="stat-label">FTE</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow)">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= $stats['external'] ?? 0 ?></div><div class="stat-label">External</div></div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar" style="flex-wrap:wrap;gap:10px;">
        <h2>All Employees <span id="empCount" style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($employees) ?>)</span></h2>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-left:auto;">
          <!-- Search -->
          <div class="search-box">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="empSearch" placeholder="Search…" oninput="filterTable()">
          </div>
          <!-- Department filter -->
          <select id="filterDept" class="form-control" style="width:auto;min-width:160px;font-size:13px;padding:7px 12px;" onchange="filterTable()">
            <option value="">All Departments</option>
            <?php foreach($deptList as $d): ?>
              <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <!-- Type filter -->
          <select id="filterType" class="form-control" style="width:auto;min-width:130px;font-size:13px;padding:7px 12px;" onchange="filterTable()">
            <option value="">All Types</option>
            <option value="FTE">FTE</option>
            <option value="External">External</option>
          </select>
          <!-- Status filter -->
          <select id="filterStatus" class="form-control" style="width:auto;min-width:120px;font-size:13px;padding:7px 12px;" onchange="filterTable()">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="terminated">Terminated</option>
          </select>
          <!-- Clear -->
          <button class="btn btn-ghost btn-sm" onclick="clearFilters()" id="clearBtn" style="display:none;">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Clear
          </button>
        </div>
      </div>
      <table id="empTable">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Job Title</th>
            <th>Type</th>
            <th>Status</th>
            <th>Joined</th>
            <th style="width:120px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($employees)): ?>
            <tr class="empty-row"><td colspan="7">No employees found. <a href="add_employee.php" style="color:var(--brand)">Add your first employee →</a></td></tr>
          <?php else: foreach($employees as $emp):
            $fullName = htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']);
          ?>
          <tr data-dept="<?= htmlspecialchars($departments[$emp['department_id']] ?? '') ?>"
              data-type="<?= htmlspecialchars($emp['employee_type'] ?? '') ?>"
              data-status="<?= htmlspecialchars(strtolower($emp['status'] ?? '')) ?>">
            <td>
              <div class="td-user">
                <div class="td-avatar"><?= strtoupper(substr($emp['first_name'],0,1)) ?></div>
                <div>
                  <div class="td-name"><?= $fullName ?></div>
                  <div class="td-sub"><?= htmlspecialchars($emp['email']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($departments[$emp['department_id']] ?? '—') ?></td>
            <td class="text-muted"><?= htmlspecialchars($emp['job_title'] ?: '—') ?></td>
            <td>
              <span class="badge <?= $emp['employee_type']==='FTE'?'badge-blue':'badge-yellow' ?>">
                <?= htmlspecialchars($emp['employee_type'] ?: '—') ?>
              </span>
            </td>
            <td>
              <span class="badge <?= strtolower($emp['status'])==='active'?'badge-green':'badge-red' ?>">
                <?= htmlspecialchars(ucfirst($emp['status'])) ?>
              </span>
            </td>
            <td class="text-muted text-sm"><?= $emp['date_of_joining'] ? date('M d, Y', strtotime($emp['date_of_joining'])) : date('M d, Y', strtotime($emp['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="edit_employee.php?id=<?= $emp['id'] ?>" class="btn btn-ghost btn-sm">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </a>
                <button type="button" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;"
                  onclick="openDelete(<?= $emp['id'] ?>, '<?= addslashes($emp['first_name'].' '.$emp['last_name']) ?>')">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                  Delete
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div class="del-modal" id="delModal">
  <div class="del-box">
    <div class="del-icon">
      <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
    </div>
    <h3>Delete Employee?</h3>
    <p id="delMsg">This will permanently remove the employee and all their data. This action cannot be undone.</p>
    <div class="del-actions">
      <button type="button" class="btn btn-secondary" onclick="closeDelete()">Cancel</button>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="delete_employee">
        <input type="hidden" name="employee_id" id="del_id">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
function filterTable() {
  const q      = document.getElementById('empSearch').value.toLowerCase();
  const dept   = document.getElementById('filterDept').value;
  const type   = document.getElementById('filterType').value;
  const status = document.getElementById('filterStatus').value;
  const rows   = document.querySelectorAll('#empTable tbody tr:not(.empty-row)');

  let visible = 0;
  rows.forEach(row => {
    const matchText   = !q      || row.textContent.toLowerCase().includes(q);
    const matchDept   = !dept   || row.dataset.dept   === dept;
    const matchType   = !type   || row.dataset.type   === type;
    const matchStatus = !status || row.dataset.status === status.toLowerCase();
    const show = matchText && matchDept && matchType && matchStatus;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('empCount').textContent = '(' + visible + ')';
  document.getElementById('clearBtn').style.display =
    (q || dept || type || status) ? 'inline-flex' : 'none';
}

function clearFilters() {
  document.getElementById('empSearch').value  = '';
  document.getElementById('filterDept').value = '';
  document.getElementById('filterType').value = '';
  document.getElementById('filterStatus').value = '';
  filterTable();
}

// ── Delete Modal ──
function openDelete(id, name) {
  document.getElementById('del_id').value  = id;
  document.getElementById('delMsg').textContent =
    'This will permanently remove ' + name + ' and all their data. This cannot be undone.';
  document.getElementById('delModal').classList.add('open');
}

function closeDelete() {
  document.getElementById('delModal').classList.remove('open');
}

// Close delete modal on backdrop click
document.getElementById('delModal').addEventListener('click', function(e) {
  if (e.target === this) closeDelete();
});
</script>
</body>
</html>
