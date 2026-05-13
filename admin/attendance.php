<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_location') {
        $name      = trim($_POST['name'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $lat       = (float)($_POST['latitude'] ?? 0);
        $lng       = (float)($_POST['longitude'] ?? 0);
        $radius    = max(50, (int)($_POST['radius_m'] ?? 200));
        $isRemote  = isset($_POST['is_remote']) ? 1 : 0;
        if ($name) {
            $db->prepare("INSERT INTO attendance_locations (name, address, latitude, longitude, radius_m, is_remote) VALUES (?,?,?,?,?,?)")
               ->execute([$name, $address, $lat, $lng, $radius, $isRemote]);
            $_SESSION['flash_success'] = "Location '$name' added.";
        }
        header("Location: attendance.php"); exit;
    }

    if ($action === 'toggle_location') {
        $lid = (int)$_POST['location_id'];
        $db->prepare("UPDATE attendance_locations SET is_active = NOT is_active WHERE id = ?")->execute([$lid]);
        header("Location: attendance.php"); exit;
    }

    if ($action === 'delete_location') {
        $lid = (int)$_POST['location_id'];
        $db->prepare("DELETE FROM attendance_locations WHERE id = ? AND id != 1")->execute([$lid]);
        $_SESSION['flash_success'] = "Location deleted.";
        header("Location: attendance.php"); exit;
    }
}

// ── Filters ──────────────────────────────────────────────────
$filterDate  = $_GET['date']   ?? date('Y-m');
$filterUser  = (int)($_GET['user_id'] ?? 0);
$filterRole  = $_GET['role']   ?? '';

$logs      = [];
$locations = [];
$users     = [];
$dbError   = null;

try {
    $where  = ["DATE_FORMAT(al.log_date,'%Y-%m') = ?"];
    $params = [$filterDate];
    if ($filterUser > 0) { $where[] = "al.user_id = ?"; $params[] = $filterUser; }
    if ($filterRole)     { $where[] = "u.role = ?";     $params[] = $filterRole; }

    $stmt = $db->prepare("
        SELECT al.*, u.name COLLATE utf8mb4_general_ci AS user_name, u.role AS user_role,
               loc.name COLLATE utf8mb4_general_ci AS location_name
        FROM attendance_logs al
        JOIN users u ON al.user_id = u.id
        LEFT JOIN attendance_locations loc ON al.location_id = loc.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY al.log_date DESC, u.name ASC
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $locations = $db->query("SELECT * FROM attendance_locations ORDER BY is_remote DESC, name ASC")->fetchAll();
    $users     = $db->query("SELECT id, name, role FROM users WHERE role IN ('employee','manager') ORDER BY name COLLATE utf8mb4_general_ci")->fetchAll();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    error_log('Admin attendance error: ' . $e->getMessage());
}

if (!function_exists('fmtTime')) {
    function fmtTime($dt) { return $dt ? date('h:i A', strtotime($dt)) : '—'; }
}
if (!function_exists('fmtHrs')) {
    function fmtHrs($sec) {
        if (!$sec) return '—';
        return sprintf('%dh %02dm', floor($sec/3600), floor(($sec%3600)/60));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attendance – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Attendance</span>
      <span class="page-breadcrumb">Logs & Location Management</span>
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
    <?php if($dbError): ?>
      <div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Database error: <?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <div class="tabs" id="mainTabs">
      <button class="tab-btn active" onclick="switchTab('logs')">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Attendance Logs
      </button>
      <button class="tab-btn" onclick="switchTab('locations')">
        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Office Locations
      </button>
    </div>

    <!-- ── LOGS TAB ── -->
    <div id="tab-logs">
      <!-- Filters -->
      <div class="card" style="margin-bottom:16px;">
        <div class="card-body" style="padding:16px 20px;">
          <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;min-width:160px;">
              <label style="font-size:12px;">Month</label>
              <input type="month" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div class="form-group" style="margin:0;min-width:200px;">
              <label style="font-size:12px;">Employee / Manager</label>
              <select name="user_id" class="form-control">
                <option value="">All Users</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= $u['id'] ?>" <?= $filterUser==$u['id']?'selected':'' ?>>
                    <?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0;min-width:140px;">
              <label style="font-size:12px;">Role</label>
              <select name="role" class="form-control">
                <option value="">All Roles</option>
                <option value="employee" <?= $filterRole==='employee'?'selected':'' ?>>Employee</option>
                <option value="manager"  <?= $filterRole==='manager' ?'selected':'' ?>>Manager</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:1px;">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              Filter
            </button>
            <a href="attendance.php" class="btn btn-secondary" style="margin-bottom:1px;">Reset</a>
          </form>
        </div>
      </div>

      <div class="table-wrap">
        <div class="table-toolbar">
          <h2>Attendance Logs <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($logs) ?> records)</span></h2>
        </div>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Employee</th>
              <th>Role</th>
              <th>Clock In</th>
              <th>Clock Out</th>
              <th>Work Hours</th>
              <th>Status</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($logs)): ?>
              <tr class="empty-row"><td colspan="8">No attendance records for this period.</td></tr>
            <?php else: foreach($logs as $log):
              $hrs = $log['work_seconds'] ?? 0;
              $short = $hrs > 0 && $hrs < 32400; // less than 9h
              $statusMap = [
                'present'  => 'badge-green',
                'remote'   => 'badge-blue',
                'half_day' => 'badge-yellow',
                'late'     => 'badge-yellow',
                'absent'   => 'badge-red',
              ];
            ?>
            <tr>
              <td class="text-sm font-semibold"><?= date('D, d M Y', strtotime($log['log_date'])) ?></td>
              <td>
                <div class="td-name"><?= htmlspecialchars($log['user_name']) ?></div>
              </td>
              <td><span class="badge badge-gray"><?= ucfirst($log['user_role']) ?></span></td>
              <td class="text-sm"><?= fmtTime($log['clock_in']) ?></td>
              <td class="text-sm"><?= fmtTime($log['clock_out']) ?></td>
              <td>
                <span style="font-size:13px;font-weight:600;color:<?= $short?'var(--red)':'var(--green)' ?>;">
                  <?= fmtHrs($hrs) ?>
                </span>
                <?php if($short && $hrs > 0): ?>
                  <span style="font-size:11px;color:var(--red);margin-left:4px;">(&lt;9h)</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $statusMap[$log['status']] ?? 'badge-gray' ?>"><?= ucfirst(str_replace('_',' ',$log['status'])) ?></span></td>
              <td class="text-muted text-sm"><?= htmlspecialchars($log['location_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── LOCATIONS TAB ── -->
    <div id="tab-locations" style="display:none;">
      <div style="display:grid;grid-template-columns:380px 1fr;gap:20px;align-items:start;">

        <!-- Add location form -->
        <div class="card">
          <div class="card-header"><div><h2>Add Office Location</h2><p>Set GPS coordinates and allowed radius</p></div></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="action" value="add_location">
              <div class="form-group" style="margin-bottom:14px;">
                <label>Location Name <span class="req">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Head Office Mumbai" required>
              </div>
              <div class="form-group" style="margin-bottom:14px;">
                <label>Address</label>
                <input type="text" name="address" class="form-control" placeholder="Full address">
              </div>
              <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-bottom:14px;">
                <div class="form-group">
                  <label>Latitude <span class="req">*</span></label>
                  <input type="number" step="0.0000001" name="latitude" id="inp_lat" class="form-control" placeholder="19.0760" required>
                </div>
                <div class="form-group">
                  <label>Longitude <span class="req">*</span></label>
                  <input type="number" step="0.0000001" name="longitude" id="inp_lng" class="form-control" placeholder="72.8777" required>
                </div>
              </div>
              <div class="form-group" style="margin-bottom:14px;">
                <label>Allowed Radius (metres)</label>
                <input type="number" name="radius_m" class="form-control" value="200" min="50" max="5000">
                <span style="font-size:11.5px;color:var(--muted-light);">Employees must be within this distance to clock in</span>
              </div>
              <div class="form-check" style="margin-bottom:20px;">
                <input type="checkbox" name="is_remote" id="is_remote">
                <label for="is_remote">Remote / Free location (no GPS check)</label>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" style="margin-bottom:12px;width:100%;" onclick="getMyLocation()">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>
                Use My Current Location
              </button>
              <button type="submit" class="btn btn-primary" style="width:100%;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Location
              </button>
            </form>
          </div>
        </div>

        <!-- Locations list -->
        <div class="table-wrap">
          <div class="table-toolbar"><h2>Office Locations</h2></div>
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Address</th>
                <th>Coordinates</th>
                <th>Radius</th>
                <th>Type</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($locations)): ?>
                <tr class="empty-row"><td colspan="7">No locations added yet.</td></tr>
              <?php else: foreach($locations as $loc): ?>
              <tr>
                <td class="font-semibold"><?= htmlspecialchars($loc['name']) ?></td>
                <td class="text-muted text-sm"><?= htmlspecialchars($loc['address'] ?: '—') ?></td>
                <td class="text-sm" style="font-family:monospace;color:var(--muted);">
                  <?= $loc['is_remote'] ? '—' : $loc['latitude'].', '.$loc['longitude'] ?>
                </td>
                <td class="text-sm text-muted"><?= $loc['is_remote'] ? '—' : $loc['radius_m'].'m' ?></td>
                <td>
                  <?php if($loc['is_remote']): ?>
                    <span class="badge badge-blue">Remote</span>
                  <?php else: ?>
                    <span class="badge badge-brand">Office</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $loc['is_active']?'badge-green':'badge-red' ?>">
                    <?= $loc['is_active']?'Active':'Inactive' ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="toggle_location">
                      <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm"><?= $loc['is_active']?'Disable':'Enable' ?></button>
                    </form>
                    <?php if($loc['id'] != 1): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this location?')">
                      <input type="hidden" name="action" value="delete_location">
                      <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                      <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Delete</button>
                    </form>
                    <?php endif; ?>
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
</div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach((b,i) => b.classList.toggle('active', i === (tab==='logs'?0:1)));
  document.getElementById('tab-logs').style.display       = tab === 'logs'      ? 'block' : 'none';
  document.getElementById('tab-locations').style.display  = tab === 'locations' ? 'block' : 'none';
}

function getMyLocation() {
  if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
  navigator.geolocation.getCurrentPosition(pos => {
    document.getElementById('inp_lat').value = pos.coords.latitude.toFixed(7);
    document.getElementById('inp_lng').value = pos.coords.longitude.toFixed(7);
  }, () => alert('Could not get location. Please enter manually.'));
}

// Auto-switch to locations tab if hash
if (location.hash === '#locations') switchTab('locations');
</script>
</body>
</html>
