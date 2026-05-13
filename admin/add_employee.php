<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

// Handle flashed messages
$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Get existing columns
$stmt = $db->query("SHOW COLUMNS FROM employees");
$allColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

$baseColumns = ['id', 'first_name', 'last_name', 'email', 'phone', 'job_title', 'date_of_birth', 'gender', 'marital_status', 'employee_id', 'department_id', 'employee_type', 'date_of_joining', 'date_of_exit', 'date_of_confirmation', 'direct_manager_name', 'location', 'base_location', 'user_code', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'permanent_address_line1', 'permanent_address_line2', 'permanent_city', 'permanent_state', 'permanent_zip_code', 'account_type', 'account_number', 'ifsc_code', 'pan', 'aadhar_no', 'uan_number', 'pf_account_number', 'employee_provident_fund', 'professional_tax', 'esi_number', 'exempt_from_tax', 'passport_no', 'place_of_issue', 'passport_date_of_issue', 'passport_date_of_expiry', 'place_of_birth', 'nationality', 'blood_group', 'personal_email', 'emergency_contact_no', 'country_code_phone', 'status', 'created_at', 'gross_salary'];
$customColumns = array_diff($allColumns, $baseColumns);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_employee') {
        try {
            $insertCols = [];
            $placeholders = [];
            $params = [];
            
            foreach ($allColumns as $col) {
                if ($col === 'id' || $col === 'created_at') continue;
                
                if (isset($_POST[$col])) {
                    $insertCols[] = "`$col`";
                    $placeholders[] = "?";
                    $val = trim($_POST[$col]);
                    $params[] = ($val === '') ? null : $val;
                } elseif ($col === 'exempt_from_tax') {
                    $insertCols[] = "`$col`";
                    $placeholders[] = "?";
                    $params[] = isset($_POST['exempt_from_tax']) ? 1 : 0;
                }
            }
            
            if (!empty($insertCols)) {
                $sql = "INSERT INTO employees (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $_SESSION['flash_success'] = "Employee profile saved successfully!";
                header("Location: employees.php");
                exit;
            } else {
                $_SESSION['flash_error'] = "No data submitted.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error adding employee: " . $e->getMessage();
        }
        header("Location: add_employee.php");
        exit;
    } elseif ($action === 'bulk_upload') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                $headers = fgetcsv($handle, 10000, ",");
                if ($headers) {
                    $mappedCols = [];
                    foreach ($headers as $h) {
                        $h = trim($h);
                        $mappedCols[] = in_array($h, $allColumns) ? $h : null;
                    }
                    
                    $successCount = 0;
                    $db->beginTransaction();
                    try {
                        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                            $insertCols = [];
                            $placeholders = [];
                            $params = [];
                            
                            foreach ($data as $index => $val) {
                                $colName = $mappedCols[$index] ?? null;
                                if ($colName && $colName !== 'id' && $colName !== 'created_at') {
                                    $insertCols[] = "`$colName`";
                                    $placeholders[] = "?";
                                    $params[] = trim($val) === '' ? null : trim($val);
                                }
                            }
                            
                            if (!empty($insertCols)) {
                                $sql = "INSERT INTO employees (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                                $db->prepare($sql)->execute($params);
                                $successCount++;
                            }
                        }
                        $db->commit();
                        $_SESSION['flash_success'] = "$successCount employees imported successfully via bulk upload!";
                    } catch (Exception $e) {
                        $db->rollBack();
                        $_SESSION['flash_error'] = "Bulk upload failed: " . $e->getMessage();
                    }
                } else {
                    $_SESSION['flash_error'] = "Invalid CSV format or empty file.";
                }
                fclose($handle);
            } else {
                $_SESSION['flash_error'] = "Could not read uploaded file.";
            }
        } else {
            $_SESSION['flash_error'] = "File upload error.";
        }
        header("Location: add_employee.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Employee – HRMS Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #f8fafc; --surface: #ffffff; --border: #e2e8f0; --accent: #4f46e5;
    --text: #0f172a; --muted: #64748b; --sidebar: 260px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}

/* Sidebar Styles */
.sidebar{width:var(--sidebar);min-width:var(--sidebar);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:32px 0;position:fixed;top:0;left:0;height:100vh;z-index:100}
.s-logo{display:flex;align-items:center;gap:12px;padding:0 28px 32px;border-bottom:1px solid var(--border)}
.s-logo .lb{width:42px;height:42px;border-radius:12px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:bold}
.s-logo strong{font-size:1.1rem;font-weight:800;color:var(--text)} 
.s-logo small{display:block;font-size:.75rem;color:var(--muted);margin-top:2px}
nav{flex:1;padding:24px 0;overflow-y:auto}
nav a{display:flex;align-items:center;gap:12px;padding:12px 28px;font-size:.95rem;font-weight:500;color:var(--muted);text-decoration:none;border-left:3px solid transparent;transition:all .2s ease}
nav a:hover, nav a.active{background:#f1f5f9;color:var(--accent);border-left-color:var(--accent)}
nav a svg{width:20px;height:20px;flex-shrink:0}
.s-foot{padding:24px 28px;border-top:1px solid var(--border)}
.s-foot a{display:flex;align-items:center;justify-content:flex-start;gap:10px;color:#ef4444;font-size:.9rem;font-weight:600;text-decoration:none;padding:12px 16px;border-radius:10px;background:#fef2f2;transition:all .2s ease}
.s-foot a:hover{background:#fee2e2}
.s-foot a svg{width:20px;height:20px;flex-shrink:0}

/* Main Content */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;padding:40px 60px}
.page-header { margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; }
.page-header h1 { font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px; }

.form-container {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    margin-bottom: 32px;
}

.custom-field-panel {
    background: #f0fdf4;
    border: 1px dashed #4ade80;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: flex-end;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
    margin-top: 32px;
}
.section-title:first-child { margin-top: 0; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; }
.form-group label { font-size: 0.85rem; font-weight: 600; color: var(--muted); }
.form-control {
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.95rem;
    font-family: inherit;
    background: #f8fafc;
    transition: all 0.2s;
    width: 100%;
}
.form-control:focus { outline: none; border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
select.form-control { cursor: pointer; }

.form-actions {
    margin-top: 40px;
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    border-top: 1px solid var(--border);
    padding-top: 24px;
}

.btn { padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; text-decoration: none; border: none; display: inline-block; }
.btn-secondary { background: #f1f5f9; color: var(--text); border: 1px solid var(--border); }
.btn-secondary:hover { background: #e2e8f0; }
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { background: #4338ca; }
.btn-success { background: #22c55e; color: white; }
.btn-success:hover { background: #16a34a; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; font-size: 0.95rem; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.checkbox-group { flex-direction: row; align-items: center; gap: 8px; margin-top: 28px; }
.checkbox-group input { width: 18px; height: 18px; accent-color: var(--accent); }
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="page-header">
        <h1>Add New Employee</h1>
        <div style="display:flex; gap: 12px; align-items:center;">
            <a href="download_template.php" style="background:#f1f5f9; color:var(--text); border:1px solid var(--border); padding:8px 16px; border-radius:8px; font-weight:600; font-size:0.85rem; text-decoration:none; transition:background 0.2s;">Download CSV Template</a>
            <form method="POST" enctype="multipart/form-data" style="display:flex; gap:8px;" onsubmit="return confirm('Upload this CSV?');">
                <input type="hidden" name="action" value="bulk_upload">
                <input type="file" name="csv_file" accept=".csv" required style="font-size:0.85rem; border:1px solid var(--border); border-radius:6px; padding:6px; background:#fff">
                <button type="submit" class="btn-primary" style="background:#16a34a; padding:8px 16px;">Bulk Import CSV</button>
            </form>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>


    <form class="form-container" method="POST">
        <input type="hidden" name="action" value="save_employee">
        
        <?php if (!empty($customColumns)): ?>
            <div class="section-title" style="color:var(--accent);">Custom Fields</div>
            <div class="form-grid">
                <?php foreach ($customColumns as $col): ?>
                    <div class="form-group">
                        <label><?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?></label>
                        <input type="text" name="<?= htmlspecialchars($col) ?>" class="form-control">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Basic Info -->
        <div class="section-title">Personal Information</div>
        <div class="form-grid">
            <div class="form-group"><label>First Name *</label><input type="text" name="first_name" class="form-control" required></div>
            <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
            <div class="form-group"><label>Work Email *</label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label>Personal Email</label><input type="email" name="personal_email" class="form-control"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
            <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth" class="form-control"></div>
            <div class="form-group">
                <label>Gender</label>
                <select name="gender" class="form-control">
                    <option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Marital Status</label>
                <select name="marital_status" class="form-control">
                    <option value="">Select</option><option value="Single">Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widowed">Widowed</option>
                </select>
            </div>
            <div class="form-group"><label>Blood Group</label><input type="text" name="blood_group" class="form-control"></div>
            <div class="form-group"><label>Nationality</label><input type="text" name="nationality" class="form-control"></div>
            <div class="form-group"><label>Place of Birth</label><input type="text" name="place_of_birth" class="form-control"></div>
            <div class="form-group"><label>Emergency Contact No</label><input type="text" name="emergency_contact_no" class="form-control"></div>
        </div>

        <!-- Employment Details -->
        <div class="section-title">Employment Details</div>
        <div class="form-grid">
            <div class="form-group"><label>Employee ID</label><input type="text" name="employee_id" class="form-control"></div>
            <div class="form-group"><label>User Code</label><input type="text" name="user_code" class="form-control"></div>
            <div class="form-group"><label>Job Title</label><input type="text" name="job_title" class="form-control"></div>
            <div class="form-group"><label>Department ID</label><input type="number" name="department_id" class="form-control"></div>
            <div class="form-group">
                <label>Employee Type</label>
                <select name="employee_type" class="form-control" required><option value="FTE">FTE</option><option value="External">External</option></select>
            </div>
            <div class="form-group"><label>Date of Joining</label><input type="date" name="date_of_joining" class="form-control"></div>
            <div class="form-group"><label>Date of Confirmation</label><input type="date" name="date_of_confirmation" class="form-control"></div>
            <div class="form-group"><label>Direct Manager Name</label><input type="text" name="direct_manager_name" class="form-control"></div>
            <div class="form-group"><label>Location</label><input type="text" name="location" class="form-control"></div>
            <div class="form-group"><label>Base Location</label><input type="text" name="base_location" class="form-control"></div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control"><option value="Active">Active</option><option value="Inactive">Inactive</option><option value="Terminated">Terminated</option></select>
            </div>
        </div>

        <!-- Address -->
        <div class="section-title">Current Address</div>
        <div class="form-grid">
            <div class="form-group"><label>Address Line 1</label><input type="text" name="address_line1" class="form-control"></div>
            <div class="form-group"><label>Address Line 2</label><input type="text" name="address_line2" class="form-control"></div>
            <div class="form-group"><label>City</label><input type="text" name="city" class="form-control"></div>
            <div class="form-group"><label>State</label><input type="text" name="state" class="form-control"></div>
            <div class="form-group"><label>Zip Code</label><input type="text" name="zip_code" class="form-control"></div>
            <div class="form-group"><label>Country</label><input type="text" name="country" class="form-control"></div>
        </div>

        <div class="section-title">Permanent Address</div>
        <div class="form-grid">
            <div class="form-group"><label>Permanent Address Line 1</label><input type="text" name="permanent_address_line1" class="form-control"></div>
            <div class="form-group"><label>Permanent Address Line 2</label><input type="text" name="permanent_address_line2" class="form-control"></div>
            <div class="form-group"><label>Permanent City</label><input type="text" name="permanent_city" class="form-control"></div>
            <div class="form-group"><label>Permanent State</label><input type="text" name="permanent_state" class="form-control"></div>
            <div class="form-group"><label>Permanent Zip Code</label><input type="text" name="permanent_zip_code" class="form-control"></div>
        </div>

        <!-- Financial Details -->
        <div class="section-title">Financial & Statutory Details</div>
        <div class="form-grid">
            <div class="form-group"><label>Gross Salary</label><input type="number" step="0.01" name="gross_salary" class="form-control"></div>
            <div class="form-group">
                <label>Account Type</label>
                <select name="account_type" class="form-control"><option value="">Select</option><option value="Savings">Savings</option><option value="Current">Current</option></select>
            </div>
            <div class="form-group"><label>Account Number</label><input type="text" name="account_number" class="form-control"></div>
            <div class="form-group"><label>IFSC Code</label><input type="text" name="ifsc_code" class="form-control"></div>
            <div class="form-group"><label>PAN Number</label><input type="text" name="pan" class="form-control"></div>
            <div class="form-group"><label>Aadhar Number</label><input type="text" name="aadhar_no" class="form-control"></div>
            <div class="form-group"><label>UAN Number</label><input type="text" name="uan_number" class="form-control"></div>
            <div class="form-group"><label>PF Account Number</label><input type="text" name="pf_account_number" class="form-control"></div>
            <div class="form-group"><label>Employee Provident Fund</label><input type="text" name="employee_provident_fund" class="form-control"></div>
            <div class="form-group"><label>Professional Tax</label><input type="text" name="professional_tax" class="form-control"></div>
            <div class="form-group"><label>ESI Number</label><input type="text" name="esi_number" class="form-control"></div>
            <div class="form-group checkbox-group">
                <input type="checkbox" name="exempt_from_tax" id="exempt_from_tax" value="1">
                <label for="exempt_from_tax" style="margin:0; cursor:pointer;">Exempt from Tax</label>
            </div>
        </div>

        <!-- Passport Details -->
        <div class="section-title">Passport Details</div>
        <div class="form-grid">
            <div class="form-group"><label>Passport Number</label><input type="text" name="passport_no" class="form-control"></div>
            <div class="form-group"><label>Place of Issue</label><input type="text" name="place_of_issue" class="form-control"></div>
            <div class="form-group"><label>Date of Issue</label><input type="date" name="passport_date_of_issue" class="form-control"></div>
            <div class="form-group"><label>Date of Expiry</label><input type="date" name="passport_date_of_expiry" class="form-control"></div>
        </div>

        <div class="form-actions">
            <a href="employees.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Employee</button>
        </div>
    </form>
</div>

</body>
</html>
