<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->string('employee_name');
            $table->string('employee_code')->nullable();
            $table->date('salary_date');
            $table->string('salary_month', 7);
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('deduction', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['salary_date']);
            $table->index(['salary_month']);
            $table->index(['employee_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
