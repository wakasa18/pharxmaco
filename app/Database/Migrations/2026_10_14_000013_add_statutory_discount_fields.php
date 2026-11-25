<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStatutoryDiscountFields extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache();
        $fields = [
            'products' => ['senior_pwd_eligible' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true]],
            'sale_items' => [
                'vat_exemption_amount' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
                'statutory_discount_kind' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            ],
        ];
        foreach ($fields as $table => $columns) {
            foreach ($columns as $name => $definition) {
                if (!$this->db->fieldExists($name, $table)) $this->forge->addColumn($table, [$name => $definition]);
            }
        }
        $this->db->resetDataCache();
    }

    public function down(): void
    {
        foreach (['products' => ['senior_pwd_eligible'], 'sale_items' => ['vat_exemption_amount', 'statutory_discount_kind']] as $table => $names) {
            foreach ($names as $name) if ($this->db->fieldExists($name, $table)) $this->forge->dropColumn($table, $name);
        }
        $this->db->resetDataCache();
    }
}
