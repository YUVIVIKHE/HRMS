<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

// ── Fetch all active leave types (dynamic) ───────────────────
$leaveTypes = $db->query("SELECT id, name, color FROM leave_types ORDER BY id")->fetchAll();

// ── Fetch all employees AND managers with their balances ─────────
$users = $db->query("
    SELECT u.id, u.name, u.email, u.role,
           e.employee_id, e.job_title, d.name AS department
    FROM users u
    INNER JOIN employees e ON e.email = u.email
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE u.role IN ('employee','manager') AND u.status = 'active'
    ORDER BY u.name
")->fetchAll();

// ── Build balance map: user_id → [type_id => {balance, used}] ─
$balMap = [];
$balRows = $db->query("SELECT user_id, leave_type_id, balance, used FROM leave_balances")->fetchAll();
foreach ($balRows as $b) {
    $balMap[$b['user_id']][$b['leave_type_id']] = [
        'balance' => (float)$b['balance'],
        'used'    => (float)$b['used'],
    ];
}

// ── ACL balances per user ────────────────────────────────────
$aclMap = [];
foreach ($users as $u) {
    $uid = $u['id'];
    $aclHrs = 0;
    // OT on working days
    try { $ot = $db->prepare("SELECT COALESCE(SUM(work_seconds - 32400),0) FROM attendance_logs WHERE user_id=? AND work_seconds > 39600 AND DAYOFWEEK(log_date) NOT IN (1,7)"); $ot->execute([$uid]); $aclHrs += round((float)$ot->fetchColumn() / 3600, 2); } catch(Exception $e){}
    // Approved ACL requests
    try { $ar = $db->prepare("SELECT COALESCE(SUM(hours),0) FROM acl_requests WHERE user_id=? AND status='approved'"); $ar->execute([$uid]); $aclHrs += (float)$ar->fetchColumn(); } catch(Exception $e){}
    // ACL used
    $aclUsed = 0;
    try { $au = $db->prepare("SELECT COALESCE(SUM(days),0) FROM leave_applications WHERE user_id=? AND leave_type_id=0 AND status IN ('approved','pending')"); $au->execute([$uid]); $aclUsed = (float)$au->fetchColumn(); } catch(Exception $e){}
    $aclEarned = round($aclHrs / 9, 2);
    $aclMap[$uid] = ['earned' => $aclEarned, 'used' => $aclUsed, 'available' => max(0, $aclEarned - $aclUsed)];
}

// ── Helpers ──────────────────────────────────────────────────
function esc2($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ── Build SpreadsheetML Excel ────────────────────────────────
$fname = 'Leave_Utilization_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">

<Styles>
  <Style ss:ID="title">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:Bold="1" ss:Size="13" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="sub_hdr">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:Bold="1" ss:Size="10" ss:Color="#374151"/>
    <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D1D5DB"/>
    </Borders>
  </Style>
  <Style ss:ID="col_hdr">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:Bold="1" ss:Color="#1E3A5F"/>
    <Interior ss:Color="#E8F0FE" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9CA3AF"/>
    </Borders>
  </Style>
  <Style ss:ID="normal">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="normal_l">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="alt">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Interior ss:Color="#F8FAFF" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="alt_l">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Interior ss:Color="#F8FAFF" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="green">
    <Alignment ss:Horizontal="Center"/>
    <Font ss:Bold="1" ss:Color="#065F46"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="red">
    <Alignment ss:Horizontal="Center"/>
    <Font ss:Bold="1" ss:Color="#991B1B"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="summary_hdr">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#2563EB" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="summary">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:Bold="1" ss:Color="#1E3A5F"/>
    <Interior ss:Color="#EFF6FF" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/></Borders>
  </Style>
</Styles>

<Worksheet ss:Name="Leave Utilization">
<Table>
  <!-- Column widths: #, Name, EmpID, Dept, Role, then 3 cols per leave type, then totals -->
  <Column ss:Width="30"/>
  <Column ss:Width="140"/>
  <Column ss:Width="70"/>
  <Column ss:Width="120"/>
  <Column ss:Width="70"/>
  <?php foreach($leaveTypes as $lt): ?>
  <Column ss:Width="60"/>
  <Column ss:Width="60"/>
  <Column ss:Width="60"/>
  <?php endforeach; ?>
  <Column ss:Width="70"/>
  <Column ss:Width="70"/>
  <Column ss:Width="70"/>

<?php
$totalCols = 5 + (count($leaveTypes) * 3) + 3; // base + per-type + totals
$mergeAcross = $totalCols - 1;

// ── Title row ────────────────────────────────────────────────
echo '<Row ss:Height="28"><Cell ss:MergeAcross="'.$mergeAcross.'" ss:StyleID="title"><Data ss:Type="String">LEAVE UTILIZATION REPORT — Generated '.date('d M Y H:i').'</Data></Cell></Row>'."\n";
echo '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>'."\n";

// ── Leave type group headers ─────────────────────────────────
echo '<Row ss:Height="20">';
echo '<Cell ss:MergeAcross="4" ss:StyleID="sub_hdr"><Data ss:Type="String">EMPLOYEE</Data></Cell>';
foreach ($leaveTypes as $lt) {
    echo '<Cell ss:MergeAcross="2" ss:StyleID="sub_hdr"><Data ss:Type="String">'.esc2(strtoupper($lt['name'])).'</Data></Cell>';
}
echo '<Cell ss:MergeAcross="2" ss:StyleID="sub_hdr"><Data ss:Type="String">ACL (COMPENSATORY)</Data></Cell>';
echo '<Cell ss:MergeAcross="2" ss:StyleID="sub_hdr"><Data ss:Type="String">TOTAL</Data></Cell>';
echo '</Row>'."\n";

// ── Column headers ───────────────────────────────────────────
echo '<Row ss:Height="18">';
foreach (['#','Name','Emp ID','Department','Role'] as $h) {
    echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">'.$h.'</Data></Cell>';
}
foreach ($leaveTypes as $lt) {
    echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Credited</Data></Cell>';
    echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Used</Data></Cell>';
    echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Balance</Data></Cell>';
}
// ACL columns
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Earned</Data></Cell>';
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Used</Data></Cell>';
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Available</Data></Cell>';
// Total columns
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Total Credited</Data></Cell>';
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Total Used</Data></Cell>';
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Total Balance</Data></Cell>';
echo '</Row>'."\n";

// ── Data rows ────────────────────────────────────────────────
$typeTotals = [];
foreach ($leaveTypes as $lt) {
    $typeTotals[$lt['id']] = ['credited'=>0,'used'=>0,'balance'=>0];
}
$grandCredited = 0; $grandUsed = 0; $grandBalance = 0;
$aclTotalEarned = 0; $aclTotalUsed = 0;

foreach ($users as $idx => $u) {
    $isAlt = ($idx % 2 === 1);
    $sn  = $isAlt ? 'alt'   : 'normal';
    $snl = $isAlt ? 'alt_l' : 'normal_l';

    $rowTotalCredited = 0; $rowTotalUsed = 0; $rowTotalBalance = 0;

    echo '<Row>';
    echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="Number">'.($idx+1).'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$snl.'"><Data ss:Type="String">'.esc2($u['name']).'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="String">'.esc2($u['employee_id']??'—').'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$snl.'"><Data ss:Type="String">'.esc2($u['department']??'—').'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="String">'.ucfirst($u['role']).'</Data></Cell>';

    foreach ($leaveTypes as $lt) {
        $b = $balMap[$u['id']][$lt['id']] ?? ['balance'=>0,'used'=>0];
        $credited = round($b['balance'] + $b['used'], 1);
        $used     = round($b['used'], 1);
        $balance  = round($b['balance'], 1);

        $rowTotalCredited += $credited;
        $rowTotalUsed     += $used;
        $rowTotalBalance  += $balance;

        $typeTotals[$lt['id']]['credited'] += $credited;
        $typeTotals[$lt['id']]['used']     += $used;
        $typeTotals[$lt['id']]['balance']  += $balance;

        echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="Number">'.$credited.'</Data></Cell>';
        echo '<Cell ss:StyleID="'.($used>0?'red':$sn).'"><Data ss:Type="Number">'.$used.'</Data></Cell>';
        echo '<Cell ss:StyleID="'.($balance>0?'green':$sn).'"><Data ss:Type="Number">'.$balance.'</Data></Cell>';
    }

    $grandCredited += $rowTotalCredited;
    $grandUsed     += $rowTotalUsed;
    $grandBalance  += $rowTotalBalance;

    // ACL columns (only for employees, not managers)
    $acl = ($u['role'] === 'employee') ? ($aclMap[$u['id']] ?? ['earned'=>0,'used'=>0,'available'=>0]) : ['earned'=>0,'used'=>0,'available'=>0];
    if ($u['role'] === 'employee') {
        $aclTotalEarned += $acl['earned'];
        $aclTotalUsed += $acl['used'];
        echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="Number">'.$acl['earned'].'</Data></Cell>';
        echo '<Cell ss:StyleID="'.($acl['used']>0?'red':$sn).'"><Data ss:Type="Number">'.$acl['used'].'</Data></Cell>';
        echo '<Cell ss:StyleID="'.($acl['available']>0?'green':$sn).'"><Data ss:Type="Number">'.$acl['available'].'</Data></Cell>';
    } else {
        echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="String">—</Data></Cell>';
        echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="String">—</Data></Cell>';
        echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="String">—</Data></Cell>';
    }

    // Total columns (include ACL)
    $rowTotalCredited += $acl['earned'];
    $rowTotalUsed += $acl['used'];
    $rowTotalBalance += $acl['available'];

    echo '<Cell ss:StyleID="'.$sn.'"><Data ss:Type="Number">'.round($rowTotalCredited,1).'</Data></Cell>';
    echo '<Cell ss:StyleID="red"><Data ss:Type="Number">'.round($rowTotalUsed,1).'</Data></Cell>';
    echo '<Cell ss:StyleID="green"><Data ss:Type="Number">'.round($rowTotalBalance,1).'</Data></Cell>';
    echo '</Row>'."\n";
}

// ── Summary row ──────────────────────────────────────────────
echo '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>'."\n";
echo '<Row ss:Height="20">';
echo '<Cell ss:MergeAcross="4" ss:StyleID="summary_hdr"><Data ss:Type="String">TOTALS ('.count($users).' employees)</Data></Cell>';
foreach ($leaveTypes as $lt) {
    echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($typeTotals[$lt['id']]['credited'],1).'</Data></Cell>';
    echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($typeTotals[$lt['id']]['used'],1).'</Data></Cell>';
    echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($typeTotals[$lt['id']]['balance'],1).'</Data></Cell>';
}
// ACL totals
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($aclTotalEarned,1).'</Data></Cell>';
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($aclTotalUsed,1).'</Data></Cell>';
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($aclTotalEarned - $aclTotalUsed,1).'</Data></Cell>';
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($grandCredited + $aclTotalEarned,1).'</Data></Cell>';
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($grandUsed + $aclTotalUsed,1).'</Data></Cell>';
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($grandBalance,1).'</Data></Cell>';
echo '</Row>'."\n";
?>

</Table>
</Worksheet>

<!-- Second sheet: Leave Type Summary -->
<Worksheet ss:Name="Leave Types">
<Table>
  <Column ss:Width="30"/>
  <Column ss:Width="160"/>
  <Column ss:Width="80"/>
  <Column ss:Width="80"/>
  <Column ss:Width="80"/>
  <Column ss:Width="80"/>
  <Column ss:Width="80"/>

  <Row ss:Height="24">
    <Cell ss:MergeAcross="6" ss:StyleID="title"><Data ss:Type="String">LEAVE TYPES CONFIGURATION</Data></Cell>
  </Row>
  <Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>
  <Row ss:Height="18">
    <?php foreach(['#','Leave Type','Days/Credit','Cycle','Carry Fwd','Total Credited','Total Used'] as $h): ?>
    <Cell ss:StyleID="col_hdr"><Data ss:Type="String"><?= esc2($h) ?></Data></Cell>
    <?php endforeach; ?>
  </Row>
  <?php
  $allTypes = $db->query("SELECT lt.*, SUM(lb.balance+lb.used) AS total_credited, SUM(lb.used) AS total_used FROM leave_types lt LEFT JOIN leave_balances lb ON lb.leave_type_id=lt.id GROUP BY lt.id ORDER BY lt.id")->fetchAll();
  foreach ($allTypes as $i => $lt):
    $sn = $i%2===1?'alt':'normal';
  ?>
  <Row>
    <Cell ss:StyleID="<?= $sn ?>"><Data ss:Type="Number"><?= $i+1 ?></Data></Cell>
    <Cell ss:StyleID="<?= $sn ?>_l"><Data ss:Type="String"><?= esc2($lt['name']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $sn ?>"><Data ss:Type="Number"><?= $lt['days_per_credit'] ?></Data></Cell>
    <Cell ss:StyleID="<?= $sn ?>"><Data ss:Type="String"><?= ucfirst($lt['credit_cycle']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $sn ?>"><Data ss:Type="String"><?= $lt['max_carry_fwd']>0?$lt['max_carry_fwd'].' days':'None' ?></Data></Cell>
    <Cell ss:StyleID="<?= $sn ?>"><Data ss:Type="Number"><?= round((float)($lt['total_credited']??0),1) ?></Data></Cell>
    <Cell ss:StyleID="<?= $sn ?>"><Data ss:Type="Number"><?= round((float)($lt['total_used']??0),1) ?></Data></Cell>
  </Row>
  <?php endforeach; ?>
</Table>
</Worksheet>

</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: ' . strlen($xml));
echo $xml;
exit;
?>
