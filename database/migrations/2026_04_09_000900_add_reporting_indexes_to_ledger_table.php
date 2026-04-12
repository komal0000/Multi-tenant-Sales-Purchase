<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'ledger_type_created_at_idx');
            $table->index(['ref_table', 'ref_id'], 'ledger_ref_table_ref_id_idx');
            $table->index(['party_id', 'created_at'], 'ledger_party_created_at_idx');
            $table->index(['account_id', 'created_at'], 'ledger_account_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ledger', function (Blueprint $table) {
            $table->dropIndex('ledger_type_created_at_idx');
            $table->dropIndex('ledger_ref_table_ref_id_idx');
            $table->dropIndex('ledger_party_created_at_idx');
            $table->dropIndex('ledger_account_created_at_idx');
        });
    }
};
