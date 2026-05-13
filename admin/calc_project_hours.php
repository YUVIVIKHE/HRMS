<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('admin');

header('Content-Type: application/json');

$start = $_GET['start'] ?? '';
$end   = $_GET['end']   ?? '';

if (!$start || !$end || $start > $end) {
    echo json_encode(['hours'=>0,'days'=>0]); exit;
}

$db = getDB();

// Fetch holidays in range
$hols = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?");
$hols->execute([$start, $end]);
$holSet = array_flip($hols->fetchAll(PDO::FETCH_COLUMN));

$days = 0;
$d = new DateTime($start);
$e = new DateTime($end);
while ($d <= $e) {
    $dow  = (int)$d->format('N');
    $date = $d->format('Y-m-d');
    if ($dow !== 7 && !isset($holSet[$date])) $days++;
    $d->modify('+1 day');
}

echo json_encode(['hours' => round($days * 9, 2), 'days' => $days]);
