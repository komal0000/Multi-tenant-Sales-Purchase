<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('id')->constrained('employees')->nullOnDelete();
            $table->foreignId('party_id')->nullable()->after('employee_code')->constrained('parties')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->after('party_id')->constrained('accounts')->nullOnDelete();
            $table->decimal('leave_days', 8, 2)->default(0)->after('deduction');
            $table->decimal('overtime_days', 8, 2)->default(0)->after('leave_days');
            $table->foreignId('expense_payment_id')->nullable()->after('remarks')->constrained('payments')->nullOnDelete();
            $table->timestamp('expense_saved_at')->nullable()->after('expense_payment_id');

            $table->index(['employee_id', 'salary_month']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'salary_month']);
            $table->dropConstrainedForeignId('expense_payment_id');
            $table->dropConstrainedForeignId('account_id');
            $table->dropConstrainedForeignId('party_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['expense_saved_at', 'overtime_days', 'leave_days']);
        });
    }
};
