<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT_NAME = 'payments_sale_purchase_xor_chk';

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE payments ADD CONSTRAINT ' . self::CONSTRAINT_NAME . ' CHECK (sale_id IS NULL OR purchase_id IS NULL)'
            );
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments DROP CHECK ' . self::CONSTRAINT_NAME);
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS ' . self::CONSTRAINT_NAME);
        }
    }
};
