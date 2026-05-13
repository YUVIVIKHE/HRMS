<?php
/**
 * auth/attendance_action.php
 * Handles clock-in / clock-out POST from employee and manager attendance pages.
 * Returns JSON for AJAX calls.
 */
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request.']); exit;
}

$db     = getDB();
$uid    = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$lat    = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng    = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$today  = date('Y-m-d');
$now    = date('Y-m-d H:i:s');

// ── Haversine distance in metres ─────────────────────────────
function haversine($lat1, $lng1, $lat2, $lng2): float {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

// ── Find matching location for this user ─────────────────────
function findLocation(PDO $db, int $uid, ?float $lat, ?float $lng): ?array {
    // Get locations assigned to this user; fall back to global (unassigned) locations
    $assigned = $db->prepare("
        SELECT al.* FROM attendance_locations al
        INNER JOIN user_locations ul ON al.id = ul.location_id
        WHERE ul.user_id = ? AND al.is_active = 1
        ORDER BY al.is_remote ASC
    ");
    $assigned->execute([$uid]);
    $locations = $assigned->fetchAll();

    // If no specific assignment, use all active locations (global fallback)
    if (empty($locations)) {
        $locations = $db->query("SELECT * FROM attendance_locations WHERE is_active = 1 ORDER BY is_remote ASC")->fetchAll();
    }

    foreach ($locations as $loc) {
        if ($loc['is_remote']) return $loc;
        if ($lat === null || $lng === null) continue;
        $dist = haversine($lat, $lng, (float)$loc['latitude'], (float)$loc['longitude']);
        if ($dist <= $loc['radius_m']) return $loc;
    }
    return null;
}

if ($action === 'clock_in') {
    // Check already clocked in today
    $existing = $db->prepare("SELECT id, clock_in FROM attendance_logs WHERE user_id = ? AND log_date = ?");
    $existing->execute([$uid, $today]);
    $row = $existing->fetch();

    if ($row && $row['clock_in']) {
        echo json_encode(['ok' => false, 'msg' => 'Already clocked in today at ' . date('h:i A', strtotime($row['clock_in'])) . '.']); exit;
    }

    $loc = findLocation($db, $uid, $lat, $lng);
    if (!$loc) {
        echo json_encode(['ok' => false, 'msg' => 'You are not within any registered office location. Contact admin if you are working remotely.']); exit;
    }

    $status = $loc['is_remote'] ? 'remote' : 'present';
    // Late if after 09:30
    if (!$loc['is_remote'] && date('H:i') > '09:30') $status = 'late';

    if ($row) {
        $db->prepare("UPDATE attendance_logs SET clock_in=?, status=?, location_id=?, clock_in_lat=?, clock_in_lng=? WHERE id=?")
           ->execute([$now, $status, $loc['id'], $lat, $lng, $row['id']]);
    } else {
        $db->prepare("INSERT INTO attendance_logs (user_id, log_date, clock_in, status, location_id, clock_in_lat, clock_in_lng) VALUES (?,?,?,?,?,?,?)")
           ->execute([$uid, $today, $now, $status, $loc['id'], $lat, $lng]);
    }

    echo json_encode(['ok' => true, 'msg' => 'Clocked in at ' . date('h:i A') . ' — ' . htmlspecialchars($loc['name']), 'time' => date('h:i A'), 'location' => $loc['name']]);
    exit;
}

if ($action === 'clock_out') {
    $row = $db->prepare("SELECT * FROM attendance_logs WHERE user_id = ? AND log_date = ?");
    $row->execute([$uid, $today]);
    $row = $row->fetch();

    if (!$row || !$row['clock_in']) {
        echo json_encode(['ok' => false, 'msg' => 'You have not clocked in today.']); exit;
    }
    if ($row['clock_out']) {
        echo json_encode(['ok' => false, 'msg' => 'Already clocked out at ' . date('h:i A', strtotime($row['clock_out'])) . '.']); exit;
    }

    $workSec = strtotime($now) - strtotime($row['clock_in']);
    $db->prepare("UPDATE attendance_logs SET clock_out=?, work_seconds=?, clock_out_lat=?, clock_out_lng=? WHERE id=?")
       ->execute([$now, $workSec, $lat, $lng, $row['id']]);

    $h = floor($workSec/3600); $m = floor(($workSec%3600)/60);
    $hrsStr = sprintf('%dh %02dm', $h, $m);
    $short  = $workSec < 32400;

    echo json_encode([
        'ok'      => true,
        'msg'     => "Clocked out at " . date('h:i A') . ". Total: $hrsStr" . ($short ? ' (less than 9h mandatory)' : ''),
        'time'    => date('h:i A'),
        'hours'   => $hrsStr,
        'short'   => $short,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
