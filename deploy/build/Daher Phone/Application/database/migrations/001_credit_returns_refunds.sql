-- ============================================================================
-- Daher Phone — Migration 001 (v1.0 → v1.1)
-- Adds: warranty in days, optional selling price, credit payment method,
--        customer credit payments, product returns, money refunds.
--
-- SAFE FOR EXISTING DATA:
--   · warranty_months is converted to days (months × 30) before being dropped
--   · selling_price becomes NULLable (existing prices are kept)
--   · legacy payment methods (bank_transfer / other) remain readable on old
--     invoices — the UI simply no longer offers them
--
-- HOW TO APPLY (run ONCE):
--   phpMyAdmin → daher_store → Import → this file → Go
-- ============================================================================

USE `daher_store`;

-- ----------------------------------------------------------------------------
-- 1. Warranty in days
-- ----------------------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN `warranty_days` INT NOT NULL DEFAULT 0 AFTER `min_stock`;

UPDATE `products` SET `warranty_days` = `warranty_months` * 30;

ALTER TABLE `products` DROP COLUMN `warranty_months`;

-- Warranty snapshot on every sold line: days at sale time + computed expiry.
ALTER TABLE `sale_items`
  ADD COLUMN `warranty_days`    INT  NOT NULL DEFAULT 0 AFTER `line_total`,
  ADD COLUMN `warranty_expires` DATE NULL AFTER `warranty_days`,
  ADD KEY `idx_sale_items_warranty` (`warranty_expires`);

-- ----------------------------------------------------------------------------
-- 2. Selling price becomes optional (NULL = "price not set yet")
-- ----------------------------------------------------------------------------
ALTER TABLE `products`
  MODIFY COLUMN `selling_price` DECIMAL(12,2) NULL DEFAULT NULL;

-- ----------------------------------------------------------------------------
-- 3. Payment methods: cash / card / credit
--    Legacy values stay in the enum so historic invoices keep their labels.
-- ----------------------------------------------------------------------------
ALTER TABLE `sales`
  MODIFY COLUMN `payment_method`
    ENUM('cash','card','credit','bank_transfer','other') NOT NULL DEFAULT 'cash';

-- ----------------------------------------------------------------------------
-- 4. Stock ledger: new movement type for customer returns
-- ----------------------------------------------------------------------------
ALTER TABLE `stock_movements`
  MODIFY COLUMN `type`
    ENUM('sale','sale_cancel','repair','repair_remove','restock','adjustment','initial','return')
    NOT NULL;

-- ----------------------------------------------------------------------------
-- 5. customer_payments — money received against credit invoices
--    method 'return_credit' = debt reduced because goods were returned
-- ----------------------------------------------------------------------------
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
-- 6. product_returns + return_items — goods coming back into stock
-- ----------------------------------------------------------------------------
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
-- 7. refunds — money going back to the customer
-- ----------------------------------------------------------------------------
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

-- Done. The application code (v1.1) expects exactly this structure.
