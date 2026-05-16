-- Add ACL eligibility column to employees table
ALTER TABLE employees ADD COLUMN IF NOT EXISTS `acl_eligible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=eligible for ACL, 0=not eligible';
