-- ============================================================================
-- Daher Phone — Repair Shop Management System
-- Database schema for MySQL 8+ / MariaDB 10.4+ (XAMPP default)
--
-- HOW TO INSTALL:
--   phpMyAdmin → "Import" tab → choose this file → Go
--   (the database `daher_store` is created automatically)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `daher_store`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `daher_store`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- users — single admin today; schema ready for staff accounts later
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)      NOT NULL,
  `password_hash` VARCHAR(255)     NOT NULL,
  `full_name`     VARCHAR(100)     NOT NULL,
  `email`         VARCHAR(150)     NULL,
  `role`          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
  `last_login_at` DATETIME         NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- categories
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL,
  `description` VARCHAR(255)  NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- products — cost/price as DECIMAL, soft-delete via is_active
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `category_id`     INT UNSIGNED   NOT NULL,
  `name`            VARCHAR(150)   NOT NULL,
  `description`     TEXT           NULL,
  `barcode`         VARCHAR(64)    NULL,
  `cost_price`      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `selling_price`   DECIMAL(12,2)  NULL DEFAULT NULL,
  `quantity`        INT            NOT NULL DEFAULT 0,
  `min_stock`       INT            NOT NULL DEFAULT 3,
  `warranty_days`   INT            NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_barcode` (`barcode`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_active` (`is_active`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- customers
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `phone`      VARCHAR(30)   NULL,
  `email`      VARCHAR(150)  NULL,
  `address`    VARCHAR(255)  NULL,
  `notes`      TEXT          NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- sales — header. Totals denormalized for fast reporting.
-- status: completed | cancelled  (cancelled sales restock automatically)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `invoice_no`     VARCHAR(20)    NOT NULL,
  `customer_id`    INT UNSIGNED   NULL,
  `user_id`        INT UNSIGNED   NOT NULL,
  `subtotal`       DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `total_cost`     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `paid_amount`    DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','card','credit','bank_transfer','other') NOT NULL DEFAULT 'cash',
  `status`         ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
  `notes`          VARCHAR(255)   NULL,
  `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_invoice` (`invoice_no`),
  KEY `idx_sales_customer` (`customer_id`),
  KEY `idx_sales_user` (`user_id`),
  KEY `idx_sales_created` (`created_at`),
  KEY `idx_sales_status` (`status`),
  CONSTRAINT `fk_sales_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- sale_items — snapshots name, price AND cost at time of sale
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `sale_id`          INT UNSIGNED   NOT NULL,
  `product_id`       INT UNSIGNED   NULL,
  `product_name`     VARCHAR(150)   NOT NULL,
  `quantity`         INT            NOT NULL,
  `unit_price`       DECIMAL(12,2)  NOT NULL,
  `unit_cost`        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `line_total`       DECIMAL(12,2)  NOT NULL,
  `warranty_days`    INT            NOT NULL DEFAULT 0,
  `warranty_expires` DATE           NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`),
  KEY `idx_sale_items_warranty` (`warranty_expires`),
  CONSTRAINT `fk_sale_items_sale`
    FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sale_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- repairs — ticket header with money summary
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `repairs`;
CREATE TABLE `repairs` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `ticket_no`    VARCHAR(20)    NOT NULL,
  `customer_id`  INT UNSIGNED   NOT NULL,
  `user_id`      INT UNSIGNED   NOT NULL,
  `device_type`  VARCHAR(50)    NOT NULL,
  `brand`        VARCHAR(50)    NULL,
  `model`        VARCHAR(80)    NULL,
  `serial_no`    VARCHAR(80)    NULL,
  `problem`      TEXT           NOT NULL,
  `tech_notes`   TEXT           NULL,
  `status`       ENUM('received','diagnosing','repairing','ready','delivered','cancelled')
                                NOT NULL DEFAULT 'received',
  `labor_cost`   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `parts_cost`   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `total_cost`   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `paid_amount`  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `received_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` DATETIME       NULL,
  `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_repairs_ticket` (`ticket_no`),
  KEY `idx_repairs_customer` (`customer_id`),
  KEY `idx_repairs_status` (`status`),
  KEY `idx_repairs_created` (`created_at`),
  CONSTRAINT `fk_repairs_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_repairs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- repair_parts — product_id NULL = external part bought for this job
-- unit_cost = what the shop paid, unit_price = what the customer is charged
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `repair_parts`;
CREATE TABLE `repair_parts` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `repair_id`  INT UNSIGNED   NOT NULL,
  `product_id` INT UNSIGNED   NULL,
  `part_name`  VARCHAR(150)   NOT NULL,
  `quantity`   INT            NOT NULL DEFAULT 1,
  `unit_cost`  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_repair_parts_repair` (`repair_id`),
  KEY `idx_repair_parts_product` (`product_id`),
  CONSTRAINT `fk_repair_parts_repair`
    FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_repair_parts_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- repair_status_history — powers the ticket timeline
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `repair_status_history`;
CREATE TABLE `repair_status_history` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `repair_id`  INT UNSIGNED  NOT NULL,
  `status`     VARCHAR(20)   NOT NULL,
  `note`       VARCHAR(255)  NULL,
  `user_id`    INT UNSIGNED  NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rsh_repair` (`repair_id`),
  CONSTRAINT `fk_rsh_repair`
    FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- expenses
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150)   NOT NULL,
  `category`     VARCHAR(50)    NOT NULL DEFAULT 'General',
  `amount`       DECIMAL(12,2)  NOT NULL,
  `expense_date` DATE           NOT NULL,
  `notes`        VARCHAR(255)   NULL,
  `user_id`      INT UNSIGNED   NULL,
  `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- stock_movements — journal of every stock change (audit trail)
-- change_qty: positive = stock in, negative = stock out
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED  NOT NULL,
  `change_qty` INT           NOT NULL,
  `type`       ENUM('sale','sale_cancel','repair','repair_remove','restock','adjustment','initial','return')
                             NOT NULL,
  `reference`  VARCHAR(50)   NULL,
  `note`       VARCHAR(255)  NULL,
  `user_id`    INT UNSIGNED  NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_product` (`product_id`),
  KEY `idx_sm_created` (`created_at`),
  CONSTRAINT `fk_sm_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- settings — key/value store
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(50)  NOT NULL,
  `setting_value` TEXT         NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- customer_payments — money received against credit invoices
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `customer_payments`;
CREATE TABLE `customer_payments` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `sale_id`     INT UNSIGNED   NOT NULL,
  `customer_id` INT UNSIGNED   NULL,
  `amount`      DECIMAL(12,2)  NOT NULL,
  `method`      ENUM('cash','card','return_credit') NOT NULL DEFAULT 'cash',
  `notes`       VARCHAR(255)   NULL,
  `user_id`     INT UNSIGNED   NULL,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cp_sale` (`sale_id`),
  KEY `idx_cp_customer` (`customer_id`),
  KEY `idx_cp_created` (`created_at`),
  CONSTRAINT `fk_cp_sale`
    FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cp_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- product_returns + return_items — goods coming back into stock
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_returns`;
CREATE TABLE `product_returns` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `return_no`   VARCHAR(20)    NOT NULL,
  `sale_id`     INT UNSIGNED   NOT NULL,
  `customer_id` INT UNSIGNED   NULL,
  `reason`      VARCHAR(255)   NOT NULL,
  `total_value` DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `user_id`     INT UNSIGNED   NULL,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_returns_no` (`return_no`),
  KEY `idx_pr_sale` (`sale_id`),
  KEY `idx_pr_customer` (`customer_id`),
  KEY `idx_pr_created` (`created_at`),
  CONSTRAINT `fk_pr_sale`
    FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `return_id`    INT UNSIGNED   NOT NULL,
  `sale_item_id` INT UNSIGNED   NULL,
  `product_id`   INT UNSIGNED   NULL,
  `product_name` VARCHAR(150)   NOT NULL,
  `quantity`     INT            NOT NULL,
  `unit_price`   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `line_total`   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_ri_return` (`return_id`),
  KEY `idx_ri_sale_item` (`sale_item_id`),
  KEY `idx_ri_product` (`product_id`),
  CONSTRAINT `fk_ri_return`
    FOREIGN KEY (`return_id`) REFERENCES `product_returns` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ri_sale_item`
    FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ri_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- refunds — money going back to the customer
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `refund_no`   VARCHAR(20)    NOT NULL,
  `sale_id`     INT UNSIGNED   NOT NULL,
  `customer_id` INT UNSIGNED   NULL,
  `amount`      DECIMAL(12,2)  NOT NULL,
  `reason`      VARCHAR(255)   NOT NULL,
  `method`      ENUM('cash','card') NOT NULL DEFAULT 'cash',
  `user_id`     INT UNSIGNED   NULL,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refunds_no` (`refund_no`),
  KEY `idx_rf_sale` (`sale_id`),
  KEY `idx_rf_customer` (`customer_id`),
  KEY `idx_rf_created` (`created_at`),
  CONSTRAINT `fk_rf_sale`
    FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_rf_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Default admin — username: admin  password: admin123
-- *** CHANGE THIS PASSWORD after first login (Settings → My Profile) ***
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`) VALUES
('admin', '$2y$10$40PQLN49AfoleIKcTTfYieMDsXkxv9RCJwvTbKY29I/AWIzJJzjFu', 'Shop Owner', 'admin');

INSERT INTO `categories` (`name`, `description`) VALUES
('Mobile Phones', 'Smartphones and feature phones'),
('Laptops',       'Notebooks and ultrabooks'),
('Computers',     'Desktop PCs and all-in-ones'),
('Accessories',   'Cases, chargers, cables, headphones'),
('Spare Parts',   'Screens, batteries, connectors and repair parts');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name',        'Daher Phone'),
('shop_address',     ''),
('shop_phone',       ''),
('shop_email',       ''),
('currency_symbol',  '$'),
('currency_position','before'),
('receipt_footer',   'Thank you for your business!'),
('default_min_stock','3'),
('date_format',      'd/m/Y'),
('accent_color',     '#0d9488');
