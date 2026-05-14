<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

$db = getDB();

// ── Fetch all active leave types (dynamic) ───────────────────
$leaveTypes = $db->query("SELECT id, name, color FROM leave_types ORDER BY id")->fetchAll();

// ── Fetch all employees/managers with their balances ─────────
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
echo '<Cell ss:MergeAcross="2" ss:StyleID="sub_hdr"><Data ss:Type="String">TOTAL</Data></Cell>';
echo '<Cell ss:StyleID="sub_hdr"><Data ss:Type="String"></Data></Cell>';
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
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Total Credited</Data></Cell>';
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Total Used</Data></Cell>';
echo '<Cell ss:StyleID="col_hdr"><Data ss:Type="String">Total Balance</Data></Cell>';
echo '</Row>'."\n";

// ── Data rows ────────────────────────────────────────────────
// Summary accumulators per leave type
$typeTotals = [];
foreach ($leaveTypes as $lt) {
    $typeTotals[$lt['id']] = ['credited'=>0,'used'=>0,'balance'=>0];
}
$grandCredited = 0; $grandUsed = 0; $grandBalance = 0;

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
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($grandCredited,1).'</Data></Cell>';
echo '<Cell ss:StyleID="summary"><Data ss:Type="Number">'.round($grandUsed,1).'</Data></Cell>';
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
