<?php
/**
 * auth/attendance_action.php
 * Handles clock-in / clock-out POST from employee and manager attendance pages.
 * Returns JSON for AJAX calls.
 */
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';

// Set IST timezone — all date/time operations use India Standard Time
date_default_timezone_set('Asia/Kolkata');

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
    $assigned = $db->prepare("
        SELECT al.* FROM attendance_locations al
        INNER JOIN user_locations ul ON al.id = ul.location_id
        WHERE ul.user_id = ? AND al.is_active = 1
        ORDER BY al.is_remote ASC
    ");
    $assigned->execute([$uid]);
    $locations = $assigned->fetchAll();

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
    // Check if previous day has unresolved attendance (clocked in but no clock out, no approved regularization)
    $prevCheck = $db->prepare("
        SELECT al.log_date, al.clock_in, al.clock_out
        FROM attendance_logs al
        WHERE al.user_id = ? AND al.log_date < ? AND al.clock_in IS NOT NULL AND al.clock_out IS NULL
        ORDER BY al.log_date DESC LIMIT 1
    ");
    $prevCheck->execute([$uid, $today]);
    $unresolved = $prevCheck->fetch();

    if ($unresolved) {
        // Check if there's an approved regularization for that date
        $regCheck = $db->prepare("SELECT id FROM attendance_regularizations WHERE user_id=? AND log_date=? AND status='approved'");
        $regCheck->execute([$uid, $unresolved['log_date']]);
        if (!$regCheck->fetch()) {
            $unresolvedDate = date('d M Y', strtotime($unresolved['log_date']));
            echo json_encode(['ok' => false, 'msg' => "Cannot clock in. You have an unresolved attendance on $unresolvedDate (clocked in but no clock out). Please submit a regularization request first."]); exit;
        }
    }

    // Also check if yesterday was a working day and no attendance at all (absent without regularization)
    // Only check if user has at least one attendance record (skip for new employees)
    $hasAnyAttendance = $db->prepare("SELECT id FROM attendance_logs WHERE user_id=? LIMIT 1");
    $hasAnyAttendance->execute([$uid]);
    if ($hasAnyAttendance->fetch()) {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yDow = (int)date('N', strtotime($yesterday));
        if ($yDow < 6) { // Mon-Fri only (skip Sat/Sun)
            $yLog = $db->prepare("SELECT id FROM attendance_logs WHERE user_id=? AND log_date=?");
            $yLog->execute([$uid, $yesterday]);
            if (!$yLog->fetch()) {
                // Check if on leave or has regularization
                $onLeave = $db->prepare("SELECT id FROM leave_applications WHERE user_id=? AND status='approved' AND from_date<=? AND to_date>=?");
                $onLeave->execute([$uid, $yesterday, $yesterday]);
                $hasReg = $db->prepare("SELECT id FROM attendance_regularizations WHERE user_id=? AND log_date=? AND status='approved'");
                $hasReg->execute([$uid, $yesterday]);
                if (!$onLeave->fetch() && !$hasReg->fetch()) {
                    $yDateStr = date('d M Y', strtotime($yesterday));
                    echo json_encode(['ok' => false, 'msg' => "Cannot clock in. You were absent on $yDateStr without approved leave or regularization. Please submit a regularization request first."]); exit;
                }
            }
        }
    }

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

// ── Get assigned tasks (for clock-out modal) ─────────────────
if ($action === 'get_tasks') {
    $tasks = $db->prepare("
        SELECT ta.id, ta.subtask, ta.status, ta.hours AS assigned_hours,
               p.project_name, p.project_code,
               COALESCE((SELECT SUM(tpl.hours_worked) FROM task_progress_logs tpl WHERE tpl.task_id = ta.id), 0) AS utilized_hours
        FROM task_assignments ta
        JOIN projects p ON ta.project_id = p.id
        WHERE ta.assigned_to = ? AND ta.status != 'Completed'
          AND ta.from_date <= ? AND ta.to_date >= ?
        ORDER BY p.project_name, ta.subtask
    ");
    $tasks->execute([$uid, $today, $today]);
    $tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);

    // Get today's work hours
    $row = $db->prepare("SELECT clock_in, work_seconds FROM attendance_logs WHERE user_id = ? AND log_date = ?");
    $row->execute([$uid, $today]);
    $row = $row->fetch();
    $workHrs = 0;
    if ($row && $row['clock_in']) {
        $workSec = strtotime($now) - strtotime($row['clock_in']);
        $workHrs = round($workSec / 3600, 2);
    }

    echo json_encode(['ok' => true, 'tasks' => $tasks, 'work_hours' => $workHrs]);
    exit;
}

// ── Clock out with task progress ─────────────────────────────
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

    // Save task progress if provided
    $taskProgress = json_decode($_POST['task_progress'] ?? '[]', true);
    if (!empty($taskProgress) && is_array($taskProgress)) {
        $insertStmt = $db->prepare("INSERT INTO task_progress_logs (task_id, user_id, attendance_id, log_date, hours_worked, progress) VALUES (?,?,?,?,?,?)");
        $updateStmt = $db->prepare("UPDATE task_assignments SET status=? WHERE id=? AND assigned_to=?");

        foreach ($taskProgress as $tp) {
            $taskId = (int)($tp['task_id'] ?? 0);
            $hours  = (float)($tp['hours'] ?? 0);
            $prog   = $tp['progress'] ?? 'In Progress';

            if ($taskId <= 0 || $hours <= 0) continue;
            if (!in_array($prog, ['Pending','In Progress','Completed','On Hold'])) $prog = 'In Progress';

            // Verify task belongs to this user
            $chk = $db->prepare("SELECT id FROM task_assignments WHERE id=? AND assigned_to=?");
            $chk->execute([$taskId, $uid]);
            if (!$chk->fetch()) continue;

            $insertStmt->execute([$taskId, $uid, $row['id'], $today, $hours, $prog]);
            $updateStmt->execute([$prog, $taskId, $uid]);
        }
    }

    $h = floor($workSec/3600); $m = floor(($workSec%3600)/60);
    $hrsStr = sprintf('%dh %02dm', $h, $m);
    $short  = $workSec < 32400;

    // ── Auto ACL: If today is Saturday/Sunday/Holiday, auto-submit ACL request ──
    $todayDow = (int)date('N'); // 6=Sat, 7=Sun
    $isWeekend = ($todayDow === 6 || $todayDow === 7);
    $isHolidayToday = false;
    try {
        $holChk = $db->prepare("SELECT id FROM holidays WHERE holiday_date=?");
        $holChk->execute([$today]);
        $isHolidayToday = (bool)$holChk->fetch();
    } catch (Exception $e) {}

    if (($isWeekend || $isHolidayToday) && $workSec > 0) {
        // Auto-create ACL request for weekend/holiday work (pending approval)
        $workHrsForACL = round($workSec / 3600, 2);
        $reason = $isWeekend ? 'Worked on weekend (' . date('l') . ')' : 'Worked on holiday';
        try {
            $aclChk = $db->prepare("SELECT id FROM acl_requests WHERE user_id=? AND work_date=?");
            $aclChk->execute([$uid, $today]);
            if (!$aclChk->fetch()) {
                $db->prepare("INSERT INTO acl_requests (user_id, work_date, reason, hours) VALUES (?,?,?,?)")
                   ->execute([$uid, $today, $reason, $workHrsForACL]);
            }
        } catch (Exception $e) {}
    }

    echo json_encode([
        'ok'      => true,
        'msg'     => "Clocked out at " . date('h:i A') . ". Total: $hrsStr" . ($short ? ' (less than 9h mandatory)' : '') . (($isWeekend || $isHolidayToday) ? ' — ACL request sent for approval.' : ''),
        'time'    => date('h:i A'),
        'hours'   => $hrsStr,
        'short'   => $short,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
