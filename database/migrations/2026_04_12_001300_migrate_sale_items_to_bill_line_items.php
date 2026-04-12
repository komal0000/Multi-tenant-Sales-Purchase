<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bill_line_items')->insertUsing(
            ['bill_type', 'bill_id', 'line_type', 'item_id', 'description', 'expense_category_id', 'qty', 'rate', 'total'],
            DB::table('sale_items')->selectRaw("'sale', sale_id, 'general', NULL, particular, NULL, qty, price, total")
        );
    }

    public function down(): void
    {
        DB::table('bill_line_items')
            ->where('bill_type', 'sale')
            ->delete();
    }
};
