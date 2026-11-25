<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserPresenceSessions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
            'user_id' => ['type' => 'INT'],
            'account_fingerprint' => ['type' => 'VARCHAR', 'constraint' => 64],
            'cashier_token_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'BIGINT', 'unsigned' => true],
            'last_seen_at' => ['type' => 'BIGINT', 'unsigned' => true],
            'expires_at' => ['type' => 'BIGINT', 'unsigned' => true],
            'ended_at' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('token_hash', true);
        $this->forge->addKey(['user_id', 'last_seen_at']);
        $this->forge->createTable('user_presence_sessions', true, $this->db->DBDriver === 'SQLite3' ? [] : ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('user_presence_sessions', true);
    }
}
