<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_name_index');
            $table->dropUnique('employees_code_unique');
            $table->dropColumn(['name', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('code')->nullable()->after('name');
            $table->unique('code');
        });
    }
};
