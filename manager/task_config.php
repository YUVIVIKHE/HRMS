<?php
/**
 * Shared task configuration — include once per request.
 * Defines SUBTASKS constant and workingDays() helper.
 */
if (!defined('SUBTASKS_DEFINED')) {
    define('SUBTASKS_DEFINED', true);
    define('SUBTASKS', [
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
