<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Batch-level expiry tracking.
 *
 * - product_batches        one row per received batch (lot) of a branch product
 * - sale_item_batches      which batches a sale line was taken from (FEFO)
 * - refund_item_batches    which batches a refunded line came from
 * - stock_logs.batch_id    batch a stock movement applied to
 *
 * branch_products.stock stays the total on hand and branch_products.expiration_date
 * becomes the nearest expiry among batches that still have stock, so existing
 * screens keep working. Existing stock is carried over as one "migration" batch per
 * branch product. Safe to run more than once.
 */
class AddProductBatches extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `product_batches` (
            `id` int NOT NULL AUTO_INCREMENT,
            `branch_product_id` int NOT NULL,
            `branch_id` int NOT NULL,
            `product_id` int NOT NULL,
            `batch_no` varchar(60) DEFAULT NULL,
            `expiration_date` date DEFAULT NULL,
            `quantity_received` int NOT NULL DEFAULT '0',
            `quantity_remaining` int NOT NULL DEFAULT '0',
            `cost_price` decimal(10,2) DEFAULT NULL,
            `supplier_id` int DEFAULT NULL,
            `delivery_id` varchar(32) NOT NULL,
            `source` varchar(20) NOT NULL DEFAULT 'delivery',
            `received_at` datetime DEFAULT NULL,
            `created_by` int DEFAULT NULL,
            `created_at` datetime DEFAULT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_product_batches_delivery` (`delivery_id`),
            UNIQUE KEY `uq_product_batches_lot` (`branch_product_id`,`batch_no`,`expiration_date`),
            KEY `idx_product_batches_fefo` (`branch_product_id`,`quantity_remaining`,`expiration_date`),
            KEY `idx_product_batches_branch_expiry` (`branch_id`,`expiration_date`),
            KEY `fk_product_batches_product` (`product_id`),
            CONSTRAINT `fk_product_batches_bp` FOREIGN KEY (`branch_product_id`) REFERENCES `branch_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_product_batches_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_product_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `chk_product_batches_qty` CHECK (`quantity_remaining` >= 0 AND `quantity_received` >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS `sale_item_batches` (
            `id` int NOT NULL AUTO_INCREMENT,
            `sale_item_id` int NOT NULL,
            `batch_id` int NOT NULL,
            `quantity` int NOT NULL,
            `quantity_returned` int NOT NULL DEFAULT '0',
            `delivery_id` varchar(32) NOT NULL,
            `batch_no` varchar(60) DEFAULT NULL,
            `expiration_date` date DEFAULT NULL,
            `created_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_sale_item_batches_item` (`sale_item_id`),
            KEY `idx_sale_item_batches_batch` (`batch_id`),
            CONSTRAINT `fk_sale_item_batches_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_sale_item_batches_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_sale_item_batches_qty` CHECK (`quantity` >= 0 AND `quantity_returned` >= 0 AND `quantity_returned` <= `quantity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS `refund_item_batches` (
            `id` int NOT NULL AUTO_INCREMENT,
            `refund_item_id` int NOT NULL,
            `batch_id` int NOT NULL,
            `quantity` int NOT NULL,
            `created_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_refund_item_batches_refund` (`refund_item_id`),
            KEY `idx_refund_item_batches_batch` (`batch_id`),
            CONSTRAINT `fk_refund_item_batches_refund` FOREIGN KEY (`refund_item_id`) REFERENCES `refund_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_refund_item_batches_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `chk_refund_item_batches_qty` CHECK (`quantity` > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->resetDataCache();
        if (!$this->db->fieldExists('batch_id', 'stock_logs')) {
            $this->forge->addColumn('stock_logs', ['batch_id' => ['type' => 'INT', 'null' => true]]);
            $this->db->query('ALTER TABLE `stock_logs` ADD KEY `idx_stock_logs_batch` (`batch_id`)');
        }

        // Carry current stock over as one batch per branch product. Rows that
        // already have a delivery reference are kept even at zero stock, so returns
        // for earlier sales still find their original delivery.
        $this->db->query("INSERT INTO `product_batches`
            (`branch_product_id`,`branch_id`,`product_id`,`batch_no`,`expiration_date`,`quantity_received`,`quantity_remaining`,
             `cost_price`,`delivery_id`,`source`,`received_at`,`created_at`,`updated_at`)
            SELECT bp.`id`, bp.`branch_id`, bp.`product_id`, NULL, bp.`expiration_date`, GREATEST(bp.`stock`,0), GREATEST(bp.`stock`,0),
                   bp.`cost_price`, COALESCE(bp.`stock_delivery_id`, REPLACE(UUID(),'-','')), 'migration',
                   COALESCE(bp.`updated_at`, bp.`created_at`), NOW(), NOW()
            FROM `branch_products` bp
            WHERE (bp.`stock` > 0 OR bp.`stock_delivery_id` IS NOT NULL)
              AND NOT EXISTS (SELECT 1 FROM `product_batches` pb WHERE pb.`branch_product_id` = bp.`id`)");

        $this->db->query("UPDATE `branch_products` bp
            JOIN `product_batches` pb ON pb.`branch_product_id` = bp.`id` AND pb.`source` = 'migration'
            SET bp.`stock_delivery_id` = pb.`delivery_id`
            WHERE bp.`stock_delivery_id` IS NULL");
    }

    public function down(): void
    {
        throw new \LogicException('Retain batch, delivery and expiry records when rolling back.');
    }
}
