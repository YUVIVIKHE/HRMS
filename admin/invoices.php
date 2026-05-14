<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

// Ensure table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `project_invoices` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `invoice_no` VARCHAR(30) NOT NULL,
      `project_id` INT UNSIGNED NOT NULL,
      `invoice_date` DATE NOT NULL,
      `due_date` DATE NULL,
      `total_hours` DECIMAL(10,2) NOT NULL DEFAULT 0,
      `utilized_hours` DECIMAL(10,2) NOT NULL DEFAULT 0,
      `rate_per_hour` DECIMAL(10,2) NOT NULL DEFAULT 0,
      `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
      `tax_percent` DECIMAL(5,2) NOT NULL DEFAULT 18,
      `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
      `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
      `notes` TEXT NULL,
      `status` ENUM('draft','sent','paid') NOT NULL DEFAULT 'draft',
      `created_by` INT UNSIGNED NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_invoice_no` (`invoice_no`),
      INDEX `idx_inv_project` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Exception $e) {}

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Generate invoice number
function genInvoiceNo($db) {
    $year = date('Y');
    $last = $db->query("SELECT invoice_no FROM project_invoices WHERE invoice_no LIKE 'INV-$year-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num = $last ? (int)substr($last, -4) + 1 : 1;
    return 'INV-' . $year . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// POST: Create invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'create_invoice') {
    $projId = (int)($_POST['project_id'] ?? 0);
    $invDate = trim($_POST['invoice_date'] ?? date('Y-m-d'));
    $dueDate = trim($_POST['due_date'] ?? '');
    $utilHrs = (float)($_POST['utilized_hours'] ?? 0);
    $rate    = (float)($_POST['rate_per_hour'] ?? 0);
    $taxPct  = (float)($_POST['tax_percent'] ?? 18);
    $notes   = trim($_POST['notes'] ?? '');

    if (!$projId || !$utilHrs || !$rate) { $errorMsg = 'Project, hours, and rate are required.'; }
    else {
        $proj = $db->prepare("SELECT total_hours FROM projects WHERE id=?");
        $proj->execute([$projId]); $proj = $proj->fetch();
        $totalHrs = $proj ? (float)$proj['total_hours'] : 0;

        $subtotal = $utilHrs * $rate;
        $taxAmt = round($subtotal * $taxPct / 100, 2);
        $totalAmt = $subtotal + $taxAmt;
        $invNo = genInvoiceNo($db);

        $db->prepare("INSERT INTO project_invoices (invoice_no, project_id, invoice_date, due_date, total_hours, utilized_hours, rate_per_hour, subtotal, tax_percent, tax_amount, total_amount, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$invNo, $projId, $invDate, $dueDate ?: null, $totalHrs, $utilHrs, $rate, $subtotal, $taxPct, $taxAmt, $totalAmt, $notes ?: null, $_SESSION['user_id']]);
        $_SESSION['flash_success'] = "Invoice $invNo created — ₹" . number_format($totalAmt, 0);
        header("Location: invoices.php"); exit;
    }
}

// POST: Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete_invoice') {
    $db->prepare("DELETE FROM project_invoices WHERE id=?")->execute([(int)$_POST['invoice_id']]);
    $_SESSION['flash_success'] = "Invoice deleted.";
    header("Location: invoices.php"); exit;
}

// POST: Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'update_status') {
    $st = $_POST['status'] ?? '';
    if (in_array($st, ['draft','sent','paid'])) {
        $db->prepare("UPDATE project_invoices SET status=? WHERE id=?")->execute([$st, (int)$_POST['invoice_id']]);
    }
    header("Location: invoices.php"); exit;
}

// Filters
$filterProject = (int)($_GET['project_id'] ?? 0);
$filterStatus  = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

$projects = $db->query("SELECT id, project_name, project_code, total_hours, hr_rate FROM projects ORDER BY project_name")->fetchAll();

$where = ["1=1"]; $params = [];
if ($filterProject) { $where[] = "pi.project_id=?"; $params[] = $filterProject; }
if ($filterStatus) { $where[] = "pi.status=?"; $params[] = $filterStatus; }
if ($search) { $where[] = "(pi.invoice_no LIKE ? OR p.project_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$invoices = $db->prepare("
    SELECT pi.*, p.project_name, p.project_code
    FROM project_invoices pi
    JOIN projects p ON pi.project_id = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY pi.created_at DESC
");
$invoices->execute($params);
$invoices = $invoices->fetchAll();

$totalRevenue = array_sum(array_column($invoices, 'total_amount'));
$paidAmount = array_sum(array_map(fn($i) => $i['status']==='paid' ? (float)$i['total_amount'] : 0, $invoices));
$pendingAmount = $totalRevenue - $paidAmount;
$statusBadge = ['draft'=>'badge-gray','sent'=>'badge-yellow','paid'=>'badge-green'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoices – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left"><span class="page-title">Invoices</span><span class="page-breadcrumb">Project billing</span></div>
    <div class="topbar-right"><span class="role-chip">Admin</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">
    <?php if($successMsg): ?><div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <!-- Create Invoice -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2>Create Invoice</h2></div>
      <div class="card-body">
        <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <input type="hidden" name="action" value="create_invoice">
          <div class="form-group" style="min-width:180px;">
            <label>Project <span class="req">*</span></label>
            <select name="project_id" id="invProject" class="form-control" required onchange="fillProjectData()">
              <option value="">— Select —</option>
              <?php foreach($projects as $p): ?>
                <option value="<?= $p['id'] ?>" data-hours="<?= $p['total_hours'] ?>" data-rate="<?= $p['hr_rate'] ?>"><?= htmlspecialchars($p['project_name']) ?> (<?= $p['project_code'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="width:100px;">
            <label>Utilized Hrs <span class="req">*</span></label>
            <input type="number" name="utilized_hours" id="invHrs" class="form-control" min="0.5" step="0.5" required oninput="calcInv()">
          </div>
          <div class="form-group" style="width:100px;">
            <label>Rate/Hr (₹) <span class="req">*</span></label>
            <input type="number" name="rate_per_hour" id="invRate" class="form-control" min="0" step="1" required oninput="calcInv()">
          </div>
          <div class="form-group" style="width:80px;">
            <label>Tax %</label>
            <input type="number" name="tax_percent" id="invTax" class="form-control" value="18" min="0" max="100" step="0.5" oninput="calcInv()">
          </div>
          <div class="form-group" style="width:130px;">
            <label>Invoice Date</label>
            <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group" style="width:130px;">
            <label>Due Date</label>
            <input type="date" name="due_date" class="form-control">
          </div>
          <div class="form-group" style="min-width:150px;flex:1;">
            <label>Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Optional…">
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;min-width:100px;">
            <span style="font-size:11px;color:var(--muted);">Total</span>
            <span id="invTotal" style="font-size:16px;font-weight:800;color:var(--brand);">₹0</span>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="height:38px;">Create</button>
        </form>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px;">
      <div class="stat-card"><div class="stat-body"><div class="stat-value"><?= count($invoices) ?></div><div class="stat-label">Invoices</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--brand);">₹<?= number_format($totalRevenue,0) ?></div><div class="stat-label">Total Revenue</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--green-text);">₹<?= number_format($paidAmount,0) ?></div><div class="stat-label">Paid</div></div></div>
      <div class="stat-card"><div class="stat-body"><div class="stat-value" style="color:var(--yellow);">₹<?= number_format($pendingAmount,0) ?></div><div class="stat-label">Pending</div></div></div>
    </div>

    <!-- Filters -->
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
      <select name="project_id" class="form-control" style="font-size:12px;padding:7px 10px;width:auto;min-width:180px;" onchange="this.form.submit()">
        <option value="">All Projects</option>
        <?php foreach($projects as $p): ?><option value="<?= $p['id'] ?>" <?= $filterProject==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['project_name']) ?></option><?php endforeach; ?>
      </select>
      <select name="status" class="form-control" style="font-size:12px;padding:7px 10px;width:auto;" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="draft" <?= $filterStatus==='draft'?'selected':'' ?>>Draft</option>
        <option value="sent" <?= $filterStatus==='sent'?'selected':'' ?>>Sent</option>
        <option value="paid" <?= $filterStatus==='paid'?'selected':'' ?>>Paid</option>
      </select>
      <div class="search-box" style="min-width:160px;flex:0 1 200px;">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search invoice…" onchange="this.form.submit()">
      </div>
      <a href="export_invoices.php?project_id=<?= $filterProject ?>&status=<?= urlencode($filterStatus) ?>&q=<?= urlencode($search) ?>" class="btn btn-sm" style="margin-left:auto;background:var(--green-bg);color:var(--green-text);border:1px solid #a7f3d0;font-weight:700;">Export</a>
    </form>

    <!-- Table -->
    <div class="table-wrap">
      <div class="table-toolbar"><h2>Invoices <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($invoices) ?>)</span></h2></div>
      <table>
        <thead><tr><th>Invoice #</th><th>Project</th><th>Date</th><th style="text-align:center;">Hours</th><th style="text-align:center;">Rate</th><th style="text-align:right;">Amount</th><th>Status</th><th style="width:130px;"></th></tr></thead>
        <tbody>
          <?php if(empty($invoices)): ?><tr class="empty-row"><td colspan="8">No invoices yet.</td></tr>
          <?php else: foreach($invoices as $inv): ?>
          <tr>
            <td class="font-semibold" style="font-family:monospace;"><?= htmlspecialchars($inv['invoice_no']) ?></td>
            <td><code style="font-size:11px;background:var(--surface-2);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($inv['project_code']) ?></code> <span class="text-sm"><?= htmlspecialchars($inv['project_name']) ?></span></td>
            <td class="text-sm"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
            <td style="text-align:center;font-weight:700;"><?= number_format($inv['utilized_hours'],1) ?></td>
            <td style="text-align:center;">₹<?= number_format($inv['rate_per_hour'],0) ?></td>
            <td style="text-align:right;font-weight:800;color:var(--brand);">₹<?= number_format($inv['total_amount'],0) ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                <select name="status" class="form-control" style="font-size:11px;padding:4px 8px;width:auto;" onchange="this.form.submit()">
                  <?php foreach(['draft','sent','paid'] as $st): ?><option value="<?= $st ?>" <?= $inv['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                <input type="hidden" name="action" value="delete_invoice">
                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                <button type="submit" class="btn btn-sm" style="background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;font-size:11px;">Del</button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<script>
function fillProjectData() {
  const sel = document.getElementById('invProject');
  const opt = sel.options[sel.selectedIndex];
  if (opt.dataset.rate) document.getElementById('invRate').value = opt.dataset.rate;
  calcInv();
}
function calcInv() {
  const hrs = parseFloat(document.getElementById('invHrs').value) || 0;
  const rate = parseFloat(document.getElementById('invRate').value) || 0;
  const tax = parseFloat(document.getElementById('invTax').value) || 0;
  const sub = hrs * rate;
  const total = sub + (sub * tax / 100);
  document.getElementById('invTotal').textContent = '₹' + Math.round(total).toLocaleString('en-IN');
}
</script>
</body>
</html>
