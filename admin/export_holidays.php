<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');
$db = getDB();

$year = (int)($_GET['year'] ?? date('Y'));
$holidays = $db->prepare("SELECT * FROM holidays WHERE YEAR(holiday_date)=? ORDER BY holiday_date ASC");
$holidays->execute([$year]); $holidays = $holidays->fetchAll();

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$fname = 'Holidays_'.$year.'.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<?mso-application progid="Excel.Sheet"?>'."\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
  <Style ss:ID="title">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:Bold="1" ss:Size="13" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#1E3A5F" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="hdr">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:Bold="1" ss:Color="#1E3A5F"/>
    <Interior ss:Color="#E8F0FE" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#9CA3AF"/></Borders>
  </Style>
  <Style ss:ID="n">
    <Alignment ss:Horizontal="Center"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="nl">
    <Alignment ss:Horizontal="Left"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="a">
    <Alignment ss:Horizontal="Center"/>
    <Interior ss:Color="#F8FAFF" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="al">
    <Alignment ss:Horizontal="Left"/>
    <Interior ss:Color="#F8FAFF" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/></Borders>
  </Style>
  <Style ss:ID="today">
    <Alignment ss:Horizontal="Center"/>
    <Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/>
    <Font ss:Bold="1" ss:Color="#92400E"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FCD34D"/></Borders>
  </Style>
  <Style ss:ID="today_l">
    <Alignment ss:Horizontal="Left"/>
    <Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/>
    <Font ss:Bold="1" ss:Color="#92400E"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FCD34D"/></Borders>
  </Style>
</Styles>

<Worksheet ss:Name="Holidays <?= $year ?>">
<Table>
  <Column ss:Width="30"/>
  <Column ss:Width="180"/>
  <Column ss:Width="100"/>
  <Column ss:Width="90"/>
  <Column ss:Width="200"/>
  <Column ss:Width="80"/>

<?php
echo '<Row ss:Height="26"><Cell ss:MergeAcross="5" ss:StyleID="title"><Data ss:Type="String">COMPANY HOLIDAYS — '.$year.' ('.count($holidays).' holidays)</Data></Cell></Row>'."\n";
echo '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>'."\n";
echo '<Row ss:Height="18">';
foreach (['#','Holiday Title','Date','Day','Description','Status'] as $h) {
    echo '<Cell ss:StyleID="hdr"><Data ss:Type="String">'.e($h).'</Data></Cell>';
}
echo '</Row>'."\n";

$today = date('Y-m-d');
foreach ($holidays as $i => $h) {
    $isToday = $h['holiday_date'] === $today;
    $s  = $isToday ? 'today'   : ($i%2===1 ? 'a'  : 'n');
    $sl = $isToday ? 'today_l' : ($i%2===1 ? 'al' : 'nl');
    $status = $h['holiday_date'] === $today ? 'Today' : ($h['holiday_date'] > $today ? 'Upcoming' : 'Past');

    echo '<Row>';
    echo '<Cell ss:StyleID="'.$s.'"><Data ss:Type="Number">'.($i+1).'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$sl.'"><Data ss:Type="String">'.e($h['title']).'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$s.'"><Data ss:Type="String">'.date('d-M-Y', strtotime($h['holiday_date'])).'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$s.'"><Data ss:Type="String">'.date('l', strtotime($h['holiday_date'])).'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$sl.'"><Data ss:Type="String">'.e($h['description']??'').'</Data></Cell>';
    echo '<Cell ss:StyleID="'.$s.'"><Data ss:Type="String">'.$status.'</Data></Cell>';
    echo '</Row>'."\n";
}
?>
</Table>
</Worksheet>
</Workbook>
<?php
$xml = ob_get_clean();
header('Content-Length: '.strlen($xml));
echo $xml;
exit;
?>
