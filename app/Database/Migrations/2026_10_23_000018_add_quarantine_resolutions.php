<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddQuarantineResolutions extends Migration
{
    public function up(): void
    {
        // Match legacy installations as well as fresh schemas (including signedness).
        $reference = function (string $table): array {
            if ($this->db->DBDriver !== 'MySQLi') return ['type' => 'INT'];
            $column = $this->db->query('SHOW COLUMNS FROM `' . $table . '` WHERE Field = ?', ['id'])->getRowArray();
            if (!$column || !preg_match('/^(int|bigint)(?:\(\d+\))?( unsigned)?$/D', $column['Type'], $parts)) {
                throw new \RuntimeException('Unsupported quarantine reference type: ' . $table);
            }
            return ['type' => strtoupper($parts[1]), 'unsigned' => !empty($parts[2])];
        };
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'refund_item_id' => $reference('refund_items'),
            'outcome' => ['type' => 'VARCHAR', 'constraint' => 12],
            'quantity' => ['type' => 'INT', 'unsigned' => true],
            'inspected_by' => $reference('users'),
            'inspected_at' => ['type' => 'DATETIME'],
            'note' => ['type' => 'VARCHAR', 'constraint' => 500],
            'stock_log_id' => $reference('stock_logs') + ['null' => true],
            'previous_stock' => ['type' => 'INT', 'null' => true],
            'new_stock' => ['type' => 'INT', 'null' => true],
            'snapshot_json' => ['type' => 'LONGTEXT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('refund_item_id');
        $this->forge->addKey(['outcome', 'inspected_at']);
        $named = $this->db->DBDriver !== 'SQLite3';
        $this->forge->addForeignKey('refund_item_id', 'refund_items', 'id', 'RESTRICT', 'RESTRICT', $named ? 'fk_phx_quarantine_refund' : '');
        $this->forge->addForeignKey('inspected_by', 'users', 'id', 'RESTRICT', 'RESTRICT', $named ? 'fk_phx_quarantine_inspector' : '');
        $this->forge->addForeignKey('stock_log_id', 'stock_logs', 'id', 'RESTRICT', 'RESTRICT', $named ? 'fk_phx_quarantine_log' : '');
        $this->forge->createTable('quarantine_resolutions', true, $this->db->DBDriver === 'SQLite3' ? [] : ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        throw new \LogicException('Keep quarantine inspection and stock audit records.');
    }
}
