<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Base columns from our schema
$baseColumns = ['id', 'first_name', 'last_name', 'email', 'phone', 'job_title', 'date_of_birth', 'gender', 'marital_status', 'employee_id', 'department_id', 'employee_type', 'date_of_joining', 'date_of_exit', 'date_of_confirmation', 'direct_manager_name', 'location', 'base_location', 'user_code', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'permanent_address_line1', 'permanent_address_line2', 'permanent_city', 'permanent_state', 'permanent_zip_code', 'account_type', 'account_number', 'ifsc_code', 'pan', 'aadhar_no', 'uan_number', 'pf_account_number', 'employee_provident_fund', 'professional_tax', 'esi_number', 'exempt_from_tax', 'passport_no', 'place_of_issue', 'passport_date_of_issue', 'passport_date_of_expiry', 'place_of_birth', 'nationality', 'blood_group', 'personal_email', 'emergency_contact_no', 'country_code_phone', 'status', 'created_at', 'gross_salary'];

$stmt = $db->query("SHOW FULL COLUMNS FROM employees");
$allColumnsFull = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_custom_field') {
        try {
            $label = trim($_POST['field_label']);
            $fieldName = preg_replace('/[^a-z0-9_]/', '_', strtolower($label));
            $fieldType = $_POST['field_type'] === 'date' ? 'DATE' : 'VARCHAR(255)';
            $isRequired = isset($_POST['is_required']) ? true : false;
            
            // Generate comment metadata
            $meta = json_encode(['required' => $isRequired, 'label' => $label]);
            
            $existingCols = array_column($allColumnsFull, 'Field');
            if (!empty($fieldName) && !in_array($fieldName, $existingCols)) {
                // To avoid breaking existing rows in strict mode, we don't set NOT NULL constraint at the DB level,
                // instead we enforce it via the UI using the metadata inside COMMENT.
                $db->exec("ALTER TABLE employees ADD COLUMN `$fieldName` $fieldType DEFAULT NULL COMMENT '$meta'");
                $_SESSION['flash_success'] = "Custom field '$label' added successfully!";
            } else {
                $_SESSION['flash_error'] = "Invalid field name or field already exists.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error adding custom field: " . $e->getMessage();
        }
        header("Location: custom_fields.php");
        exit;
    } elseif ($action === 'delete_custom_field') {
        try {
            $fieldName = $_POST['field_name'];
            $existingCols = array_column($allColumnsFull, 'Field');
            if (!in_array($fieldName, $baseColumns) && in_array($fieldName, $existingCols)) {
                $db->exec("ALTER TABLE employees DROP COLUMN `$fieldName`");
                $_SESSION['flash_success'] = "Custom field deleted successfully!";
            } else {
                $_SESSION['flash_error'] = "Cannot delete core system field.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error deleting field: " . $e->getMessage();
        }
        header("Location: custom_fields.php");
        exit;
    } elseif ($action === 'edit_custom_field') {
        try {
            $oldName = $_POST['old_field_name'];
            $label = trim($_POST['field_label']);
            $isRequired = isset($_POST['is_required']) ? true : false;
            
            // Find current type
            $currentType = 'VARCHAR(255)';
            foreach ($allColumnsFull as $c) {
                if ($c['Field'] === $oldName) {
                    $currentType = $c['Type'];
                    break;
                }
            }
            
            $meta = json_encode(['required' => $isRequired, 'label' => $label]);
            $db->exec("ALTER TABLE employees MODIFY COLUMN `$oldName` $currentType DEFAULT NULL COMMENT '$meta'");
            $_SESSION['flash_success'] = "Custom field updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error updating field: " . $e->getMessage();
        }
        header("Location: custom_fields.php");
        exit;
    }
}

// Re-fetch columns to reflect recent changes
$stmt = $db->query("SHOW FULL COLUMNS FROM employees");
$allColumnsFull = $stmt->fetchAll(PDO::FETCH_ASSOC);

$customCols = [];
foreach ($allColumnsFull as $col) {
    if (!in_array($col['Field'], $baseColumns)) {
        $meta = json_decode($col['Comment'], true);
        if (!$meta) {
            $meta = ['required' => false, 'label' => ucwords(str_replace('_', ' ', $col['Field']))];
        }
        $customCols[] = [
            'field' => $col['Field'],
            'type' => $col['Type'],
            'meta' => $meta
        ];
    }
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
    --bg: #f8fafc; --surface: #ffffff; --border: #e2e8f0; --accent: #3730a3;
    --text: #0f172a; --muted: #64748b; --sidebar: 260px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}

/* Sidebar Styles */
.sidebar{width:var(--sidebar);min-width:var(--sidebar);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:32px 0;position:fixed;top:0;left:0;height:100vh;z-index:100}
.s-logo{display:flex;align-items:center;gap:12px;padding:0 28px 32px;border-bottom:1px solid var(--border)}
.s-logo .lb{width:42px;height:42px;border-radius:12px;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:bold}
.s-logo strong{font-size:1.1rem;font-weight:800;color:var(--text)} 
.s-logo small{display:block;font-size:.75rem;color:var(--muted);margin-top:2px}
nav{flex:1;padding:24px 0;overflow-y:auto}
nav a{display:flex;align-items:center;gap:12px;padding:12px 28px;font-size:.95rem;font-weight:500;color:var(--muted);text-decoration:none;border-left:3px solid transparent;transition:all .2s ease}
nav a:hover, nav a.active{background:#f1f5f9;color:#4f46e5;border-left-color:#4f46e5}
nav a svg{width:20px;height:20px;flex-shrink:0}
.s-foot{padding:24px 28px;border-top:1px solid var(--border); margin-top: auto;}
.s-foot a{display:flex;align-items:center;justify-content:flex-start;gap:10px;color:#ef4444;font-size:.9rem;font-weight:600;text-decoration:none;padding:12px 16px;border-radius:10px;background:#fef2f2;transition:all .2s ease}
.s-foot a:hover{background:#fee2e2}
.s-foot a svg{width:20px;height:20px;flex-shrink:0}

.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;padding:40px 60px}

.header-card {
    background: linear-gradient(135deg, #3730a3, #4f46e5);
    color: white;
    padding: 32px 40px;
    border-radius: 16px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 15px -3px rgba(55, 48, 163, 0.2);
}
.header-bg {
    position: absolute;
    right: -100px;
    top: -100px;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    z-index: 1;
}

.card {
    background: var(--surface);
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    margin-bottom: 24px;
}

.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 0.85rem; font-weight: 700; color: var(--text); }
.form-control {
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.95rem;
    font-family: inherit;
    background: #ffffff;
    transition: all 0.2s;
    width: 100%;
}
.form-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(55, 48, 163, 0.1); }
select.form-control { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px; padding-right: 40px; }

.btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { background: #312e81; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; font-size: 0.95rem; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

table { width: 100%; border-collapse: collapse; margin-top: 16px; }
th { text-align: left; padding: 12px 16px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); border-bottom: 1px solid var(--border); }
td { padding: 16px; font-size: 0.95rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
tr:last-child td { border-bottom: none; }

.badge { padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: #f1f5f9; color: var(--muted); }
.badge-required { background: #fee2e2; color: #991b1b; }

.action-btn { background: none; border: none; color: var(--accent); font-weight: 600; font-size: 0.85rem; cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
.action-btn:hover { background: #f1f5f9; }
.action-btn.delete { color: #ef4444; }
.action-btn.delete:hover { background: #fef2f2; }

/* Modal */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal-overlay.active { display: flex; }
.modal { background: white; padding: 32px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="header-card">
        <div class="header-bg"></div>
        <div style="position:relative; z-index:2;">
            <div style="font-size:0.75rem; font-weight:700; letter-spacing:0.1em; opacity:0.8; margin-bottom:8px;">EMPLOYEE MANAGEMENT</div>
            <h1 style="font-size:2.2rem; font-weight:800; margin-bottom:12px; letter-spacing:-0.5px;">Custom Fields</h1>
            <p style="font-size:1rem; opacity:0.9; max-width:600px; line-height:1.6;">Define extra fields — Blood Group, Emergency Contact, Shirt Size, or anything else — that appear dynamically on every Employee and Manager profile form.</p>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-size:1.2rem; font-weight:800; margin-bottom:4px; color:var(--text);">Add Custom Field</h2>
        <p style="font-size:0.9rem; color:var(--muted); margin-bottom:24px; padding-bottom:24px; border-bottom:1px solid var(--border);">Fields are saved globally and appear on employee/manager profile forms immediately.</p>

        <form method="POST">
            <input type="hidden" name="action" value="add_custom_field">
            <div class="form-group" style="margin-bottom:20px;">
                <label>Field Label <span style="color:#ef4444">*</span></label>
                <input type="text" name="field_label" class="form-control" placeholder="e.g. Blood Group, Emergency Contact, Shirt Size" required>
            </div>
            
            <div style="display:flex; gap:20px; margin-bottom:24px;">
                <div class="form-group" style="flex:1;">
                    <label>Field Type</label>
                    <select name="field_type" class="form-control">
                        <option value="text">Text (short)</option>
                        <option value="date">Date</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:32px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" name="is_required" id="is_required" style="width:18px; height:18px; accent-color:var(--accent); cursor:pointer;">
                <label for="is_required" style="font-weight:600; font-size:0.9rem; cursor:pointer;">Mark as Required</label>
            </div>

            <button type="submit" class="btn btn-primary">+ Add Field</button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:1.2rem; font-weight:800; margin-bottom:16px;">Active Custom Fields</h2>
        <table>
            <thead>
                <tr>
                    <th>Field Label</th>
                    <th>Database Column</th>
                    <th>Required</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($customCols)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--muted);">No custom fields added yet.</td></tr>
                <?php else: foreach($customCols as $col): 
                    $meta = $col['meta'];
                ?>
                    <tr>
                        <td style="font-weight:600; color:var(--text);"><?= htmlspecialchars($meta['label']) ?></td>
                        <td style="font-family:monospace; color:var(--muted);"><?= htmlspecialchars($col['field']) ?></td>
                        <td>
                            <?php if(!empty($meta['required'])): ?>
                                <span class="badge badge-required">Required</span>
                            <?php else: ?>
                                <span class="badge">Optional</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="action-btn" onclick="openEditModal('<?= htmlspecialchars($col['field']) ?>', '<?= htmlspecialchars(addslashes($meta['label'])) ?>', <?= !empty($meta['required']) ? 'true' : 'false' ?>)">Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to completely delete this field and all data associated with it?');">
                                <input type="hidden" name="action" value="delete_custom_field">
                                <input type="hidden" name="field_name" value="<?= htmlspecialchars($col['field']) ?>">
                                <button type="submit" class="action-btn delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <h2 style="font-size:1.2rem; font-weight:800; margin-bottom:24px;">Edit Custom Field</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_custom_field">
            <input type="hidden" name="old_field_name" id="edit_old_field_name">
            
            <div class="form-group" style="margin-bottom:20px;">
                <label>Field Label <span style="color:#ef4444">*</span></label>
                <input type="text" name="field_label" id="edit_field_label" class="form-control" required>
            </div>
            


            <div style="margin-bottom:32px; display:flex; align-items:center; gap:10px;">
                <input type="checkbox" name="is_required" id="edit_is_required" style="width:18px; height:18px; accent-color:var(--accent); cursor:pointer;">
                <label for="edit_is_required" style="font-weight:600; font-size:0.9rem; cursor:pointer;">Mark as Required</label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn" style="background:#f1f5f9; color:var(--text);" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(fieldName, label, isRequired) {
    document.getElementById('edit_old_field_name').value = fieldName;
    document.getElementById('edit_field_label').value = label;
    document.getElementById('edit_is_required').checked = isRequired;
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
</script>

</body>
</html>
