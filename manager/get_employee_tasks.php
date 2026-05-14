<?php
/**
 * AJAX endpoint: Returns task assignments for a given employee for a given month.
 * Used by the assign_task.php calendar.
 */
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/db.php';
guardRole('manager');
header('Content-Type: application/json');

$db  = getDB();
$empId = (int)($_GET['employee_id'] ?? 0);
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));

if (!$empId) { echo json_encode([]); exit; }

$startOfMonth = sprintf('%04d-%02d-01', $year, $month);
$endOfMonth   = date('Y-m-t', strtotime($startOfMonth));

// Get all tasks that overlap with this month
$stmt = $db->prepare("
    SELECT ta.subtask, ta.from_date, ta.to_date, ta.hours, p.project_code
    FROM task_assignments ta
    JOIN projects p ON ta.project_id = p.id
    WHERE ta.assigned_to = ? AND ta.from_date <= ? AND ta.to_date >= ?
    ORDER BY ta.from_date ASC
");
$stmt->execute([$empId, $endOfMonth, $startOfMonth]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a day-by-day map of hours assigned
$dayMap = []; // date => {total_hrs, tasks: [{subtask, project_code, hours}]}

foreach ($tasks as $t) {
    $from = max($startOfMonth, $t['from_date']);
    $to   = min($endOfMonth, $t['to_date']);
    
    // Count working days in this task's range within the month
    $d = new DateTime($from);
    $e = new DateTime($to);
    $workDays = 0;
    while ($d <= $e) {
        if ((int)$d->format('N') !== 7) $workDays++;
        $d->modify('+1 day');
    }
    
    // Distribute hours evenly across working days
    $hrsPerDay = $workDays > 0 ? round($t['hours'] / $workDays, 2) : 0;
    
    $d = new DateTime($from);
    while ($d <= $e) {
        if ((int)$d->format('N') !== 7) {
            $dateStr = $d->format('Y-m-d');
            if (!isset($dayMap[$dateStr])) {
                $dayMap[$dateStr] = ['total_hrs' => 0, 'tasks' => []];
            }
            $dayMap[$dateStr]['total_hrs'] += $hrsPerDay;
            $dayMap[$dateStr]['tasks'][] = [
                'subtask' => $t['subtask'],
                'project_code' => $t['project_code'],
                'hours' => $hrsPerDay
            ];
        }
        $d->modify('+1 day');
    }
}

echo json_encode($dayMap);
