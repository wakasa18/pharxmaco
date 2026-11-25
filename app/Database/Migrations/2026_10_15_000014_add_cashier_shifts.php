<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCashierShifts extends Migration
{
    public function up(): void
    {
        $id = ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true];
        $integer = ['type' => 'INT', 'null' => false];
        $nullableId = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true];
        $money = ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0];
        $text = ['type' => 'TEXT', 'null' => true];
        $json = ['type' => $this->db->DBDriver === 'SQLite3' ? 'TEXT' : 'LONGTEXT', 'null' => true];
        $date = ['type' => 'DATETIME', 'null' => true];
        $definitions = [
            'cashier_drawers' => [
                'id' => $id, 'branch_id' => $integer, 'drawer_name' => ['type' => 'VARCHAR', 'constraint' => 60],
                'status' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'active'],
                'active_shift_id' => $nullableId, 'last_shift_id' => $nullableId, 'created_at' => $date,
            ],
            'cashier_shifts' => [
                'id' => $id, 'drawer_id' => $nullableId, 'branch_id' => $integer, 'user_id' => $integer,
                'active_user_id' => ['type' => 'INT', 'null' => true], 'active_drawer_id' => $nullableId,
                'open_key' => ['type' => 'VARCHAR', 'constraint' => 64],
                'status' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'open'],
                'opening_cash' => $money, 'opening_expected' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
                'opening_difference' => $money, 'opening_note' => $text, 'opened_at' => $date, 'closed_at' => $date,
                'expected_cash' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
                'counted_cash' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
                'cash_difference' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
                'cash_left' => $money, 'banked_cash' => $money, 'closing_note' => $text, 'summary_json' => $json,
                'handover_to_user_id' => ['type' => 'INT', 'null' => true],
                'handover_status' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
                'handover_accepted_shift_id' => $nullableId,
            ],
            'cashier_events' => [
                'id' => $id, 'event_key' => ['type' => 'VARCHAR', 'constraint' => 100],
                'shift_id' => $nullableId, 'branch_id' => $integer, 'user_id' => $integer,
                'event_type' => ['type' => 'VARCHAR', 'constraint' => 20], 'sale_id' => $nullableId,
                'payment_method' => ['type' => 'VARCHAR', 'constraint' => 12], 'amount' => $money,
                'reason' => $text, 'snapshot_json' => $json, 'created_at' => $date,
            ],
            'cashier_held_sales' => [
                'id' => $id, 'hold_key' => ['type' => 'VARCHAR', 'constraint' => 64], 'user_id' => $integer,
                'branch_id' => $integer, 'label' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'cart_json' => array_replace($json, ['null' => false]), 'held_at' => $date,
            ],
            'cashier_shift_reviews' => [
                'id' => $id, 'shift_id' => $nullableId, 'reviewed_by' => $integer, 'review_note' => $text, 'reviewed_at' => $date,
            ],
            'cashier_event_reviews' => [
                'id' => $id, 'event_id' => $nullableId, 'reviewed_by' => $integer, 'review_note' => $text, 'reviewed_at' => $date,
            ],
            'cashier_shift_actions' => [
                'id' => $id, 'shift_id' => $nullableId, 'user_id' => $integer,
                'action' => ['type' => 'VARCHAR', 'constraint' => 32], 'note' => $text, 'created_at' => $date,
            ],
        ];
        $unique = ['cashier_drawers' => [['branch_id','drawer_name']], 'cashier_shifts' => [['open_key'],['active_user_id'],['active_drawer_id']],
            'cashier_events' => [['event_key']], 'cashier_held_sales' => [['hold_key']], 'cashier_shift_reviews' => [['shift_id']], 'cashier_event_reviews' => [['event_id']]];
        $indexes = ['cashier_shifts' => [['user_id','branch_id','opened_at']], 'cashier_events' => [['user_id','branch_id','created_at'],['shift_id','id'],['sale_id']],
            'cashier_held_sales' => [['user_id','branch_id']], 'cashier_shift_actions' => [['shift_id','id']]];
        foreach ($definitions as $table => $fields) {
            if ($this->db->tableExists($table)) continue;
            $this->forge->addField($fields); $this->forge->addKey('id', true);
            foreach ($unique[$table] ?? [] as $columns) $this->forge->addUniqueKey($columns);
            foreach ($indexes[$table] ?? [] as $columns) $this->forge->addKey($columns);
            $this->forge->createTable($table, true, $this->db->DBDriver === 'SQLite3' ? [] : ['ENGINE' => 'InnoDB']);
        }
        $this->db->resetDataCache();
        foreach ($this->db->table('branches')->get()->getResultArray() as $branch) {
            if (!$this->db->table('cashier_drawers')->where('branch_id', $branch['id'])->countAllResults()) {
                $this->db->table('cashier_drawers')->insert(['branch_id' => $branch['id'], 'drawer_name' => 'Main drawer', 'status' => 'active', 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
    }

    public function down(): void
    {
        throw new \LogicException('Cashier shifts and reconciliation history must be retained.');
    }
}
