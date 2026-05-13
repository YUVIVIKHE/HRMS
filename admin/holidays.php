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

    // Add single holiday
    if ($action === 'add_holiday') {
        $title = trim($_POST['title'] ?? '');
        $date  = trim($_POST['holiday_date'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        if (!$title || !$date) {
            $_SESSION['flash_error'] = "Title and date are required.";
        } else {
            try {
                $db->prepare("INSERT INTO holidays (title, holiday_date, description) VALUES (?,?,?)")
                   ->execute([$title, $date, $desc ?: null]);
                $_SESSION['flash_success'] = "Holiday '$title' added.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = strpos($e->getMessage(),'Duplicate') !== false
                    ? "A holiday already exists on ".date('d M Y', strtotime($date))."."
                    : "Error: ".$e->getMessage();
            }
        }
        header("Location: holidays.php"); exit;
    }

    // Edit holiday
    if ($action === 'edit_holiday') {
        $id    = (int)$_POST['holiday_id'];
        $title = trim($_POST['title'] ?? '');
        $date  = trim($_POST['holiday_date'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        if (!$title || !$date) {
            $_SESSION['flash_error'] = "Title and date are required.";
        } else {
            try {
                $db->prepare("UPDATE holidays SET title=?, holiday_date=?, description=? WHERE id=?")
                   ->execute([$title, $date, $desc ?: null, $id]);
                $_SESSION['flash_success'] = "Holiday updated.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = strpos($e->getMessage(),'Duplicate') !== false
                    ? "A holiday already exists on that date."
                    : "Error: ".$e->getMessage();
            }
        }
        header("Location: holidays.php"); exit;
    }

    // Delete holiday
    if ($action === 'delete_holiday') {
        $id = (int)$_POST['holiday_id'];
        $db->prepare("DELETE FROM holidays WHERE id=?")->execute([$id]);
        $_SESSION['flash_success'] = "Holiday deleted.";
        header("Location: holidays.php"); exit;
    }

    // Bulk upload CSV
    if ($action === 'bulk_upload') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($handle) {
                $headers = fgetcsv($handle, 1000, ',');
                // Normalise headers
                $headers = array_map(fn($h) => strtolower(trim(str_replace("\xEF\xBB\xBF",'',$h))), $headers);

                $titleIdx = array_search('title', $headers);
                $dateIdx  = array_search('holiday_date', $headers);
                $descIdx  = array_search('description', $headers);

                if ($titleIdx === false || $dateIdx === false) {
                    $_SESSION['flash_error'] = "CSV must have 'title' and 'holiday_date' columns. Found: ".implode(', ',$headers);
                } else {
                    $count = 0; $skipped = 0;
                    $stmt = $db->prepare("INSERT IGNORE INTO holidays (title, holiday_date, description) VALUES (?,?,?)");
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        $t = trim($row[$titleIdx] ?? '');
                        $d = trim($row[$dateIdx]  ?? '');
                        $desc = $descIdx !== false ? trim($row[$descIdx] ?? '') : '';
                        if (!$t || !$d) { $skipped++; continue; }
                        // Accept d-M-Y or d/m/Y or Y-m-d
                        $parsed = date_create($d);
                        if (!$parsed) { $skipped++; continue; }
                        $d = $parsed->format('Y-m-d');
                        $stmt->execute([$t, $d, $desc ?: null]);
                        $count += $stmt->rowCount();
                    }
                    fclose($handle);
                    $_SESSION['flash_success'] = "$count holiday(s) imported.".($skipped?" $skipped row(s) skipped.":'');
                }
            }
        } else {
            $_SESSION['flash_error'] = "File upload error.";
        }
        header("Location: holidays.php"); exit;
    }
}

// ── Data ─────────────────────────────────────────────────────
$year      = (int)($_GET['year'] ?? date('Y'));
$holidays  = $db->prepare("SELECT * FROM holidays WHERE YEAR(holiday_date)=? ORDER BY holiday_date ASC");
$holidays->execute([$year]); $holidays = $holidays->fetchAll();

$years = $db->query("SELECT DISTINCT YEAR(holiday_date) AS y FROM holidays ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($year, $years)) $years[] = $year;
sort($years);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Holidays – HRMS Portal</title>
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
      <span class="page-title">Holidays</span>
      <span class="page-breadcrumb">Manage company holidays</span>
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
        <h1>Holidays <?= $year ?></h1>
        <p>Define company holidays. These are excluded from leave working-day calculations.</p>
      </div>
      <div class="page-header-actions">
        <!-- Year filter -->
        <select class="form-control" style="font-size:13px;padding:7px 12px;min-width:100px;" onchange="location.href='holidays.php?year='+this.value">
          <?php foreach($years as $y): ?>
            <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
        <a href="export_holidays.php?year=<?= $year ?>" class="btn btn-success">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export Excel
        </a>
        <button class="btn btn-secondary" onclick="openModal('bulkModal')">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
          Bulk Upload
        </button>
        <button class="btn btn-primary" onclick="openModal('addModal')">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Holiday
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--brand-light);color:var(--brand);">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-body"><div class="stat-value"><?= count($holidays) ?></div><div class="stat-label">Total Holidays</div><div class="stat-sub"><?= $year ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green);">
          <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count(array_filter($holidays, fn($h) => $h['holiday_date'] >= date('Y-m-d'))) ?></div>
          <div class="stat-label">Upcoming</div><div class="stat-sub">From today</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--yellow-bg);color:var(--yellow);">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-body">
          <div class="stat-value"><?= count(array_filter($holidays, fn($h) => $h['holiday_date'] < date('Y-m-d'))) ?></div>
          <div class="stat-label">Past</div><div class="stat-sub">Already passed</div>
        </div>
      </div>
    </div>

    <!-- Holidays table -->
    <div class="table-wrap">
      <div class="table-toolbar">
        <h2>Holiday List <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($holidays) ?>)</span></h2>
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="hSearch" placeholder="Search holidays…" oninput="filterH(this.value)">
        </div>
      </div>
      <table id="hTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Holiday Title</th>
            <th>Date</th>
            <th>Day</th>
            <th>Description</th>
            <th>Status</th>
            <th style="width:120px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($holidays)): ?>
            <tr class="empty-row"><td colspan="7">No holidays for <?= $year ?>. Click "Add Holiday" to get started.</td></tr>
          <?php else: foreach($holidays as $i => $h):
            $isPast     = $h['holiday_date'] < date('Y-m-d');
            $isToday    = $h['holiday_date'] === date('Y-m-d');
            $isUpcoming = $h['holiday_date'] > date('Y-m-d');
          ?>
          <tr class="h-row" data-name="<?= htmlspecialchars(strtolower($h['title'])) ?>"
              style="<?= $isToday?'background:#fffbeb;':'' ?>">
            <td class="text-muted text-sm"><?= $i+1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <?php if($isToday): ?>
                  <span style="width:8px;height:8px;border-radius:50%;background:var(--yellow);flex-shrink:0;"></span>
                <?php elseif($isUpcoming): ?>
                  <span style="width:8px;height:8px;border-radius:50%;background:var(--green);flex-shrink:0;"></span>
                <?php else: ?>
                  <span style="width:8px;height:8px;border-radius:50%;background:var(--muted-light);flex-shrink:0;"></span>
                <?php endif; ?>
                <span class="font-semibold"><?= htmlspecialchars($h['title']) ?></span>
              </div>
            </td>
            <td class="font-semibold text-sm"><?= date('d M Y', strtotime($h['holiday_date'])) ?></td>
            <td class="text-muted text-sm"><?= date('l', strtotime($h['holiday_date'])) ?></td>
            <td class="text-muted text-sm"><?= htmlspecialchars($h['description'] ?: '—') ?></td>
            <td>
              <?php if($isToday): ?>
                <span class="badge badge-yellow">Today</span>
              <?php elseif($isUpcoming): ?>
                <span class="badge badge-green">Upcoming</span>
              <?php else: ?>
                <span class="badge badge-gray">Past</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <button type="button" class="btn btn-ghost btn-sm"
                  onclick="openEdit(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)">Edit</button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this holiday?')">
                  <input type="hidden" name="action" value="delete_holiday">
                  <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;">Delete</button>
                </form>
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

<!-- Add Holiday Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3>Add Holiday</h3>
      <button class="modal-close" onclick="closeModal('addModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST" onsubmit="return validateHoliday(this)">
      <input type="hidden" name="action" value="add_holiday">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:14px;">
          <label>Holiday Title <span class="req">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Republic Day" required>
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Holiday Date <span class="req">*</span></label>
          <input type="date" name="holiday_date" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Optional description…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Holiday</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Holiday Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3>Edit Holiday</h3>
      <button class="modal-close" onclick="closeModal('editModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form method="POST" onsubmit="return validateHoliday(this)">
      <input type="hidden" name="action" value="edit_holiday">
      <input type="hidden" name="holiday_id" id="edit_id">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:14px;">
          <label>Holiday Title <span class="req">*</span></label>
          <input type="text" name="title" id="edit_title" class="form-control" required>
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Holiday Date <span class="req">*</span></label>
          <input type="date" name="holiday_date" id="edit_date" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal-overlay" id="bulkModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3>Bulk Upload Holidays</h3>
      <button class="modal-close" onclick="closeModal('bulkModal')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-body">
      <div style="background:var(--surface-2);border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;margin-bottom:16px;font-size:13px;">
        <div style="font-weight:700;margin-bottom:6px;">CSV Format Required:</div>
        <code style="font-size:12px;color:var(--brand);">title, holiday_date, description</code>
        <div style="color:var(--muted);margin-top:6px;">Date formats accepted: <code>2025-01-26</code> or <code>26-Jan-2025</code> or <code>26/01/2025</code></div>
        <div style="margin-top:8px;">
          <a href="download_holiday_template.php" class="btn btn-secondary btn-sm">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download Template
          </a>
        </div>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="bulk_upload">
        <div id="bulkDrop" style="border:2px dashed var(--border);border-radius:var(--radius-lg);padding:36px;text-align:center;cursor:pointer;background:var(--surface-2);" onclick="document.getElementById('bulk_csv').click()">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--muted-light)" stroke-width="1.5" style="display:block;margin:0 auto 10px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
          <div id="bulkLabel" style="font-size:13.5px;font-weight:600;color:var(--muted);">Click to select CSV file</div>
          <div style="font-size:12px;color:var(--muted-light);margin-top:4px;">or drag and drop</div>
          <input type="file" id="bulk_csv" name="csv_file" accept=".csv" style="display:none;" onchange="onBulkFile(this)">
        </div>
        <div class="modal-footer" style="padding:16px 0 0;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('bulkModal')">Cancel</button>
          <button type="submit" id="bulkSubmit" class="btn btn-primary" disabled>Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));

function openEdit(h) {
  document.getElementById('edit_id').value    = h.id;
  document.getElementById('edit_title').value = h.title;
  document.getElementById('edit_date').value  = h.holiday_date;
  document.getElementById('edit_desc').value  = h.description || '';
  openModal('editModal');
}

function validateHoliday(form) {
  const title = form.querySelector('[name="title"]').value.trim();
  const date  = form.querySelector('[name="holiday_date"]').value;
  if (!title) { alert('Holiday title is required.'); return false; }
  if (!date)  { alert('Holiday date is required.'); return false; }
  return true;
}

function filterH(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.h-row').forEach(r => { r.style.display = !q || r.dataset.name.includes(q) || r.textContent.toLowerCase().includes(q) ? '' : 'none'; });
}

function onBulkFile(input) {
  if (input.files[0]) {
    document.getElementById('bulkLabel').textContent = input.files[0].name;
    document.getElementById('bulkLabel').style.color = 'var(--text)';
    document.getElementById('bulkSubmit').disabled = false;
  }
}

// Drag and drop for bulk
const drop = document.getElementById('bulkDrop');
drop.addEventListener('dragover', e => { e.preventDefault(); drop.style.borderColor='var(--brand)'; drop.style.background='var(--brand-light)'; });
drop.addEventListener('dragleave', () => { drop.style.borderColor='var(--border)'; drop.style.background='var(--surface-2)'; });
drop.addEventListener('drop', e => {
  e.preventDefault(); drop.style.borderColor='var(--border)'; drop.style.background='var(--surface-2)';
  const f = e.dataTransfer.files[0];
  if (f && f.name.endsWith('.csv')) {
    const dt = new DataTransfer(); dt.items.add(f);
    const inp = document.getElementById('bulk_csv'); inp.files = dt.files; onBulkFile(inp);
  }
});
</script>
</body>
</html>
