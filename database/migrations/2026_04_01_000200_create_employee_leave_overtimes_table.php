<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_overtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('bs_year');
            $table->unsignedTinyInteger('bs_month');
            $table->decimal('leave_days', 8, 2)->default(0);
            $table->decimal('overtime_days', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'bs_year', 'bs_month']);
            $table->index(['bs_year', 'bs_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_overtimes');
    }
};
