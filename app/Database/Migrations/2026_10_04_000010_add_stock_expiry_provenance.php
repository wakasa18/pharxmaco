<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStockExpiryProvenance extends Migration
{
    public function up(): void
    {
        $fields = [
            'branch_products' => ['stock_delivery_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true]],
            'sale_items' => [
                'stock_delivery_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'expiration_date_at_sale' => ['type' => 'DATE', 'null' => true],
            ],
        ];
        foreach ($fields as $table => $columns) {
            foreach ($columns as $column => $definition) {
                $this->db->resetDataCache();
                if (!$this->db->fieldExists($column, $table)) $this->forge->addColumn($table, [$column => $definition]);
            }
        }
    }

    public function down(): void
    {
        throw new \LogicException('Retain recorded delivery and expiry provenance when rolling back.');
    }
}
