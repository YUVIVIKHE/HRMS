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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_custom_field') {
    try {
        $fieldName = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($_POST['field_name'])));
        $fieldType = $_POST['field_type'] === 'date' ? 'DATE' : 'VARCHAR(255)';
        
        if (!empty($fieldName) && !in_array($fieldName, $allColumns)) {
            $db->exec("ALTER TABLE employees ADD COLUMN `$fieldName` $fieldType DEFAULT NULL");
            $_SESSION['flash_success'] = "Custom field '$fieldName' added successfully to the database!";
        } else {
            $_SESSION['flash_error'] = "Invalid field name or field already exists.";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Error adding custom field: " . $e->getMessage();
    }
    header("Location: custom_fields.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Custom Fields – HRMS Portal</title>
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

.custom-field-panel {
    background: #f0fdf4;
    border: 1px dashed #4ade80;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 32px;
    display: flex;
    gap: 16px;
    align-items: flex-end;
}
.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; }
.form-group label { font-size: 0.85rem; font-weight: 600; color: var(--muted); }
.form-control {
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.95rem;
    font-family: inherit;
    background: #ffffff;
    transition: all 0.2s;
    width: 100%;
}
.form-control:focus { outline: none; border-color: #16a34a; background: #fff; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); }
.btn-success { background: #22c55e; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; border: none; height: 42px; }
.btn-success:hover { background: #16a34a; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; font-size: 0.95rem; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.table-container {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
table { width: 100%; border-collapse: collapse; }
th { background: #f8fafc; padding: 16px 24px; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); border-bottom: 1px solid var(--border); }
td { padding: 16px 24px; font-size: 0.95rem; border-bottom: 1px solid var(--border); }
tr:last-child td { border-bottom: none; }
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="page-header">
        <h1>Custom Fields Schema Builder</h1>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <form class="custom-field-panel" method="POST">
        <input type="hidden" name="action" value="add_custom_field">
        <div style="flex:2">
            <div style="font-weight:700;margin-bottom:8px;color:#166534">Add New Field to Employee Database</div>
            <p style="font-size:0.8rem;color:#15803d;margin-bottom:12px">These fields will automatically appear on the Add Employee form and in the Bulk Upload CSV template.</p>
        </div>
        <div class="form-group" style="flex:1">
            <label style="color:#166534">Field Name (e.g. skype_id)</label>
            <input type="text" name="field_name" class="form-control" required pattern="[a-zA-Z0-9_ ]+">
        </div>
        <div class="form-group" style="flex:1">
            <label style="color:#166534">Data Type</label>
            <select name="field_type" class="form-control">
                <option value="text">Text / Varchar</option>
                <option value="date">Date</option>
            </select>
        </div>
        <button type="submit" class="btn-success">+ Add Field</button>
    </form>

    <h3 style="margin-bottom: 16px; font-size: 1.1rem; color: var(--text)">Active Custom Fields</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Database Column Name</th>
                    <th>Display Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($customColumns)): ?>
                    <tr>
                        <td colspan="2" style="text-align: center; color: var(--muted); padding: 40px;">No custom fields added yet.</td>
                    </tr>
                <?php else: foreach($customColumns as $col): ?>
                    <tr>
                        <td style="font-family: monospace; font-weight: 600; color: var(--accent);"><?= htmlspecialchars($col) ?></td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
