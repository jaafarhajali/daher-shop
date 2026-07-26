-- ============================================================================
-- Daher Phone — Migration 002 (branding only, v1.3)
-- Renames the shop from "Daher Store" to "Daher Phone" in the settings table.
-- The condition keeps any custom name the owner may have set themselves.
--
-- HOW TO APPLY (run ONCE): phpMyAdmin → daher_store → Import → this file → Go
-- ============================================================================

USE `daher_store`;

UPDATE `settings`
SET `setting_value` = 'Daher Phone'
WHERE `setting_key` = 'shop_name'
  AND `setting_value` IN ('Daher Store', 'Daher Shop', 'Daher store');
