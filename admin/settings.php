<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

// Ensure settings table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `setting_key` VARCHAR(100) NOT NULL,
      `setting_value` TEXT NULL,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Exception $e) {}

// Helper functions
function getSetting($db, $key, $default = '') {
    $s = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=?");
    $s->execute([$key]); $v = $s->fetchColumn();
    return $v !== false ? $v : $default;
}
function saveSetting($db, $key, $value) {
    $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$key, $value, $value]);
}

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// POST: Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'save_settings') {
    $fields = ['company_name','company_address','company_phone','company_email','company_website',
               'work_hours_per_day','work_start_time','late_threshold','ot_threshold_hours',
               'currency_symbol','financial_year_start','payslip_note'];
    foreach ($fields as $f) {
        saveSetting($db, $f, trim($_POST[$f] ?? ''));
    }
    $_SESSION['flash_success'] = "Settings saved.";
    header("Location: settings.php"); exit;
}

// Load current settings
$settings = [];
$keys = ['company_name','company_address','company_phone','company_email','company_website',
         'work_hours_per_day','work_start_time','late_threshold','ot_threshold_hours',
         'currency_symbol','financial_year_start','payslip_note'];
foreach ($keys as $k) { $settings[$k] = getSetting($db, $k); }

// Defaults
if (!$settings['work_hours_per_day']) $settings['work_hours_per_day'] = '9';
if (!$settings['work_start_time']) $settings['work_start_time'] = '09:30';
if (!$settings['late_threshold']) $settings['late_threshold'] = '09:30';
if (!$settings['ot_threshold_hours']) $settings['ot_threshold_hours'] = '2';
if (!$settings['currency_symbol']) $settings['currency_symbol'] = '₹';
if (!$settings['financial_year_start']) $settings['financial_year_start'] = 'April';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings – HRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
  <header class="topbar">
    <div class="topbar-left"><span class="page-title">Settings</span><span class="page-breadcrumb">System Configuration</span></div>
    <div class="topbar-right"><span class="role-chip">Admin</span><div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div><span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  </header>
  <div class="page-body">
    <?php if($successMsg): ?><div class="alert alert-success"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <form method="POST">
      <input type="hidden" name="action" value="save_settings">

      <!-- Company Info -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2>Company Information</h2></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Company Name</label><input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name']) ?>" placeholder="Your Company Name"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone']) ?>" placeholder="+91 XXXXXXXXXX"></div>
            <div class="form-group"><label>Email</label><input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email']) ?>" placeholder="info@company.com"></div>
            <div class="form-group"><label>Website</label><input type="text" name="company_website" class="form-control" value="<?= htmlspecialchars($settings['company_website']) ?>" placeholder="https://company.com"></div>
          </div>
          <div class="form-group" style="margin-top:12px;"><label>Address</label><textarea name="company_address" class="form-control" rows="2" placeholder="Full company address…"><?= htmlspecialchars($settings['company_address']) ?></textarea></div>
        </div>
      </div>

      <!-- Attendance Settings -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2>Attendance & Work Hours</h2></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Work Hours Per Day</label><input type="number" name="work_hours_per_day" class="form-control" value="<?= htmlspecialchars($settings['work_hours_per_day']) ?>" min="1" max="24" step="0.5"></div>
            <div class="form-group"><label>Work Start Time</label><input type="time" name="work_start_time" class="form-control" value="<?= htmlspecialchars($settings['work_start_time']) ?>"></div>
            <div class="form-group"><label>Late Threshold (after this = late)</label><input type="time" name="late_threshold" class="form-control" value="<?= htmlspecialchars($settings['late_threshold']) ?>"></div>
            <div class="form-group"><label>OT Threshold (min extra hrs for ACL)</label><input type="number" name="ot_threshold_hours" class="form-control" value="<?= htmlspecialchars($settings['ot_threshold_hours']) ?>" min="1" max="8" step="0.5"></div>
          </div>
        </div>
      </div>

      <!-- Payroll Settings -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2>Payroll & Finance</h2></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Currency Symbol</label><input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol']) ?>" style="width:80px;"></div>
            <div class="form-group"><label>Financial Year Starts</label>
              <select name="financial_year_start" class="form-control">
                <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                  <option <?= $settings['financial_year_start']===$m?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;"><label>Payslip Footer Note</label><input type="text" name="payslip_note" class="form-control" value="<?= htmlspecialchars($settings['payslip_note']) ?>" placeholder="e.g. Computer generated salary slip. Signature not required."></div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;padding-bottom:32px;">
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </div>
    </form>
  </div>
</div>
</div>
</body>
</html>
