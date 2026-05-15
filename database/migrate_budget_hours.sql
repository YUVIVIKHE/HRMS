-- Add budget_hours column to projects table
ALTER TABLE projects ADD COLUMN IF NOT EXISTS `budget_hours` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `total_hours`;
