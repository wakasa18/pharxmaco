<?php

namespace App\Database\Migrations;

use App\Libraries\CashierForeignKeys;
use CodeIgniter\Database\Migration;

class EnforceCashierForeignKeys extends Migration
{
    public function up(): void
    {
        CashierForeignKeys::apply($this->db);
    }

    public function down(): void
    {
        throw new \LogicException('Cashier history referential integrity must be retained.');
    }
}
