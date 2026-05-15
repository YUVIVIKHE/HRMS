<?php
/**
 * Shared task configuration — include once per request.
 * Role-based subtask lists + custom task entry.
 */
if (!defined('SUBTASKS_DEFINED')) {
    define('SUBTASKS_DEFINED', true);

    // Design Engineer tasks
    define('SUBTASKS_DESIGN', [
        'PANEL SEGREGATION',
        'FIELD BOX MARKING',
        'PRELIMINARY CAS',
        'PRELIMINARY ROUTING (PDF OR AUTOCAD)',
        'TRAY SIZING',
        'CABLE LENGTH',
        '3D ROUTING',
        'CAS UPDATE WITH RESPECT TO SCHEMATIC',
        'CABLE & ACCESSORIES EXTRACTION',
        'TRAY & ACCESSORIES EXTRACTION',
        'BOM',
        '2D ROUTING IN AUTOCAD',
        '2D ROUTING APPROVAL STATUS',
        'CHECKING WITH SALES / PM',
        'FINAL DOCUMENTATION',
    ]);

    // Site Engineer tasks
    define('SUBTASKS_SITE', [
        'PRE-STARTUP',
        'INSTALLATION',
        'COMMISSIONING',
    ]);

    // All subtasks combined (for backward compatibility)
    define('SUBTASKS', array_merge(SUBTASKS_DESIGN, SUBTASKS_SITE));
}

if (!function_exists('workingDays')) {
    function workingDays(string $from, string $to): int {
        if (!$from || !$to || $from > $to) return 0;
        $days = 0;
        $d = new DateTime($from);
        $e = new DateTime($to);
        while ($d <= $e) {
            if ((int)$d->format('N') !== 7) $days++;
            $d->modify('+1 day');
        }
        return $days;
    }
}
