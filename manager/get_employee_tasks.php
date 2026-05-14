<?php
/**
 * AJAX endpoint: Returns task data, holidays, and leaves for a given employee/month.
 * Used by assign_task.php calendar.
 */
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
header('Content-Type: application/json');

$db  = getDB();
$empId = (int)($_GET['employee_id'] ?? 0);
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));

if (!$empId) { echo json_encode(['days' => [], 'holidays' => [], 'leaves' => []]); exit; }

$startOfMonth = sprintf('%04d-%02d-01', $year, $month);
$endOfMonth   = date('Y-m-t', strtotime($startOfMonth));

// ── Holidays (admin-added) ───────────────────────────────────
$holidays = [];
try {
    $hStmt = $db->prepare("SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $hStmt->execute([$startOfMonth, $endOfMonth]);
    foreach ($hStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $holidays[$h['holiday_date']] = $h['name'];
    }
} catch (Exception $e) {}

// ── Approved leaves for this employee ────────────────────────
$leaves = [];
try {
    $lStmt = $db->prepare("
        SELECT la.start_date, la.end_date, lt.name AS leave_type
        FROM leave_applications la
        LEFT JOIN leave_types lt ON la.leave_type_id = lt.id
        WHERE la.user_id = ? AND la.status = 'approved'
          AND la.start_date <= ? AND la.end_date >= ?
    ");
    $lStmt->execute([$empId, $endOfMonth, $startOfMonth]);
    foreach ($lStmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $d = new DateTime(max($startOfMonth, $l['start_date']));
        $e = new DateTime(min($endOfMonth, $l['end_date']));
        while ($d <= $e) {
            $leaves[$d->format('Y-m-d')] = $l['leave_type'] ?? 'Leave';
            $d->modify('+1 day');
        }
    }
} catch (Exception $e) {}

// ── Tasks ────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT ta.subtask, ta.from_date, ta.to_date, ta.hours, p.project_code
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = ? AND ta.from_date <= ? AND ta.to_date >= ?
    ORDER BY ta.from_date ASC
");
$stmt->execute([$empId, $endOfMonth, $startOfMonth]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build day map (exclude Sat, Sun, holidays, leaves)
$dayMap = [];

foreach ($tasks as $t) {
    $from = max($startOfMonth, $t['from_date']);
    $to   = min($endOfMonth, $t['to_date']);

    // Count working days (exclude Sat, Sun, holidays, leaves)
    $d = new DateTime($from);
    $e = new DateTime($to);
    $workDays = 0;
    while ($d <= $e) {
        $dow = (int)$d->format('N'); // 6=Sat, 7=Sun
        $ds = $d->format('Y-m-d');
        if ($dow < 6 && !isset($holidays[$ds]) && !isset($leaves[$ds])) $workDays++;
        $d->modify('+1 day');
    }

    $hrsPerDay = $workDays > 0 ? round($t['hours'] / $workDays, 2) : 0;

    $d = new DateTime($from);
    while ($d <= $e) {
        $dow = (int)$d->format('N');
        $ds = $d->format('Y-m-d');
        if ($dow < 6 && !isset($holidays[$ds]) && !isset($leaves[$ds])) {
            if (!isset($dayMap[$ds])) $dayMap[$ds] = ['total_hrs' => 0, 'tasks' => []];
            $dayMap[$ds]['total_hrs'] += $hrsPerDay;
            $dayMap[$ds]['tasks'][] = ['subtask' => $t['subtask'], 'project_code' => $t['project_code'], 'hours' => $hrsPerDay];
        }
        $d->modify('+1 day');
    }
}

echo json_encode([
    'days' => $dayMap,
    'holidays' => $holidays,
    'leaves' => $leaves
]);
