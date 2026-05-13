<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

// Get custom columns
$allCols = $db->query("SHOW COLUMNS FROM employees")->fetchAll(PDO::FETCH_COLUMN);
$baseColumns = ['id','first_name','last_name','email','phone','job_title','date_of_birth','gender','marital_status','employee_id','department_id','employee_type','date_of_joining','date_of_exit','date_of_confirmation','direct_manager_name','location','base_location','user_code','address_line1','address_line2','city','state','zip_code','country','permanent_address_line1','permanent_address_line2','permanent_city','permanent_state','permanent_zip_code','account_type','account_number','ifsc_code','pan','aadhar_no','uan_number','pf_account_number','employee_provident_fund','professional_tax','esi_number','exempt_from_tax','passport_no','place_of_issue','passport_date_of_issue','passport_date_of_expiry','place_of_birth','nationality','blood_group','personal_email','emergency_contact_no','country_code_phone','status','created_at','gross_salary'];
$customCols = array_values(array_diff($allCols, $baseColumns));

// Fetch department names for the sample row hint
$depts = $db->query("SELECT name FROM departments ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$deptHint = implode(' / ', $depts);

// Fixed headers in friendly order — use "department" (name) not "department_id"
$headers = [
    'first_name', 'last_name', 'email', 'personal_email', 'phone',
    'employee_id', 'user_code', 'job_title', 'department',
    'employee_type', 'status',
    'date_of_joining', 'date_of_confirmation', 'date_of_exit',
    'direct_manager_name', 'location', 'base_location',
    'date_of_birth', 'gender', 'marital_status',
    'blood_group', 'nationality', 'place_of_birth', 'emergency_contact_no',
    'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country',
    'permanent_address_line1', 'permanent_address_line2', 'permanent_city', 'permanent_state', 'permanent_zip_code',
    'gross_salary',
    'account_type', 'account_number', 'ifsc_code',
    'pan', 'aadhar_no', 'uan_number', 'pf_account_number',
    'employee_provident_fund', 'professional_tax', 'esi_number', 'exempt_from_tax',
    'passport_no', 'place_of_issue', 'passport_date_of_issue', 'passport_date_of_expiry',
];

// Append any custom columns at the end
foreach ($customCols as $c) {
    if (!in_array($c, $headers)) $headers[] = $c;
}

// Sample row with accepted values as hints
$sample = [
    'first_name'               => 'John',
    'last_name'                => 'Doe',
    'email'                    => 'john.doe@company.com',
    'personal_email'           => 'john@gmail.com',
    'phone'                    => '+91-9876543210',
    'employee_id'              => 'EMP001',
    'user_code'                => 'USR001',
    'job_title'                => 'Software Engineer',
    'department'               => 'Engineering Design',   // must match a department name exactly
    'employee_type'            => 'FTE',                  // FTE or External
    'status'                   => 'active',               // active / inactive / terminated
    'date_of_joining'          => '2024-01-15',           // YYYY-MM-DD
    'date_of_confirmation'     => '2024-07-15',
    'date_of_exit'             => '',
    'direct_manager_name'      => 'Jane Smith',
    'location'                 => 'Mumbai',
    'base_location'            => 'Mumbai',
    'date_of_birth'            => '1995-06-20',
    'gender'                   => 'Male',                 // Male / Female / Other
    'marital_status'           => 'Single',               // Single / Married / Divorced / Widowed
    'blood_group'              => 'O+',
    'nationality'              => 'Indian',
    'place_of_birth'           => 'Mumbai',
    'emergency_contact_no'     => '+91-9876500000',
    'address_line1'            => '123 Main Street',
    'address_line2'            => 'Apt 4B',
    'city'                     => 'Mumbai',
    'state'                    => 'Maharashtra',
    'zip_code'                 => '400001',
    'country'                  => 'India',
    'permanent_address_line1'  => '456 Home Road',
    'permanent_address_line2'  => '',
    'permanent_city'           => 'Pune',
    'permanent_state'          => 'Maharashtra',
    'permanent_zip_code'       => '411001',
    'gross_salary'             => '50000.00',
    'account_type'             => 'Savings',              // Savings / Current
    'account_number'           => '123456789012',
    'ifsc_code'                => 'HDFC0001234',
    'pan'                      => 'ABCDE1234F',
    'aadhar_no'                => '1234-5678-9012',
    'uan_number'               => '100123456789',
    'pf_account_number'        => 'MH/BAN/12345/000/0000001',
    'employee_provident_fund'  => '1800',
    'professional_tax'         => '200',
    'esi_number'               => '',
    'exempt_from_tax'          => '0',                    // 0 or 1
    'passport_no'              => 'A1234567',
    'place_of_issue'           => 'Mumbai',
    'passport_date_of_issue'   => '2020-01-01',
    'passport_date_of_expiry'  => '2030-01-01',
];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=employee_bulk_template.csv');

$out = fopen('php://output', 'w');
fputcsv($out, $headers);

// Sample row
$row = [];
foreach ($headers as $h) {
    $row[] = $sample[$h] ?? '';
}
fputcsv($out, $row);

fclose($out);
exit;
