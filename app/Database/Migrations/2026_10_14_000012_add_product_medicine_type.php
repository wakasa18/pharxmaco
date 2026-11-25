<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductMedicineType extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache();
        if (!$this->db->fieldExists('medicine_type', 'products')) {
            $this->forge->addColumn('products', ['medicine_type' => [
                'type' => 'VARCHAR', 'constraint' => 20, 'null' => true,
            ]]);
        }
        $this->db->resetDataCache();
    }

    public function down(): void
    {
        if ($this->db->fieldExists('medicine_type', 'products')) $this->forge->dropColumn('products', 'medicine_type');
        $this->db->resetDataCache();
    }
}
