<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('phone');
            $table->enum('opening_balance_side', ['dr', 'cr'])->default('dr')->after('opening_balance');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('type');
            $table->enum('opening_balance_side', ['dr', 'cr'])->default('dr')->after('opening_balance');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_side']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_side']);
        });
    }
};
