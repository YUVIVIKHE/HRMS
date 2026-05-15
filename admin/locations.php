<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── POST handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_location') {
        $name     = trim($_POST['name'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $lat      = (float)($_POST['latitude']  ?? 0);
        $lng      = (float)($_POST['longitude'] ?? 0);
        $radius   = max(50, (int)($_POST['radius_m'] ?? 200));
        $isRemote = isset($_POST['is_remote']) ? 1 : 0;
        if ($name) {
            $db->prepare("INSERT INTO attendance_locations (name,address,latitude,longitude,radius_m,is_remote) VALUES (?,?,?,?,?,?)")
               ->execute([$name,$address,$lat,$lng,$radius,$isRemote]);
            $_SESSION['flash_success'] = "Location '$name' added.";
        }
        header("Location: locations.php"); exit;
    }

    if ($action === 'edit_location') {
        $lid      = (int)$_POST['location_id'];
        $name     = trim($_POST['name'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $lat      = (float)($_POST['latitude']  ?? 0);
        $lng      = (float)($_POST['longitude'] ?? 0);
        $radius   = max(50, (int)($_POST['radius_m'] ?? 200));
        $isRemote = isset($_POST['is_remote']) ? 1 : 0;
        if ($name && $lid) {
            $db->prepare("UPDATE attendance_locations SET name=?,address=?,latitude=?,longitude=?,radius_m=?,is_remote=? WHERE id=?")
               ->execute([$name,$address,$lat,$lng,$radius,$isRemote,$lid]);
            $_SESSION['flash_success'] = "Location updated.";
        }
        header("Location: locations.php"); exit;
    }

    if ($action === 'toggle_location') {
        $lid = (int)$_POST['location_id'];
        $db->prepare("UPDATE attendance_locations SET is_active = NOT is_active WHERE id=?")->execute([$lid]);
        header("Location: locations.php"); exit;
    }

    if ($action === 'delete_location') {
        $lid = (int)$_POST['location_id'];
        $db->prepare("DELETE FROM attendance_locations WHERE id=? AND id!=1")->execute([$lid]);
        $_SESSION['flash_success'] = "Location deleted.";
        header("Location: locations.php"); exit;
    }

    if ($action === 'assign_locations') {
        $locId   = (int)($_POST['assign_location_id'] ?? 0);
        $userIds = array_map('intval', $_POST['user_ids'] ?? []);
        if ($locId > 0 && !empty($userIds)) {
            $stmt = $db->prepare("INSERT IGNORE INTO user_locations (user_id,location_id) VALUES (?,?)");
            foreach ($userIds as $uid) $stmt->execute([$uid,$locId]);
            $_SESSION['flash_success'] = count($userIds)." user(s) assigned.";
        }
        header("Location: locations.php"); exit;
    }

    if ($action === 'remove_user_location') {
        $uid = (int)$_POST['user_id'];
        $lid = (int)$_POST['location_id'];
        $db->prepare("DELETE FROM user_locations WHERE user_id=? AND location_id=?")->execute([$uid,$lid]);
        header("Location: locations.php"); exit;
    }
}

// ── Data ─────────────────────────────────────────────────────
$locations = $db->query("SELECT * FROM attendance_locations ORDER BY is_remote ASC, name ASC")->fetchAll();
$users     = $db->query("SELECT u.id, u.name, u.email, u.role FROM users u INNER JOIN employees e ON e.email = u.email WHERE u.role IN ('employee','manager') AND u.status='active' ORDER BY u.name")->fetchAll();

// Build user→locations map
$userLocMap = [];
$ulRows = $db->query("SELECT ul.user_id, ul.location_id, al.name AS loc_name, al.is_remote FROM user_locations ul JOIN attendance_locations al ON ul.location_id = al.id")->fetchAll();
foreach ($ulRows as $r) {
    $userLocMap[$r['user_id']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Office Locations – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">

  <header class="topbar">
    <div class="topbar-left">
      <span class="page-title">Office Locations</span>
      <span class="page-breadcrumb">Manage locations &amp; user assignments</span>
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
        <h1>Office Locations</h1>
        <p>Define office locations, assign employees to specific locations, or allow remote work.</p>
      </div>
      <div class="page-header-actions">
        <a href="attendance.php" class="btn btn-secondary">← Back to Attendance</a>
        <button class="btn btn-primary" onclick="openAddModal()">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Location
        </button>
      </div>
    </div>

    <!-- ── SECTION 1: User Location Assignments ── -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header">
        <div>
          <h2>User Location Assignments</h2>
          <p>Select users and assign them to a location. Users with no assignment can clock in from any active location.</p>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" id="assignForm">
          <input type="hidden" name="action" value="assign_locations">
          <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;min-width:240px;">
              <label style="font-size:12px;font-weight:600;">Assign to Location</label>
              <select name="assign_location_id" class="form-control" required>
                <option value="">Select location…</option>
                <?php foreach($locations as $loc): if(!$loc['is_active']) continue; ?>
                  <option value="<?= $loc['id'] ?>">
                    <?= htmlspecialchars($loc['name']) ?> <?= $loc['is_remote']?'(Remote)':'(Office)' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:1px;">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Assign Selected
            </button>
            <button type="button" class="btn btn-secondary" onclick="clearSel()" style="margin-bottom:1px;">Clear</button>
          </div>

          <div class="table-wrap" style="box-shadow:none;max-height:420px;overflow-y:auto;">
            <div class="table-toolbar" style="padding:10px 16px;position:sticky;top:0;z-index:3;background:var(--surface);border-bottom:1px solid var(--border-light);">
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="selAll" style="width:15px;height:15px;accent-color:var(--brand);cursor:pointer;" onchange="toggleAll(this)">
                <span style="font-size:13px;font-weight:600;color:var(--text-2);">Select All</span>
              </div>
              <div class="search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search users…" oninput="filterUsers(this.value)">
              </div>
            </div>
            <table>
              <thead style="position:sticky;top:0;z-index:2;">
                <tr>
                  <th style="width:40px;"></th>
                  <th>User</th>
                  <th>Role</th>
                  <th>Assigned Locations</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($users as $u):
                  $assigned = $userLocMap[$u['id']] ?? [];
                ?>
                <tr class="urow" data-name="<?= htmlspecialchars(strtolower($u['name'])) ?>">
                  <td style="padding-left:16px;">
                    <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="uchk"
                      style="width:15px;height:15px;accent-color:var(--brand);cursor:pointer;">
                  </td>
                  <td>
                    <div class="td-user">
                      <div class="td-avatar"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                      <div>
                        <div class="td-name"><?= htmlspecialchars($u['name']) ?></div>
                        <div class="td-sub"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge <?= $u['role']==='manager'?'badge-brand':'badge-gray' ?>"><?= ucfirst($u['role']) ?></span></td>
                  <td>
                    <?php if(empty($assigned)): ?>
                      <span style="font-size:12.5px;color:var(--muted-light);">Global — any location</span>
                    <?php else: ?>
                      <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?php foreach($assigned as $a): ?>
                          <span class="badge <?= $a['is_remote']?'badge-blue':'badge-brand' ?>" style="font-size:11px;">
                            <?= htmlspecialchars($a['loc_name']) ?>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if(!empty($assigned)): ?>
                      <button type="button" class="btn btn-ghost btn-sm" onclick="openRemoveModal(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')">
                        Remove
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </form>
      </div>
    </div>

    <!-- ── SECTION 2: Locations List ── -->
    <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
      <div class="table-toolbar" style="position:sticky;top:0;z-index:2;background:var(--surface);">
        <h2>All Locations <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($locations) ?>)</span></h2>
      </div>
      <table>
        <thead style="position:sticky;top:56px;z-index:2;">
          <tr>
            <th>Name</th>
            <th>Address</th>
            <th>Coordinates</th>
            <th>Radius</th>
            <th>Type</th>
            <th>Status</th>
            <th>Assigned Users</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($locations)): ?>
            <tr class="empty-row"><td colspan="8">No locations yet. Click "Add Location" to create one.</td></tr>
          <?php else: foreach($locations as $loc):
            $cnt = $db->prepare("SELECT COUNT(*) FROM user_locations WHERE location_id=?");
            $cnt->execute([$loc['id']]); $cnt = (int)$cnt->fetchColumn();
          ?>
          <tr>
            <td class="font-semibold"><?= htmlspecialchars($loc['name']) ?></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($loc['address'] ?: '—') ?></td>
            <td class="text-sm" style="font-family:monospace;color:var(--muted);">
              <?= $loc['is_remote'] ? '—' : htmlspecialchars($loc['latitude'].', '.$loc['longitude']) ?>
            </td>
            <td class="text-sm text-muted"><?= $loc['is_remote'] ? '—' : $loc['radius_m'].'m' ?></td>
            <td><?= $loc['is_remote'] ? '<span class="badge badge-blue">Remote</span>' : '<span class="badge badge-brand">Office</span>' ?></td>
            <td><span class="badge <?= $loc['is_active']?'badge-green':'badge-red' ?>"><?= $loc['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <?php if($cnt > 0): ?>
                <span class="badge badge-gray"><?= $cnt ?> user<?= $cnt>1?'s':'' ?></span>
              <?php else: ?>
                <span style="font-size:12px;color:var(--muted-light);">Global</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <button type="button" class="btn btn-ghost btn-sm"
                  onclick="openEditModal(<?= htmlspecialchars(json_encode($loc), ENT_QUOTES) ?>)">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </button>
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

<!-- ── Add Location Modal ── -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Add Office Location</h3>
      <button class="modal-close" onclick="closeModal('addModal')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_location">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:14px;">
          <label>Location Name <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="e.g. Head Office Mumbai" required>
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Address</label>
          <input type="text" name="address" class="form-control" placeholder="Full address">
        </div>
        <div id="gpsFields">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div class="form-group">
              <label>Latitude</label>
              <input type="number" step="0.0000001" name="latitude" id="add_lat" class="form-control" placeholder="19.0760">
            </div>
            <div class="form-group">
              <label>Longitude</label>
              <input type="number" step="0.0000001" name="longitude" id="add_lng" class="form-control" placeholder="72.8777">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:14px;">
            <label>Allowed Radius (metres)</label>
            <input type="number" name="radius_m" class="form-control" value="200" min="50" max="5000">
            <span style="font-size:11.5px;color:var(--muted-light);">Employees must be within this distance to clock in</span>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" style="width:100%;margin-bottom:14px;" onclick="getMyLoc('add_lat','add_lng')">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>
            Use My Current Location
          </button>
          <!-- Map -->
          <div id="addMapWrap" style="border-radius:8px;overflow:hidden;border:1px solid var(--border);margin-bottom:14px;">
            <div id="addMap" style="height:200px;width:100%;"></div>
          </div>
          <span style="font-size:11px;color:var(--muted);">Click on the map to set location, or drag the marker.</span>
        </div>
        <div class="form-check">
          <input type="checkbox" name="is_remote" id="add_remote" onchange="toggleGps(this,'gpsFields')">
          <label for="add_remote">Remote / Work from Home (no GPS check)</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Location</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Location Modal ── -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Location</h3>
      <button class="modal-close" onclick="closeModal('editModal')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_location">
      <input type="hidden" name="location_id" id="edit_loc_id">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:14px;">
          <label>Location Name <span class="req">*</span></label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Address</label>
          <input type="text" name="address" id="edit_address" class="form-control">
        </div>
        <div id="editGpsFields">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div class="form-group">
              <label>Latitude</label>
              <input type="number" step="0.0000001" name="latitude" id="edit_lat" class="form-control">
            </div>
            <div class="form-group">
              <label>Longitude</label>
              <input type="number" step="0.0000001" name="longitude" id="edit_lng" class="form-control">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:14px;">
            <label>Allowed Radius (metres)</label>
            <input type="number" name="radius_m" id="edit_radius" class="form-control" min="50" max="5000">
          </div>
          <button type="button" class="btn btn-secondary btn-sm" style="width:100%;margin-bottom:14px;" onclick="getMyLoc('edit_lat','edit_lng')">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>
            Use My Current Location
          </button>
        </div>
        <div class="form-check">
          <input type="checkbox" name="is_remote" id="edit_remote" onchange="toggleGps(this,'editGpsFields')">
          <label for="edit_remote">Remote / Work from Home (no GPS check)</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Remove User Location Modal ── -->
<div class="modal-overlay" id="removeModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3>Remove Location Assignment</h3>
      <button class="modal-close" onclick="closeModal('removeModal')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="remove_user_location">
      <input type="hidden" name="user_id" id="rm_uid">
      <div class="modal-body">
        <p style="font-size:13.5px;color:var(--muted);margin-bottom:16px;" id="rm_msg"></p>
        <div class="form-group">
          <label>Remove from Location</label>
          <select name="location_id" id="rm_loc_select" class="form-control" required></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('removeModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Remove</button>
      </div>
    </form>
  </div>
</div>

<?php
// Build JS-friendly user→locations map
$jsMap = [];
foreach ($userLocMap as $uid => $locs) {
    $jsMap[$uid] = array_map(fn($l) => ['id' => $l['location_id'], 'name' => $l['loc_name']], $locs);
}
?>
<script>
const userLocMap = <?= json_encode($jsMap) ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));

function openAddModal() { openModal('addModal'); }

function openEditModal(loc) {
  document.getElementById('edit_loc_id').value  = loc.id;
  document.getElementById('edit_name').value    = loc.name;
  document.getElementById('edit_address').value = loc.address || '';
  document.getElementById('edit_lat').value     = loc.latitude;
  document.getElementById('edit_lng').value     = loc.longitude;
  document.getElementById('edit_radius').value  = loc.radius_m;
  document.getElementById('edit_remote').checked = !!parseInt(loc.is_remote);
  toggleGps(document.getElementById('edit_remote'), 'editGpsFields');
  openModal('editModal');
}

function openRemoveModal(uid, name) {
  document.getElementById('rm_uid').value = uid;
  document.getElementById('rm_msg').textContent = 'Remove a location assignment from ' + name + '.';
  const sel = document.getElementById('rm_loc_select');
  sel.innerHTML = '';
  const locs = userLocMap[uid] || [];
  locs.forEach(l => {
    const opt = document.createElement('option');
    opt.value = l.id; opt.textContent = l.name;
    sel.appendChild(opt);
  });
  openModal('removeModal');
}

function toggleGps(cb, containerId) {
  const c = document.getElementById(containerId);
  if (c) c.style.display = cb.checked ? 'none' : 'block';
}

function getMyLoc(latId, lngId) {
  if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
  navigator.geolocation.getCurrentPosition(
    p => { document.getElementById(latId).value = p.coords.latitude.toFixed(7); document.getElementById(lngId).value = p.coords.longitude.toFixed(7); },
    () => alert('Could not get location.')
  );
}

function toggleAll(master) {
  document.querySelectorAll('.uchk').forEach(cb => { if(cb.closest('tr').style.display!=='none') cb.checked = master.checked; });
}
function clearSel() {
  document.querySelectorAll('.uchk').forEach(cb => cb.checked = false);
  document.getElementById('selAll').checked = false;
}
function filterUsers(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.urow').forEach(r => { r.style.display = !q || r.dataset.name.includes(q) ? '' : 'none'; });
}
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Map for Add Location
let addMap, addMarker;
function initAddMap() {
  if (addMap) return;
  const lat = parseFloat(document.getElementById('add_lat').value) || 19.076;
  const lng = parseFloat(document.getElementById('add_lng').value) || 72.8777;
  addMap = L.map('addMap').setView([lat, lng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
  }).addTo(addMap);
  addMarker = L.marker([lat, lng], {draggable: true}).addTo(addMap);

  // Click on map to move marker
  addMap.on('click', function(e) {
    addMarker.setLatLng(e.latlng);
    document.getElementById('add_lat').value = e.latlng.lat.toFixed(7);
    document.getElementById('add_lng').value = e.latlng.lng.toFixed(7);
  });

  // Drag marker
  addMarker.on('dragend', function() {
    const pos = addMarker.getLatLng();
    document.getElementById('add_lat').value = pos.lat.toFixed(7);
    document.getElementById('add_lng').value = pos.lng.toFixed(7);
  });
}

// Initialize map when add modal opens
const addModal = document.getElementById('addModal');
if (addModal) {
  const observer = new MutationObserver(function() {
    if (addModal.classList.contains('open')) {
      setTimeout(() => { initAddMap(); addMap.invalidateSize(); }, 200);
    }
  });
  observer.observe(addModal, {attributes: true, attributeFilter: ['class']});
}

// Update map when lat/lng fields change manually
document.getElementById('add_lat')?.addEventListener('change', function() {
  if (!addMap) return;
  const lat = parseFloat(this.value) || 0;
  const lng = parseFloat(document.getElementById('add_lng').value) || 0;
  if (lat && lng) { addMarker.setLatLng([lat, lng]); addMap.setView([lat, lng], 15); }
});
document.getElementById('add_lng')?.addEventListener('change', function() {
  if (!addMap) return;
  const lat = parseFloat(document.getElementById('add_lat').value) || 0;
  const lng = parseFloat(this.value) || 0;
  if (lat && lng) { addMarker.setLatLng([lat, lng]); addMap.setView([lat, lng], 15); }
});

// Override getMyLoc to also update map
const origGetMyLoc = getMyLoc;
getMyLoc = function(latId, lngId) {
  if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
  navigator.geolocation.getCurrentPosition(
    p => {
      document.getElementById(latId).value = p.coords.latitude.toFixed(7);
      document.getElementById(lngId).value = p.coords.longitude.toFixed(7);
      if (addMap && latId === 'add_lat') {
        addMarker.setLatLng([p.coords.latitude, p.coords.longitude]);
        addMap.setView([p.coords.latitude, p.coords.longitude], 16);
      }
    },
    () => alert('Could not get location.'),
    {enableHighAccuracy: true, timeout: 8000}
  );
};
</script>
</body>
</html>
