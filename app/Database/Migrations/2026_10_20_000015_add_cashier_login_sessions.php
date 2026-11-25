<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCashierLoginSessions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'user_id' => ['type' => 'INT'],
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'account_fingerprint' => ['type' => 'VARCHAR', 'constraint' => 64],
            'last_seen_at' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'expires_at' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey('user_id', true);
        $this->forge->createTable('cashier_login_sessions', true, $this->db->DBDriver === 'SQLite3' ? [] : ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('cashier_login_sessions', true);
    }
}
