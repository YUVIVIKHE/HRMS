<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

// IDs to export — 'all' or comma-separated list
$mode = $_GET['mode'] ?? 'selected';
$ids  = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));

if ($mode === 'all') {
    $employees = $db->query("SELECT e.*, d.name AS department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id ORDER BY e.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT e.*, d.name AS department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.id IN ($placeholders) ORDER BY e.created_at DESC");
    $stmt->execute($ids);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    header("Location: employees.php");
    exit;
}

// Friendly column headers
$colLabels = [
    'employee_id'             => 'Employee ID',
    'first_name'              => 'First Name',
    'last_name'               => 'Last Name',
    'email'                   => 'Work Email',
    'personal_email'          => 'Personal Email',
    'phone'                   => 'Phone',
    'department_name'         => 'Department',
    'job_title'               => 'Job Title',
    'employee_type'           => 'Employee Type',
    'status'                  => 'Status',
    'date_of_joining'         => 'Date of Joining',
    'date_of_confirmation'    => 'Date of Confirmation',
    'date_of_exit'            => 'Date of Exit',
    'direct_manager_name'     => 'Direct Manager',
    'location'                => 'Location',
    'base_location'           => 'Base Location',
    'user_code'               => 'User Code',
    'date_of_birth'           => 'Date of Birth',
    'gender'                  => 'Gender',
    'marital_status'          => 'Marital Status',
    'blood_group'             => 'Blood Group',
    'nationality'             => 'Nationality',
    'place_of_birth'          => 'Place of Birth',
    'emergency_contact_no'    => 'Emergency Contact',
    'address_line1'           => 'Address Line 1',
    'address_line2'           => 'Address Line 2',
    'city'                    => 'City',
    'state'                   => 'State',
    'zip_code'                => 'Zip Code',
    'country'                 => 'Country',
    'permanent_address_line1' => 'Permanent Address 1',
    'permanent_address_line2' => 'Permanent Address 2',
    'permanent_city'          => 'Permanent City',
    'permanent_state'         => 'Permanent State',
    'permanent_zip_code'      => 'Permanent Zip',
    'gross_salary'            => 'Gross Salary',
    'account_type'            => 'Account Type',
    'account_number'          => 'Account Number',
    'ifsc_code'               => 'IFSC Code',
    'pan'                     => 'PAN',
    'aadhar_no'               => 'Aadhar No',
    'uan_number'              => 'UAN Number',
    'pf_account_number'       => 'PF Account No',
    'employee_provident_fund' => 'Employee PF',
    'professional_tax'        => 'Professional Tax',
    'esi_number'              => 'ESI Number',
    'exempt_from_tax'         => 'Exempt from Tax',
    'passport_no'             => 'Passport No',
    'place_of_issue'          => 'Place of Issue',
    'passport_date_of_issue'  => 'Passport Issue Date',
    'passport_date_of_expiry' => 'Passport Expiry Date',
    'created_at'              => 'Created At',
];

// Skip internal/FK columns
$skip = ['id', 'department_id', 'country_code_phone'];

// Determine columns from first row + colLabels order
$exportCols = array_keys($colLabels);

$filename = 'employees_export_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// Header row
$headerRow = [];
foreach ($exportCols as $col) {
    if (in_array($col, $skip)) continue;
    $headerRow[] = $colLabels[$col] ?? ucwords(str_replace('_', ' ', $col));
}
// Append any custom columns not in the label map
if (!empty($employees)) {
    foreach (array_keys($employees[0]) as $col) {
        if (!in_array($col, $exportCols) && !in_array($col, $skip)) {
            $headerRow[] = ucwords(str_replace('_', ' ', $col));
            $exportCols[] = $col;
        }
    }
}
fputcsv($out, $headerRow);

// Data rows
foreach ($employees as $emp) {
    $row = [];
    foreach ($exportCols as $col) {
        if (in_array($col, $skip)) continue;
        $val = $emp[$col] ?? '';
        if ($col === 'exempt_from_tax') $val = $val ? 'Yes' : 'No';
        $row[] = $val;
    }
    fputcsv($out, $row);
}

fclose($out);
exit;
