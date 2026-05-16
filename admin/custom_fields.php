<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$baseColumns = ['id','first_name','last_name','email','phone','job_title','date_of_birth','gender','marital_status','employee_id','department_id','employee_type','date_of_joining','date_of_exit','date_of_confirmation','direct_manager_name','location','base_location','user_code','address_line1','address_line2','city','state','zip_code','country','permanent_address_line1','permanent_address_line2','permanent_city','permanent_state','permanent_zip_code','account_type','account_number','ifsc_code','pan','aadhar_no','uan_number','pf_account_number','employee_provident_fund','professional_tax','esi_number','exempt_from_tax','passport_no','place_of_issue','passport_date_of_issue','passport_date_of_expiry','place_of_birth','nationality','blood_group','personal_email','emergency_contact_no','country_code_phone','status','created_at','gross_salary'];

$stmt = $db->query("SHOW FULL COLUMNS FROM employees");
$allColumnsFull = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_custom_field') {
        try {
            $label = trim($_POST['field_label']);
            $fieldName = preg_replace('/[^a-z0-9_]/','_',strtolower($label));
            $fType = $_POST['field_type'] ?? 'text';
            $dbType = 'VARCHAR(255)';
            if ($fType === 'date') $dbType = 'DATE';
            elseif ($fType === 'number') $dbType = 'DECIMAL(12,2)';
            elseif ($fType === 'textarea') $dbType = 'TEXT';

            $isRequired = isset($_POST['is_required']);
            $dropdownOptions = [];
            if ($fType === 'dropdown' && !empty($_POST['dropdown_options'])) {
                $dropdownOptions = array_filter(array_map('trim', explode("\n", $_POST['dropdown_options'])));
            }

            $existingCols = array_column($allColumnsFull,'Field');
            if (!empty($fieldName) && !in_array($fieldName,$existingCols)) {
                // Store meta as simple pipe-separated format in comment: type|label|opt1,opt2,opt3|required
                $optStr = implode(',', $dropdownOptions);
                $comment = $fType . '|' . $label . '|' . $optStr . '|' . ($isRequired ? '1' : '0');
                $db->exec("ALTER TABLE employees ADD COLUMN `$fieldName` $dbType DEFAULT NULL COMMENT " . $db->quote($comment));
                $_SESSION['flash_success'] = "Custom field '$label' added.";
            } else { $_SESSION['flash_error'] = "Invalid name or field already exists."; }
        } catch (PDOException $e) { $_SESSION['flash_error'] = "Error: ".$e->getMessage(); }
        header("Location: custom_fields.php"); exit;
    } elseif ($action === 'delete_custom_field') {
        try {
            $fieldName = $_POST['field_name'];
            $existingCols = array_column($allColumnsFull,'Field');
            if (!in_array($fieldName,$baseColumns) && in_array($fieldName,$existingCols)) {
                $db->exec("ALTER TABLE employees DROP COLUMN `$fieldName`");
                $_SESSION['flash_success'] = "Field deleted.";
            } else { $_SESSION['flash_error'] = "Cannot delete a core field."; }
        } catch (PDOException $e) { $_SESSION['flash_error'] = "Error: ".$e->getMessage(); }
        header("Location: custom_fields.php"); exit;
    } elseif ($action === 'edit_custom_field') {
        try {
            $oldName = $_POST['old_field_name'];
            $label = trim($_POST['field_label']);
            $isRequired = isset($_POST['is_required']);
            $currentType = 'VARCHAR(255)';
            foreach ($allColumnsFull as $c) { if ($c['Field']===$oldName) { $currentType=$c['Type']; break; } }
            $meta = json_encode(['required'=>$isRequired,'label'=>$label]);
            $db->exec("ALTER TABLE employees MODIFY COLUMN `$oldName` $currentType DEFAULT NULL COMMENT '$meta'");
            $_SESSION['flash_success'] = "Field updated.";
        } catch (PDOException $e) { $_SESSION['flash_error'] = "Error: ".$e->getMessage(); }
        header("Location: custom_fields.php"); exit;
    }
}

$stmt = $db->query("SHOW FULL COLUMNS FROM employees");
$allColumnsFull = $stmt->fetchAll(PDO::FETCH_ASSOC);
$customCols = [];
foreach ($allColumnsFull as $col) {
    if (!in_array($col['Field'],$baseColumns)) {
        $comment = $col['Comment'] ?? '';
        // Parse: type|label|opt1,opt2,opt3|required
        $parts = explode('|', $comment);
        if (count($parts) >= 2) {
            $meta = ['type'=>$parts[0], 'label'=>$parts[1], 'options'=>array_filter(explode(',', $parts[2] ?? '')), 'required'=>($parts[3] ?? '0')==='1'];
        } else {
            // Try JSON fallback for old fields
            $meta = json_decode($comment, true) ?: ['required'=>false,'label'=>ucwords(str_replace('_',' ',$col['Field'])),'type'=>'text','options'=>[]];
        }
        $customCols[] = ['field'=>$col['Field'],'type'=>$col['Type'],'meta'=>$meta];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Custom Fields – HRMS Portal</title>
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
      <span class="page-title">Custom Fields</span>
      <span class="page-breadcrumb">Employee Schema Configuration</span>
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
        <h1>Custom Fields</h1>
        <p>Extend employee profiles with additional fields that appear on all forms dynamically.</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:360px 1fr;gap:20px;align-items:start;">

      <!-- Add Field Card -->
      <div class="card">
        <div class="card-header">
          <div>
            <h2>Add New Field</h2>
            <p>Fields appear on employee forms immediately</p>
          </div>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="add_custom_field">
            <div class="form-group" style="margin-bottom:16px;">
              <label>Field Label <span class="req">*</span></label>
              <input type="text" name="field_label" class="form-control" placeholder="e.g. Blood Group, Shirt Size" required>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
              <label>Field Type</label>
              <select name="field_type" id="fieldType" class="form-control" onchange="toggleDropdownOpts()">
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="date">Date</option>
                <option value="email">Email</option>
                <option value="phone">Phone</option>
                <option value="textarea">Textarea (Long Text)</option>
                <option value="dropdown">Dropdown</option>
                <option value="yes_no">Yes / No</option>
                <option value="url">URL / Link</option>
              </select>
            </div>
            <div class="form-group" id="dropdownOptsGroup" style="margin-bottom:16px;display:none;">
              <label>Dropdown Options <span class="req">*</span></label>
              <textarea name="dropdown_options" class="form-control" rows="4" placeholder="Enter one option per line&#10;e.g.&#10;Option 1&#10;Option 2&#10;Option 3"></textarea>
              <span style="font-size:11px;color:var(--muted);">One option per line</span>
            </div>
            <div class="form-check" style="margin-bottom:20px;">
              <input type="checkbox" name="is_required" id="is_required">
              <label for="is_required">Mark as Required</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
              <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Field
            </button>
          </form>
        </div>
      </div>

      <!-- Active Fields Table -->
      <div class="table-wrap">
        <div class="table-toolbar">
          <h2>Active Custom Fields <span style="font-weight:400;color:var(--muted);font-size:13px;">(<?= count($customCols) ?>)</span></h2>
        </div>
        <table>
          <thead>
            <tr>
              <th>Label</th>
              <th>Column Name</th>
              <th>Type</th>
              <th>Required</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($customCols)): ?>
              <tr class="empty-row"><td colspan="5">No custom fields yet. Add one to get started.</td></tr>
            <?php else: foreach($customCols as $col): $meta=$col['meta']; ?>
            <tr>
              <td class="font-semibold"><?= htmlspecialchars($meta['label']) ?></td>
              <td><code style="font-size:12px;background:var(--surface-2);padding:2px 7px;border-radius:5px;color:var(--muted);"><?= htmlspecialchars($col['field']) ?></code></td>
              <td class="text-muted text-sm"><?= htmlspecialchars($col['type']) ?></td>
              <td>
                <?php if(!empty($meta['required'])): ?>
                  <span class="badge badge-red">Required</span>
                <?php else: ?>
                  <span class="badge badge-gray">Optional</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button type="button" class="btn btn-ghost btn-sm" onclick="openEdit('<?= htmlspecialchars($col['field']) ?>','<?= htmlspecialchars(addslashes($meta['label'])) ?>',<?= !empty($meta['required'])?'true':'false' ?>)">Edit</button>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field and all its data?')">
                    <input type="hidden" name="action" value="delete_custom_field">
                    <input type="hidden" name="field_name" value="<?= htmlspecialchars($col['field']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Custom Field</h3>
      <button class="modal-close" onclick="closeEdit()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="edit_custom_field">
        <input type="hidden" name="old_field_name" id="edit_field_name">
        <div class="form-group" style="margin-bottom:16px;">
          <label>Field Label <span class="req">*</span></label>
          <input type="text" name="field_label" id="edit_label" class="form-control" required>
        </div>
        <div class="form-check">
          <input type="checkbox" name="is_required" id="edit_required">
          <label for="edit_required">Mark as Required</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(field, label, required) {
  document.getElementById('edit_field_name').value = field;
  document.getElementById('edit_label').value = label;
  document.getElementById('edit_required').checked = required;
  document.getElementById('editModal').classList.add('open');
}
function closeEdit() {
  document.getElementById('editModal').classList.remove('open');
}
function toggleDropdownOpts() {
  const t = document.getElementById('fieldType').value;
  document.getElementById('dropdownOptsGroup').style.display = t === 'dropdown' ? 'block' : 'none';
}
</script>
</body>
</html>
