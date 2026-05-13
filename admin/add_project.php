<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$editId  = (int)($_GET['id'] ?? 0);
$project = null;
if ($editId) {
    $s = $db->prepare("SELECT * FROM projects WHERE id=?");
    $s->execute([$editId]); $project = $s->fetch();
    if (!$project) { header("Location: projects.php"); exit; }
}

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Auto-generate project code ────────────────────────────────
function generateProjectCode(PDO $db): string {
    $last = $db->query("SELECT project_code FROM projects WHERE project_code REGEXP '^PRJ[0-9]+$' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int)substr($last, 3) + 1 : 1;
    return 'PRJ' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// ── Calculate working hours (excl. Sundays + holidays) ────────
function calcWorkingHours(PDO $db, string $start, string $end): float {
    if (!$start || !$end || $start > $end) return 0;

    // Fetch holidays in range
    $hols = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $hols->execute([$start, $end]);
    $holSet = array_flip($hols->fetchAll(PDO::FETCH_COLUMN));

    $days = 0;
    $d = new DateTime($start);
    $e = new DateTime($end);
    while ($d <= $e) {
        $dow  = (int)$d->format('N'); // 1=Mon … 7=Sun
        $date = $d->format('Y-m-d');
        if ($dow !== 7 && !isset($holSet[$date])) $days++;
        $d->modify('+1 day');
    }
    return round($days * 9, 2); // 9 working hours per day
}

// ── POST: Save ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['project_name']  ?? '');
    $code      = trim($_POST['project_code']  ?? '');
    $client    = trim($_POST['client_name']   ?? '');
    $priority  = in_array($_POST['priority']??'', ['Low','Medium','High','Critical']) ? $_POST['priority'] : 'Medium';
    $managerId = (int)($_POST['manager_id']   ?? 0) ?: null;
    $start     = $_POST['start_date']         ?? '';
    $deadline  = $_POST['deadline_date']      ?? '';
    $hrRate    = max(0, (float)($_POST['hr_rate'] ?? 0));
    $status    = in_array($_POST['status']??'', ['Planning','Active','On Hold','Completed','Cancelled']) ? $_POST['status'] : 'Planning';
    $desc      = trim($_POST['description']   ?? '');
    $errors    = [];

    if (!$name)     $errors[] = 'Project name is required.';
    if (!$code)     $errors[] = 'Project code is required.';
    if (!$start)    $errors[] = 'Start date is required.';
    if (!$deadline) $errors[] = 'Deadline is required.';
    if ($start && $deadline && $start > $deadline) $errors[] = 'Deadline must be after start date.';

    if (empty($errors)) {
        $totalHours = calcWorkingHours($db, $start, $deadline);

        try {
            if ($editId) {
                $db->prepare("UPDATE projects SET project_name=?,project_code=?,client_name=?,priority=?,manager_id=?,start_date=?,deadline_date=?,total_hours=?,hr_rate=?,status=?,description=? WHERE id=?")
                   ->execute([$name,$code,$client?:null,$priority,$managerId,$start,$deadline,$totalHours,$hrRate,$status,$desc?:null,$editId]);
                $_SESSION['flash_success'] = "Project updated. Total working hours: ".number_format($totalHours,1);
            } else {
                $db->prepare("INSERT INTO projects (project_name,project_code,client_name,priority,manager_id,start_date,deadline_date,total_hours,hr_rate,status,description) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$name,$code,$client?:null,$priority,$managerId,$start,$deadline,$totalHours,$hrRate,$status,$desc?:null]);
                $_SESSION['flash_success'] = "Project '$name' created. Total working hours: ".number_format($totalHours,1);
            }
            header("Location: projects.php"); exit;
        } catch (PDOException $e) {
            $errorMsg = strpos($e->getMessage(),'Duplicate') !== false
                ? "Project code '$code' already exists."
                : "Error: ".$e->getMessage();
        }
    } else {
        $errorMsg = implode(' ', $errors);
    }
}

// ── Data ─────────────────────────────────────────────────────
$managers   = $db->query("SELECT id, name FROM users WHERE role='manager' AND status='active' ORDER BY name")->fetchAll();
$nextCode   = $project ? $project['project_code'] : generateProjectCode($db);
$isEdit     = (bool)$project;
$pageTitle  = $isEdit ? 'Edit Project' : 'New Project';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $pageTitle ?> – HRMS Portal</title>
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
      <span class="page-title"><?= $pageTitle ?></span>
      <span class="page-breadcrumb"><a href="projects.php" style="color:var(--muted);text-decoration:none;">Projects</a> / <?= $isEdit ? htmlspecialchars($project['project_name']) : 'New' ?></span>
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

    <form method="POST" id="projectForm" onsubmit="return validateProject()">
      <?php if($isEdit): ?><input type="hidden" name="project_id" value="<?= $editId ?>"><?php endif; ?>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Project Details</h2></div></div>
        <div class="card-body">
          <div class="form-grid">

            <div class="form-group">
              <label>Project Name <span class="req">*</span></label>
              <input type="text" name="project_name" class="form-control" value="<?= htmlspecialchars($project['project_name']??'') ?>" placeholder="e.g. Website Redesign" required>
            </div>

            <div class="form-group">
              <label>Project Code <span class="req">*</span></label>
              <div style="display:flex;gap:8px;">
                <input type="text" name="project_code" id="projCode" class="form-control" value="<?= htmlspecialchars($nextCode) ?>" required style="font-family:monospace;font-weight:700;">
                <?php if(!$isEdit): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="regenCode()" title="Regenerate code" style="flex-shrink:0;">↻</button>
                <?php endif; ?>
              </div>
              <span style="font-size:11.5px;color:var(--muted-light);">Auto-generated. You can customise it.</span>
            </div>

            <div class="form-group">
              <label>Client Name</label>
              <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($project['client_name']??'') ?>" placeholder="e.g. Acme Corp">
            </div>

            <div class="form-group">
              <label>Priority</label>
              <select name="priority" class="form-control">
                <?php foreach(['Low','Medium','High','Critical'] as $p): ?>
                  <option <?= ($project['priority']??'Medium')===$p?'selected':'' ?>><?= $p ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Assign Manager</label>
              <select name="manager_id" class="form-control">
                <option value="">— No Manager —</option>
                <?php foreach($managers as $m): ?>
                  <option value="<?= $m['id'] ?>" <?= ($project['manager_id']??0)==$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Status</label>
              <select name="status" class="form-control">
                <?php foreach(['Planning','Active','On Hold','Completed','Cancelled'] as $s): ?>
                  <option <?= ($project['status']??'Planning')===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Timeline &amp; Hours</h2><p>Working hours are auto-calculated excluding Sundays and company holidays.</p></div></div>
        <div class="card-body">
          <div class="form-grid">

            <div class="form-group">
              <label>Start Date <span class="req">*</span></label>
              <input type="date" name="start_date" id="startDate" class="form-control" value="<?= htmlspecialchars($project['start_date']??'') ?>" required onchange="calcHours()">
            </div>

            <div class="form-group">
              <label>Deadline Date <span class="req">*</span></label>
              <input type="date" name="deadline_date" id="deadlineDate" class="form-control" value="<?= htmlspecialchars($project['deadline_date']??'') ?>" required onchange="calcHours()">
            </div>

            <div class="form-group">
              <label>Total Working Hours</label>
              <div style="display:flex;align-items:center;gap:10px;">
                <input type="text" id="totalHrsDisplay" class="form-control" value="<?= $project?number_format($project['total_hours'],1).' hrs':'' ?>" readonly style="background:var(--surface-2);color:var(--brand);font-weight:700;cursor:not-allowed;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="calcHours()" style="flex-shrink:0;">Recalculate</button>
              </div>
              <span style="font-size:11.5px;color:var(--muted-light);">9 hrs/day × working days (excl. Sundays &amp; holidays)</span>
            </div>

            <div class="form-group">
              <label>HR Rate (₹/hr)</label>
              <input type="number" name="hr_rate" id="hrRate" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($project['hr_rate']??'0') ?>" onchange="calcCost()">
            </div>

            <div class="form-group">
              <label>Estimated Cost</label>
              <input type="text" id="estCost" class="form-control" readonly style="background:var(--surface-2);color:var(--green-text);font-weight:700;cursor:not-allowed;" value="<?= $project?'₹'.number_format($project['total_hours']*$project['hr_rate'],2):'—' ?>">
            </div>

          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Description</h2></div></div>
        <div class="card-body">
          <textarea name="description" class="form-control" rows="4" placeholder="Project description, scope, notes…" style="resize:vertical;"><?= htmlspecialchars($project['description']??'') ?></textarea>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;padding-bottom:32px;">
        <a href="projects.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          <?= $isEdit ? 'Save Changes' : 'Create Project' ?>
        </button>
      </div>
    </form>

  </div>
</div>
</div>

<script>
// ── Calculate working hours via AJAX ─────────────────────────
function calcHours() {
  const start    = document.getElementById('startDate').value;
  const deadline = document.getElementById('deadlineDate').value;
  if (!start || !deadline || start > deadline) {
    document.getElementById('totalHrsDisplay').value = '';
    document.getElementById('estCost').value = '—';
    return;
  }
  document.getElementById('totalHrsDisplay').value = 'Calculating…';
  fetch('calc_project_hours.php?start='+encodeURIComponent(start)+'&end='+encodeURIComponent(deadline))
    .then(r=>r.json())
    .then(d=>{
      document.getElementById('totalHrsDisplay').value = d.hours.toFixed(1)+' hrs ('+d.days+' working days)';
      document.getElementById('totalHrsDisplay').dataset.raw = d.hours;
      calcCost();
    })
    .catch(()=>{ document.getElementById('totalHrsDisplay').value = 'Error'; });
}

function calcCost() {
  const hrs  = parseFloat(document.getElementById('totalHrsDisplay').dataset.raw || 0);
  const rate = parseFloat(document.getElementById('hrRate').value || 0);
  document.getElementById('estCost').value = hrs && rate ? '₹'+( hrs*rate ).toLocaleString('en-IN',{minimumFractionDigits:2}) : '—';
}

function validateProject() {
  const name  = document.querySelector('[name="project_name"]').value.trim();
  const start = document.getElementById('startDate').value;
  const end   = document.getElementById('deadlineDate').value;
  if (!name)       { alert('Project name is required.'); return false; }
  if (!start||!end){ alert('Start and deadline dates are required.'); return false; }
  if (start > end) { alert('Deadline must be after start date.'); return false; }
  return true;
}

let codeCounter = <?= (int)substr($nextCode,3) ?>;
function regenCode() {
  codeCounter++;
  document.getElementById('projCode').value = 'PRJ' + String(codeCounter).padStart(4,'0');
}

// Auto-calc on load if editing
<?php if($isEdit): ?>
document.getElementById('totalHrsDisplay').dataset.raw = <?= $project['total_hours'] ?>;
calcCost();
<?php endif; ?>
</script>
</body>
</html>
