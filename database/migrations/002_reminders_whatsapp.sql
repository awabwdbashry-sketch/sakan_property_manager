-- ترحيل 002: تذكيرات واتساب اليدوية + حالة معالجة الإشعارات + صلاحية الإرسال.
-- آمن ومتكرر التشغيل: لا يحذف ولا يعدّل أي بيانات موجودة. نفّذه في phpMyAdmin على قاعدة sakan_property_manager (تبويب SQL أو استيراد).
SET NAMES utf8mb4;
USE `sakan_property_manager`;

CREATE TABLE IF NOT EXISTS `reminders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind` VARCHAR(30) NOT NULL,
  `installment_id` INT UNSIGNED DEFAULT NULL,
  `payment_id` INT UNSIGNED DEFAULT NULL,
  `contract_id` INT UNSIGNED DEFAULT NULL,
  `maintenance_id` INT UNSIGNED DEFAULT NULL,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `expected_remaining` DECIMAL(14,2) DEFAULT NULL,
  `status` ENUM('approved','sent','not_sent','snoozed','dismissed') NOT NULL,
  `snooze_until` DATE DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `outcome_by` INT UNSIGNED DEFAULT NULL,
  `outcome_at` DATETIME DEFAULT NULL,
  `outcome_note` VARCHAR(300) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rem_inst` (`installment_id`),
  KEY `idx_rem_pay` (`payment_id`),
  KEY `idx_rem_contract` (`contract_id`),
  KEY `idx_rem_maint` (`maintenance_id`),
  KEY `idx_rem_status` (`status`, `created_at`),
  CONSTRAINT `fk_rem_inst` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rem_pay` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rem_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rem_maint` FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rem_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rem_appr` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rem_out` FOREIGN KEY (`outcome_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة أعمدة للإشعارات فقط إن لم تكن موجودة
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'handled_at') = 0,
  'ALTER TABLE `notifications` ADD COLUMN `handled_at` DATETIME DEFAULT NULL, ADD COLUMN `handled_by` INT UNSIGNED DEFAULT NULL, ADD COLUMN `snoozed_until` DATETIME DEFAULT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

INSERT IGNORE INTO `permissions` (`code`, `name`, `grp`, `sort_order`) VALUES
('messages.send', 'تجهيز رسائل واتساب اليدوية للمستأجرين وتسجيل نتيجتها', 'الرسائل', 95);
INSERT IGNORE INTO `role_permissions` (`role_id`, `perm_code`) VALUES (1, 'messages.send');

INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES ('whatsapp_country_code', '249'), ('maint_overdue_days', '7');
INSERT INTO `settings` (`skey`, `svalue`) VALUES ('schema_version', '2') ON DUPLICATE KEY UPDATE `svalue` = '2';
