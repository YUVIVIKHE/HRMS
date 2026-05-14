-- ============================================================
--  Task Progress Tracking — Migration
--  Tracks hours worked on tasks during clock-out
-- ============================================================

CREATE TABLE IF NOT EXISTS `task_progress_logs` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `task_id`         INT UNSIGNED  NOT NULL COMMENT 'FK to task_assignments.id',
  `user_id`         INT UNSIGNED  NOT NULL COMMENT 'FK to users.id',
  `attendance_id`   INT UNSIGNED  NOT NULL COMMENT 'FK to attendance_logs.id',
  `log_date`        DATE          NOT NULL,
  `hours_worked`    DECIMAL(5,2)  NOT NULL DEFAULT 0,
  `progress`        ENUM('Pending','In Progress','Completed','On Hold') NOT NULL DEFAULT 'In Progress',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_task_user` (`task_id`, `user_id`),
  INDEX `idx_attendance` (`attendance_id`),
  INDEX `idx_log_date` (`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
