-- =====================================================================
-- سَكَن | SAKAN Property Manager — مخطط قاعدة البيانات
-- متوافق مع MySQL 5.7+ / MariaDB 10.3+ (WAMP)
-- الاستيراد: phpMyAdmin > استيراد > اختيار هذا الملف
-- ملاحظة: لا يحتوي الملف على أي بيانات تجريبية، فقط الإعدادات الضرورية.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `sakan_property_manager` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sakan_property_manager`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- الأدوار والصلاحيات والمستخدمون
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `grp` VARCHAR(60) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `perm_code` VARCHAR(60) NOT NULL,
  PRIMARY KEY (`role_id`, `perm_code`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`perm_code`) REFERENCES `permissions` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_user_time` (`username`, `attempted_at`),
  KEY `idx_la_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الإعدادات والعدادات والقوائم القابلة للإعداد
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(80) NOT NULL,
  `svalue` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `counters` (
  `name` VARCHAR(40) NOT NULL,
  `value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pm_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ec_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- العقارات والوحدات والمستأجرون
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `properties` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(80) DEFAULT NULL,
  `district` VARCHAR(80) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `floors` SMALLINT UNSIGNED DEFAULT NULL,
  `manager_name` VARCHAR(120) DEFAULT NULL,
  `contact_phone` VARCHAR(40) DEFAULT NULL,
  `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_properties_code` (`code`),
  KEY `idx_properties_name` (`name`),
  KEY `idx_properties_status` (`status`),
  CONSTRAINT `fk_properties_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `units` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` INT UNSIGNED NOT NULL,
  `unit_no` VARCHAR(30) NOT NULL,
  `floor` VARCHAR(20) DEFAULT NULL,
  `unit_type` VARCHAR(60) DEFAULT NULL,
  `area` DECIMAL(10,2) DEFAULT NULL,
  `rooms` SMALLINT UNSIGNED DEFAULT NULL,
  `base_rent` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `deposit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('vacant','rented','reserved','maintenance','unavailable') NOT NULL DEFAULT 'vacant',
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_property_no` (`property_id`, `unit_no`),
  KEY `idx_units_status` (`status`),
  CONSTRAINT `fk_units_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  CONSTRAINT `fk_units_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(40) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `national_id` VARCHAR(50) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `alt_contact_name` VARCHAR(120) DEFAULT NULL,
  `alt_contact_phone` VARCHAR(40) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `registered_on` DATE NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenants_name` (`full_name`),
  KEY `idx_tenants_phone` (`phone`),
  CONSTRAINT `fk_tenants_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- العقود
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contracts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_no` VARCHAR(40) NOT NULL,
  `tenant_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED NOT NULL,
  `unit_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `rent_amount` DECIMAL(14,2) NOT NULL COMMENT 'الإيجار الشهري',
  `period` ENUM('monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',
  `deposit_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `due_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `terms` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('draft','active','expired','terminated') NOT NULL DEFAULT 'draft',
  `renewed_from_id` INT UNSIGNED DEFAULT NULL,
  `activated_at` DATETIME DEFAULT NULL,
  `terminated_on` DATE DEFAULT NULL,
  `termination_reason` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contracts_no` (`contract_no`),
  KEY `idx_contracts_unit_status` (`unit_id`, `status`),
  KEY `idx_contracts_tenant` (`tenant_id`),
  KEY `idx_contracts_property` (`property_id`),
  KEY `idx_contracts_end` (`end_date`, `status`),
  CONSTRAINT `fk_contracts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `fk_contracts_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  CONSTRAINT `fk_contracts_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_contracts_renewed` FOREIGN KEY (`renewed_from_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contracts_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contract_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(40) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `old_values` TEXT DEFAULT NULL,
  `new_values` TEXT DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ce_contract` (`contract_id`, `created_at`),
  CONSTRAINT `fk_ce_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  CONSTRAINT `fk_ce_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- التأمين (مبالغ منفصلة عن الإيجار المحصل)
CREATE TABLE IF NOT EXISTS `deposits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id` INT UNSIGNED NOT NULL,
  `kind` ENUM('received','refunded','deducted') NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `txn_date` DATE NOT NULL,
  `method_id` INT UNSIGNED DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dep_contract` (`contract_id`),
  CONSTRAINT `fk_dep_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  CONSTRAINT `fk_dep_method` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dep_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- الاستحقاقات والدفعات والإيصالات
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `installments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref_no` VARCHAR(60) NOT NULL,
  `contract_id` INT UNSIGNED NOT NULL,
  `seq_no` SMALLINT UNSIGNED NOT NULL,
  `tenant_id` INT UNSIGNED NOT NULL,
  `property_id` INT UNSIGNED NOT NULL,
  `unit_id` INT UNSIGNED NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `remaining` DECIMAL(14,2) NOT NULL,
  `status` ENUM('due','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'due',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inst_ref` (`ref_no`),
  UNIQUE KEY `uq_inst_contract_seq` (`contract_id`, `seq_no`),
  KEY `idx_inst_due` (`due_date`, `status`),
  KEY `idx_inst_tenant` (`tenant_id`),
  KEY `idx_inst_unit` (`unit_id`),
  KEY `idx_inst_property` (`property_id`),
  CONSTRAINT `fk_inst_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  CONSTRAINT `fk_inst_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `fk_inst_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  CONSTRAINT `fk_inst_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `txn_no` VARCHAR(40) NOT NULL,
  `installment_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `paid_on` DATE NOT NULL,
  `method_id` INT UNSIGNED NOT NULL,
  `reference_no` VARCHAR(80) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `idempotency_key` VARCHAR(64) NOT NULL,
  `status` ENUM('valid','void') NOT NULL DEFAULT 'valid',
  `void_reason` VARCHAR(255) DEFAULT NULL,
  `voided_by` INT UNSIGNED DEFAULT NULL,
  `voided_at` DATETIME DEFAULT NULL,
  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_txn` (`txn_no`),
  UNIQUE KEY `uq_pay_idem` (`idempotency_key`),
  KEY `idx_pay_inst` (`installment_id`),
  KEY `idx_pay_date` (`paid_on`, `status`),
  CONSTRAINT `fk_pay_inst` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`),
  CONSTRAINT `fk_pay_method` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`),
  CONSTRAINT `fk_pay_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_voider` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `receipts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_no` VARCHAR(40) NOT NULL,
  `payment_id` INT UNSIGNED NOT NULL,
  `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issued_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_no` (`receipt_no`),
  UNIQUE KEY `uq_receipt_payment` (`payment_id`),
  CONSTRAINT `fk_receipt_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `fk_receipt_user` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المصروفات والصيانة
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_no` VARCHAR(40) NOT NULL,
  `property_id` INT UNSIGNED NOT NULL,
  `unit_id` INT UNSIGNED DEFAULT NULL,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `reported_on` DATE NOT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('new','in_progress','waiting','completed','cancelled') NOT NULL DEFAULT 'new',
  `estimated_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `vendor` VARCHAR(150) DEFAULT NULL,
  `completed_on` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `hold_unit` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mr_no` (`request_no`),
  KEY `idx_mr_status` (`status`, `priority`),
  KEY `idx_mr_property` (`property_id`),
  KEY `idx_mr_unit` (`unit_id`),
  CONSTRAINT `fk_mr_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  CONSTRAINT `fk_mr_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_mr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mr_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_no` VARCHAR(40) NOT NULL,
  `property_id` INT UNSIGNED NOT NULL,
  `unit_id` INT UNSIGNED DEFAULT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `method_id` INT UNSIGNED DEFAULT NULL,
  `payee` VARCHAR(150) DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `maintenance_id` INT UNSIGNED DEFAULT NULL COMMENT 'ربط واحد لواحد مع بلاغ الصيانة لمنع الاحتساب المزدوج',
  `status` ENUM('paid','cancelled') NOT NULL DEFAULT 'paid',
  `cancel_reason` VARCHAR(255) DEFAULT NULL,
  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exp_no` (`expense_no`),
  UNIQUE KEY `uq_exp_maintenance` (`maintenance_id`),
  KEY `idx_exp_date` (`expense_date`, `status`),
  KEY `idx_exp_property` (`property_id`),
  KEY `idx_exp_category` (`category_id`),
  CONSTRAINT `fk_exp_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`),
  CONSTRAINT `fk_exp_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `fk_exp_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_exp_method` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_maintenance` FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_requests` (`id`),
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maintenance_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `event` VARCHAR(60) NOT NULL,
  `note` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ml_request` (`request_id`, `created_at`),
  CONSTRAINT `fk_ml_request` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`id`),
  CONSTRAINT `fk_ml_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- المرفقات والإشعارات وسجل التدقيق
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attachments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(30) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `kind` ENUM('image','document') NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(80) NOT NULL,
  `mime` VARCHAR(100) NOT NULL,
  `size` INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att_stored` (`stored_name`),
  KEY `idx_att_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_att_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(40) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `body` VARCHAR(500) DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `dedupe_key` VARCHAR(120) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_by` INT UNSIGNED DEFAULT NULL,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `handled_at` DATETIME DEFAULT NULL,
  `handled_by` INT UNSIGNED DEFAULT NULL,
  `snoozed_until` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_dedupe` (`dedupe_key`),
  KEY `idx_notif_read` (`is_read`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`read_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- سجل التدقيق: لا مفاتيح خارجية عمداً حتى لا يتأثر السجل بأي تعديل لاحق
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(60) DEFAULT NULL,
  `action` VARCHAR(60) NOT NULL,
  `entity_type` VARCHAR(40) DEFAULT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_time` (`created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- البيانات الأولية الضرورية فقط (لا توجد معاملات أو سجلات تجريبية)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `roles` (`id`, `code`, `name`, `is_system`) VALUES
(1, 'admin', 'مدير النظام', 1),
(2, 'staff', 'موظف الإدارة', 1);

INSERT IGNORE INTO `permissions` (`code`, `name`, `grp`, `sort_order`) VALUES
('dashboard.view', 'عرض لوحة التحكم', 'لوحة التحكم', 10),
('properties.view', 'عرض العقارات', 'العقارات', 20),
('properties.manage', 'إدارة العقارات (إضافة/تعديل/أرشفة)', 'العقارات', 21),
('units.view', 'عرض الوحدات', 'الوحدات', 30),
('units.manage', 'إدارة الوحدات', 'الوحدات', 31),
('tenants.view', 'عرض المستأجرين', 'المستأجرون', 40),
('tenants.manage', 'إدارة المستأجرين', 'المستأجرون', 41),
('tenants.sensitive', 'عرض البيانات الشخصية الحساسة (الهوية)', 'المستأجرون', 42),
('contracts.view', 'عرض العقود', 'العقود', 50),
('contracts.manage', 'إدارة العقود (إنشاء/تفعيل/تجديد/تعديل)', 'العقود', 51),
('contracts.terminate', 'إنهاء العقود', 'العقود', 52),
('payments.view', 'عرض الاستحقاقات والدفعات', 'التحصيل', 60),
('payments.collect', 'تسجيل الدفعات وإصدار الإيصالات', 'التحصيل', 61),
('payments.void', 'إلغاء الدفعات (تصحيح محاسبي)', 'التحصيل', 62),
('expenses.view', 'عرض المصروفات', 'المصروفات', 70),
('expenses.manage', 'إدارة المصروفات', 'المصروفات', 71),
('maintenance.view', 'عرض بلاغات الصيانة', 'الصيانة', 80),
('maintenance.manage', 'إدارة بلاغات الصيانة', 'الصيانة', 81),
('reports.view', 'عرض وتصدير التقارير', 'التقارير', 90),
('notifications.view', 'عرض الإشعارات', 'الإشعارات', 100),
('users.manage', 'إدارة المستخدمين والأدوار', 'النظام', 110),
('settings.manage', 'إدارة إعدادات النظام', 'النظام', 111),
('audit.view', 'عرض سجل التدقيق', 'النظام', 112),
('messages.send', 'تجهيز رسائل واتساب اليدوية للمستأجرين وتسجيل نتيجتها', 'الرسائل', 95);

-- مدير النظام: كل الصلاحيات
INSERT IGNORE INTO `role_permissions` (`role_id`, `perm_code`) SELECT 1, `code` FROM `permissions`;

-- موظف الإدارة: صلاحيات تشغيلية، دون الإعدادات والمستخدمين والتدقيق والإلغاء المالي
INSERT IGNORE INTO `role_permissions` (`role_id`, `perm_code`) VALUES
(2, 'dashboard.view'), (2, 'properties.view'), (2, 'properties.manage'),
(2, 'units.view'), (2, 'units.manage'), (2, 'tenants.view'), (2, 'tenants.manage'),
(2, 'contracts.view'), (2, 'contracts.manage'), (2, 'payments.view'), (2, 'payments.collect'),
(2, 'expenses.view'), (2, 'expenses.manage'), (2, 'maintenance.view'), (2, 'maintenance.manage'),
(2, 'reports.view'), (2, 'notifications.view');

INSERT IGNORE INTO `payment_methods` (`id`, `name`, `sort_order`) VALUES
(1, 'نقداً', 1),
(2, 'تحويل بنكي', 2);

INSERT IGNORE INTO `expense_categories` (`name`, `sort_order`) VALUES
('صيانة', 1), ('نظافة', 2), ('خدمات', 3),
('كهرباء ومياه للمناطق المشتركة', 4), ('مصروفات إدارية', 5), ('أخرى', 99);

INSERT IGNORE INTO `counters` (`name`, `value`) VALUES
('contract', 0), ('payment', 0), ('receipt', 0), ('expense', 0), ('maintenance', 0), ('property', 0);

INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES
('org_name', 'سَكَن لإدارة العقارات'),
('owner_name', ''),
('currency', 'ج.س'),
('timezone', 'Africa/Khartoum'),
('date_format', 'd/m/Y'),
('contact_phone', ''),
('contact_email', ''),
('contact_address', ''),
('alert_rent_days', '7'),
('alert_contract_days', '30'),
('receipt_prefix', 'RC'),
('contract_prefix', 'CN'),
('receipt_footer', 'هذا الإيصال صادر عن النظام ولا يحتاج إلى ختم إضافي إلا عند الطلب.'),
('whatsapp_country_code', '249'),
('maint_overdue_days', '7'),
('schema_version', '2'),
('setup_completed', '0');
