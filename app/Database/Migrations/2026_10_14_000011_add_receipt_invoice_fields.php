<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceiptInvoiceFields extends Migration
{
    public function up(): void
    {
        $fields = [
            'products' => [
                'generic_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'strength' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'tax_treatment' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            ],
            'sale_items' => [
                'generic_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'strength_snapshot' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'tax_treatment_snapshot' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            ],
            'sales' => ['receipt_metadata' => ['type' => 'TEXT', 'null' => true]],
        ];
        foreach ($fields as $table => $columns) {
            foreach ($columns as $column => $definition) {
                $this->db->resetDataCache();
                if (!$this->db->fieldExists($column, $table)) $this->forge->addColumn($table, [$column => $definition]);
            }
        }
        $this->forge->addField([
            'series_key' => ['type' => 'VARCHAR', 'constraint' => 64],
            'next_number' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addKey('series_key', true);
        $this->forge->createTable('receipt_invoice_sequences', true, $this->db->DBDriver === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : []);
        $this->db->resetDataCache();
    }

    public function down(): void
    {
        throw new \LogicException('Keep invoice serials and medicine snapshots for historical receipts.');
    }
}
