-- تراجع عن ترحيل 002 (اختياري). يحذف سجل التذكيرات وأعمدة معالجة الإشعارات وصلاحية messages.send فقط.
-- تنبيه: يفقد سجل رسائل واتساب المسجَّل. خذ نسخة احتياطية قبل التنفيذ. لا يمسّ أي سجل مالي.
SET NAMES utf8mb4;
USE `sakan_property_manager`;
DROP TABLE IF EXISTS `reminders`;
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'handled_at') > 0,
  'ALTER TABLE `notifications` DROP COLUMN `handled_at`, DROP COLUMN `handled_by`, DROP COLUMN `snoozed_until`', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
DELETE FROM `role_permissions` WHERE `perm_code` = 'messages.send';
DELETE FROM `permissions` WHERE `code` = 'messages.send';
DELETE FROM `settings` WHERE `skey` IN ('whatsapp_country_code', 'maint_overdue_days');
UPDATE `settings` SET `svalue` = '1' WHERE `skey` = 'schema_version';
-- ملاحظة: التراجع مخصص للعودة إلى كود الإصدار 1. الكود الأحدث يعيد تطبيق الترحيل تلقائياً عند أول طلب.
