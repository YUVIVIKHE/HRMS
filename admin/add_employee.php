<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
$warnMsg    = $_SESSION['flash_warning'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);

$stmt = $db->query("SHOW COLUMNS FROM employees");
$allColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
$deptList = $db->query("SELECT id, name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$baseColumns = ['id','first_name','last_name','email','phone','job_title','date_of_birth','gender','marital_status','employee_id','department_id','employee_type','date_of_joining','date_of_exit','date_of_confirmation','direct_manager_name','location','base_location','user_code','address_line1','address_line2','city','state','zip_code','country','permanent_address_line1','permanent_address_line2','permanent_city','permanent_state','permanent_zip_code','account_type','account_number','ifsc_code','pan','aadhar_no','uan_number','pf_account_number','employee_provident_fund','professional_tax','esi_number','exempt_from_tax','passport_no','place_of_issue','passport_date_of_issue','passport_date_of_expiry','place_of_birth','nationality','blood_group','personal_email','emergency_contact_no','country_code_phone','status','created_at','gross_salary'];
$customColumns = array_diff($allColumns, $baseColumns);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_employee') {
        try {
            $insertCols = []; $placeholders = []; $params = [];
            foreach ($allColumns as $col) {
                if ($col === 'id' || $col === 'created_at') continue;
                if (isset($_POST[$col])) {
                    $insertCols[] = "`$col`"; $placeholders[] = "?";
                    $val = trim($_POST[$col]);
                    $params[] = ($val === '') ? null : $val;
                } elseif ($col === 'exempt_from_tax') {
                    $insertCols[] = "`$col`"; $placeholders[] = "?";
                    $params[] = isset($_POST['exempt_from_tax']) ? 1 : 0;
                }
            }
            if (!empty($insertCols)) {
                $db->prepare("INSERT INTO employees (".implode(',',$insertCols).") VALUES (".implode(',',$placeholders).")")->execute($params);
                $_SESSION['flash_success'] = "Employee added successfully.";
                header("Location: employees.php"); exit;
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error: " . $e->getMessage();
        }
        header("Location: add_employee.php"); exit;
    } elseif ($action === 'bulk_upload') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                $csvHeaders = fgetcsv($handle, 10000, ",");
                if ($csvHeaders) {
                    // Build department name → id lookup (case-insensitive)
                    $deptMap = [];
                    foreach ($db->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC) as $d) {
                        $deptMap[strtolower(trim($d['name']))] = (int)$d['id'];
                    }

                    // Normalise CSV headers
                    $csvHeaders = array_map('trim', $csvHeaders);

                    $successCount = 0;
                    $rowErrors    = [];
                    $rowNum       = 1; // 1 = header row

                    $db->beginTransaction();
                    try {
                        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                            $rowNum++;
                            // Skip completely blank rows
                            if (empty(array_filter($data, fn($v) => trim($v) !== ''))) continue;

                            // Map CSV columns → values
                            $row = [];
                            foreach ($csvHeaders as $i => $h) {
                                $row[$h] = isset($data[$i]) ? trim($data[$i]) : '';
                            }

                            // Validate required fields
                            $missing = [];
                            foreach (['first_name', 'last_name', 'email'] as $req) {
                                if (empty($row[$req])) $missing[] = $req;
                            }
                            if (!empty($missing)) {
                                $rowErrors[] = "Row $rowNum: missing required field(s): " . implode(', ', $missing);
                                continue;
                            }

                            // Resolve department name → department_id
                            if (isset($row['department'])) {
                                $dKey = strtolower($row['department']);
                                $row['department_id'] = $deptMap[$dKey] ?? null;
                                if (!empty($row['department']) && $row['department_id'] === null) {
                                    $rowErrors[] = "Row $rowNum: unknown department '{$row['department']}' — set to NULL.";
                                }
                                unset($row['department']);
                            }

                            // Build INSERT only for known columns, skip id/created_at
                            $ic = []; $ph = []; $pr = [];
                            foreach ($row as $col => $val) {
                                if (!in_array($col, $allColumns) || $col === 'id' || $col === 'created_at') continue;
                                $ic[] = "`$col`";
                                $ph[] = "?";
                                $pr[] = ($val === '') ? null : $val;
                            }

                            if (!empty($ic)) {
                                $db->prepare("INSERT INTO employees (" . implode(',', $ic) . ") VALUES (" . implode(',', $ph) . ")")->execute($pr);
                                $successCount++;
                            }
                        }
                        $db->commit();

                        $msg = "$successCount employee(s) imported successfully.";
                        if (!empty($rowErrors)) {
                            $_SESSION['flash_warning'] = $msg . " Warnings: " . implode(' | ', $rowErrors);
                        } else {
                            $_SESSION['flash_success'] = $msg;
                        }
                    } catch (Exception $e) {
                        $db->rollBack();
                        $_SESSION['flash_error'] = "Import failed at row $rowNum: " . $e->getMessage();
                    }
                } else {
                    $_SESSION['flash_error'] = "Invalid or empty CSV file.";
                }
                fclose($handle);
            } else {
                $_SESSION['flash_error'] = "Could not read uploaded file.";
            }
        } else {
            $_SESSION['flash_error'] = "File upload error. Please try again.";
        }
        header("Location: add_employee.php?tab=bulk"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Employee – HRMS Portal</title>
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
      <span class="page-title">Add Employee</span>
      <span class="page-breadcrumb"><a href="employees.php" style="color:var(--muted);text-decoration:none;">Employees</a> / New</span>
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
    <?php if($warnMsg): ?>
      <div class="alert alert-warning"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?= htmlspecialchars($warnMsg) ?></div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
      <div class="alert alert-error"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-text">
        <h1>New Employee</h1>
        <p>Fill in the profile details or use bulk upload to onboard multiple employees at once.</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button id="tab-single" class="tab-btn active" onclick="switchTab('single')">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Single Entry
      </button>
      <button id="tab-bulk" class="tab-btn" onclick="switchTab('bulk')">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
        Bulk Upload
      </button>
    </div>

    <!-- Single Entry Form -->
    <div id="view-single">
    <form method="POST">
      <input type="hidden" name="action" value="save_employee">

      <?php if(!empty($customColumns)): ?>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <div>
            <h2>Custom Fields</h2>
            <p>Fields defined in your Custom Fields settings</p>
          </div>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <?php foreach($customColumns as $col): ?>
            <div class="form-group">
              <label><?= htmlspecialchars(ucwords(str_replace('_',' ',$col))) ?></label>
              <input type="text" name="<?= htmlspecialchars($col) ?>" class="form-control">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Personal Information</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>First Name <span class="req">*</span></label><input type="text" name="first_name" class="form-control" required></div>
            <div class="form-group"><label>Last Name <span class="req">*</span></label><input type="text" name="last_name" class="form-control" required></div>
            <div class="form-group"><label>Work Email <span class="req">*</span></label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label>Personal Email</label><input type="email" name="personal_email" class="form-control"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
            <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth" class="form-control"></div>
            <div class="form-group"><label>Gender</label>
              <select name="gender" class="form-control"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select>
            </div>
            <div class="form-group"><label>Marital Status</label>
              <select name="marital_status" class="form-control"><option value="">Select</option><option>Single</option><option>Married</option><option>Divorced</option><option>Widowed</option></select>
            </div>
            <div class="form-group"><label>Blood Group</label><input type="text" name="blood_group" class="form-control"></div>
            <div class="form-group"><label>Nationality</label><input type="text" name="nationality" class="form-control"></div>
            <div class="form-group"><label>Place of Birth</label><input type="text" name="place_of_birth" class="form-control"></div>
            <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact_no" class="form-control"></div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Employment Details</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_id" class="form-control"></div>
            <div class="form-group"><label>User Code</label><input type="text" name="user_code" class="form-control"></div>
            <div class="form-group"><label>Job Title</label><input type="text" name="job_title" class="form-control"></div>
            <div class="form-group"><label>Department</label>
              <select name="department_id" class="form-control">
                <option value="">Select Department</option>
                <?php foreach($deptList as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Employee Type</label>
              <select name="employee_type" class="form-control"><option value="FTE">FTE</option><option value="External">External</option></select>
            </div>
            <div class="form-group"><label>Date of Joining</label><input type="date" name="date_of_joining" class="form-control"></div>
            <div class="form-group"><label>Date of Confirmation</label><input type="date" name="date_of_confirmation" class="form-control"></div>
            <div class="form-group"><label>Direct Manager</label><input type="text" name="direct_manager_name" class="form-control"></div>
            <div class="form-group"><label>Location</label><input type="text" name="location" class="form-control"></div>
            <div class="form-group"><label>Base Location</label><input type="text" name="base_location" class="form-control"></div>
            <div class="form-group"><label>Status</label>
              <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option><option value="terminated">Terminated</option></select>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Address</h2></div></div>
        <div class="card-body">
          <div class="form-section-title">Current Address</div>
          <div class="form-grid">
            <div class="form-group"><label>Address Line 1</label><input type="text" name="address_line1" class="form-control"></div>
            <div class="form-group"><label>Address Line 2</label><input type="text" name="address_line2" class="form-control"></div>
            <div class="form-group"><label>City</label><input type="text" name="city" class="form-control"></div>
            <div class="form-group"><label>State</label><input type="text" name="state" class="form-control"></div>
            <div class="form-group"><label>Zip Code</label><input type="text" name="zip_code" class="form-control"></div>
            <div class="form-group"><label>Country</label><input type="text" name="country" class="form-control"></div>
          </div>
          <div class="divider"></div>
          <div class="form-section-title">Permanent Address</div>
          <div class="form-grid">
            <div class="form-group"><label>Address Line 1</label><input type="text" name="permanent_address_line1" class="form-control"></div>
            <div class="form-group"><label>Address Line 2</label><input type="text" name="permanent_address_line2" class="form-control"></div>
            <div class="form-group"><label>City</label><input type="text" name="permanent_city" class="form-control"></div>
            <div class="form-group"><label>State</label><input type="text" name="permanent_state" class="form-control"></div>
            <div class="form-group"><label>Zip Code</label><input type="text" name="permanent_zip_code" class="form-control"></div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Financial & Statutory</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Gross Salary</label><input type="number" step="0.01" name="gross_salary" class="form-control"></div>
            <div class="form-group"><label>Account Type</label>
              <select name="account_type" class="form-control"><option value="">Select</option><option>Savings</option><option>Current</option></select>
            </div>
            <div class="form-group"><label>Account Number</label><input type="text" name="account_number" class="form-control"></div>
            <div class="form-group"><label>IFSC Code</label><input type="text" name="ifsc_code" class="form-control"></div>
            <div class="form-group"><label>PAN Number</label><input type="text" name="pan" class="form-control"></div>
            <div class="form-group"><label>Aadhar Number</label><input type="text" name="aadhar_no" class="form-control"></div>
            <div class="form-group"><label>UAN Number</label><input type="text" name="uan_number" class="form-control"></div>
            <div class="form-group"><label>PF Account Number</label><input type="text" name="pf_account_number" class="form-control"></div>
            <div class="form-group"><label>Employee PF</label><input type="text" name="employee_provident_fund" class="form-control"></div>
            <div class="form-group"><label>Professional Tax</label><input type="text" name="professional_tax" class="form-control"></div>
            <div class="form-group"><label>ESI Number</label><input type="text" name="esi_number" class="form-control"></div>
            <div class="form-group" style="justify-content:flex-end;padding-top:20px;">
              <div class="form-check">
                <input type="checkbox" name="exempt_from_tax" id="exempt_from_tax" value="1">
                <label for="exempt_from_tax">Exempt from Tax</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div><h2>Passport Details</h2></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label>Passport Number</label><input type="text" name="passport_no" class="form-control"></div>
            <div class="form-group"><label>Place of Issue</label><input type="text" name="place_of_issue" class="form-control"></div>
            <div class="form-group"><label>Date of Issue</label><input type="date" name="passport_date_of_issue" class="form-control"></div>
            <div class="form-group"><label>Date of Expiry</label><input type="date" name="passport_date_of_expiry" class="form-control"></div>
          </div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:4px;">
        <a href="employees.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Save Employee
        </button>
      </div>
    </form>
    </div>

    <!-- Bulk Upload -->
    <div id="view-bulk" style="display:none;">

      <!-- Step cards -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">
        <div class="card" style="text-align:center;padding:24px 20px;">
          <div style="width:40px;height:40px;background:var(--brand-light);border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--brand);font-size:18px;font-weight:800;">1</div>
          <div style="font-weight:700;font-size:13.5px;margin-bottom:6px;">Download Template</div>
          <p style="font-size:12.5px;color:var(--muted);line-height:1.5;">Get the CSV with all columns including your custom fields and a sample row.</p>
          <a href="download_template.php" class="btn btn-secondary btn-sm" style="margin-top:14px;">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download CSV
          </a>
        </div>
        <div class="card" style="text-align:center;padding:24px 20px;">
          <div style="width:40px;height:40px;background:#d1fae5;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:#059669;font-size:18px;font-weight:800;">2</div>
          <div style="font-weight:700;font-size:13.5px;margin-bottom:6px;">Fill in Data</div>
          <p style="font-size:12.5px;color:var(--muted);line-height:1.5;">Fill employee rows. Delete the sample row before uploading. Keep column headers unchanged.</p>
        </div>
        <div class="card" style="text-align:center;padding:24px 20px;">
          <div style="width:40px;height:40px;background:var(--yellow-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--yellow);font-size:18px;font-weight:800;">3</div>
          <div style="font-weight:700;font-size:13.5px;margin-bottom:6px;">Upload & Import</div>
          <p style="font-size:12.5px;color:var(--muted);line-height:1.5;">Upload the filled CSV. Valid rows are imported; rows with errors are skipped and reported.</p>
        </div>
      </div>

      <!-- Upload form -->
      <div class="card">
        <div class="card-header">
          <div>
            <h2>Upload CSV File</h2>
            <p>Only .csv files accepted. Max file size depends on your server's upload_max_filesize setting.</p>
          </div>
        </div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data" id="bulkForm">
            <input type="hidden" name="action" value="bulk_upload">
            <div style="border:2px dashed var(--border);border-radius:var(--radius-lg);padding:40px;text-align:center;background:var(--surface-2);cursor:pointer;" onclick="document.getElementById('csv_file').click();" id="dropZone">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--muted-light)" stroke-width="1.5" style="margin:0 auto 12px;display:block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
              <div id="dropLabel" style="font-size:14px;font-weight:600;color:var(--muted);margin-bottom:4px;">Click to select a CSV file</div>
              <div style="font-size:12px;color:var(--muted-light);">or drag and drop here</div>
              <input type="file" id="csv_file" name="csv_file" accept=".csv" required style="display:none;" onchange="onFileSelect(this)">
            </div>
            <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px;">
              <a href="download_template.php" class="btn btn-secondary">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download Template
              </a>
              <button type="submit" class="btn btn-success" id="importBtn" disabled>
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Import CSV
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Field reference -->
      <div class="card" style="margin-top:16px;">
        <div class="card-header"><div><h2>Field Reference</h2><p>Accepted values for key columns</p></div></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
            <div style="background:var(--surface-2);border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;">
              <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;">department</div>
              <div style="font-size:12px;color:var(--muted);line-height:1.7;">
                <?php foreach($deptList as $d): ?>
                  <span style="display:inline-block;background:#f3f4f6;border-radius:4px;padding:1px 7px;margin:2px 2px 2px 0;font-family:monospace;"><?= htmlspecialchars($d['name']) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <div style="background:var(--surface-2);border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;">
              <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;">employee_type</div>
              <div style="font-size:12px;color:var(--muted);">
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">FTE</span>
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">External</span>
              </div>
            </div>
            <div style="background:var(--surface-2);border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;">
              <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;">status</div>
              <div style="font-size:12px;color:var(--muted);">
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">active</span>
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">inactive</span>
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">terminated</span>
              </div>
            </div>
            <div style="background:var(--surface-2);border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;">
              <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;">gender</div>
              <div style="font-size:12px;color:var(--muted);">
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">Male</span>
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">Female</span>
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">Other</span>
              </div>
            </div>
            <div style="background:var(--surface-2);border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;">
              <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;">date fields</div>
              <div style="font-size:12px;color:var(--muted);">Format: <span style="font-family:monospace;">YYYY-MM-DD</span> e.g. <span style="font-family:monospace;">2024-01-15</span></div>
            </div>
            <div style="background:var(--surface-2);border:1px solid var(--border-light);border-radius:var(--radius);padding:14px;">
              <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;">exempt_from_tax</div>
              <div style="font-size:12px;color:var(--muted);">
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">0</span> = No &nbsp;
                <span style="background:#f3f4f6;border-radius:4px;padding:1px 7px;font-family:monospace;">1</span> = Yes
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
      <div class="card">
        <div class="card-body" style="text-align:center;padding:60px 40px;">
          <div style="width:56px;height:56px;background:var(--brand-light);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--brand)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
          </div>
          <h2 style="font-size:18px;font-weight:800;margin-bottom:8px;">Bulk Upload Employees</h2>
          <p style="color:var(--muted);max-width:480px;margin:0 auto 32px;line-height:1.6;font-size:13.5px;">Download the CSV template matching your current schema, fill it out, and upload to create multiple records at once.</p>
          <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;align-items:center;">
            <a href="download_template.php" class="btn btn-secondary">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Download Template
            </a>
            <form method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:10px;" onsubmit="return confirm('Import this CSV?')">
              <input type="hidden" name="action" value="bulk_upload">
              <input type="file" name="csv_file" accept=".csv" required style="font-size:13px;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-2);cursor:pointer;">
              <button type="submit" class="btn btn-success">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Import CSV
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
</div>

<script>
function switchTab(tab) {
  ['single','bulk'].forEach(t => {
    document.getElementById('tab-'+t).classList.toggle('active', t===tab);
    document.getElementById('view-'+t).style.display = t===tab ? 'block' : 'none';
  });
}

function onFileSelect(input) {
  const label = document.getElementById('dropLabel');
  const btn   = document.getElementById('importBtn');
  if (input.files && input.files[0]) {
    label.textContent = input.files[0].name;
    label.style.color = 'var(--text)';
    btn.disabled = false;
  }
}

// Drag and drop
const zone = document.getElementById('dropZone');
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--brand)'; zone.style.background = 'var(--brand-light)'; });
  zone.addEventListener('dragleave', () => { zone.style.borderColor = 'var(--border)'; zone.style.background = 'var(--surface-2)'; });
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.style.borderColor = 'var(--border)'; zone.style.background = 'var(--surface-2)';
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.csv')) {
      const input = document.getElementById('csv_file');
      const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
      onFileSelect(input);
    }
  });
}

// Auto-open bulk tab if there was a bulk-related flash
<?php if(isset($_GET['tab']) && $_GET['tab']==='bulk'): ?>
switchTab('bulk');
<?php endif; ?>
</script>
</body>
</html>
