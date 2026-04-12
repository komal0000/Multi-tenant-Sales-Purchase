<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tenantScopedTables = [
        'users',
        'parties',
        'accounts',
        'sales',
        'purchases',
        'payments',
        'ledger',
        'items',
        'expense_categories',
        'bill_line_items',
        'item_ledgers',
        'employees',
        'employee_leave_overtimes',
        'employee_salaries',
        'payroll_settings',
    ];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('timezone')->default('Asia/Kathmandu');
            $table->string('currency_code', 3)->default('NPR');
            $table->timestamps();
        });

        DB::table('tenants')->insert([
            'name' => 'Default Tenant',
            'code' => 'default',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaultTenantId = (int) DB::table('tenants')->where('code', 'default')->value('id');

        foreach ($this->tenantScopedTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });

            DB::table($tableName)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $defaultTenantId]);
        }

        Schema::table('parties', function (Blueprint $table) {
            $table->index(['tenant_id', 'name'], 'parties_tenant_name_idx');
            $table->index(['tenant_id', 'created_at'], 'parties_tenant_created_idx');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->index(['tenant_id', 'name'], 'accounts_tenant_name_idx');
            $table->index(['tenant_id', 'type', 'created_at'], 'accounts_tenant_type_created_idx');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->index(['tenant_id', 'name'], 'items_tenant_name_idx');
            $table->index(['tenant_id', 'created_at'], 'items_tenant_created_idx');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->index(['tenant_id', 'parent_id'], 'expense_categories_tenant_parent_idx');
            $table->index(['tenant_id', 'name'], 'expense_categories_tenant_name_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'sales_tenant_created_idx');
            $table->index(['tenant_id', 'party_id', 'created_at'], 'sales_tenant_party_created_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'purchases_tenant_created_idx');
            $table->index(['tenant_id', 'party_id', 'created_at'], 'purchases_tenant_party_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'payments_tenant_created_idx');
            $table->index(['tenant_id', 'party_id', 'created_at'], 'payments_tenant_party_created_idx');
            $table->index(['tenant_id', 'account_id', 'created_at'], 'payments_tenant_account_created_idx');
        });

        Schema::table('ledger', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'ledger_tenant_created_idx');
            $table->index(['tenant_id', 'type', 'created_at'], 'ledger_tenant_type_created_idx');
            $table->index(['tenant_id', 'party_id', 'created_at'], 'ledger_tenant_party_created_idx');
            $table->index(['tenant_id', 'account_id', 'created_at'], 'ledger_tenant_account_created_idx');
            $table->index(['tenant_id', 'ref_table', 'ref_id'], 'ledger_tenant_ref_idx');
        });

        Schema::table('bill_line_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'bill_type', 'bill_id'], 'bill_line_items_tenant_bill_idx');
            $table->index(['tenant_id', 'line_type', 'item_id'], 'bill_line_items_tenant_item_idx');
            $table->index(['tenant_id', 'line_type', 'expense_category_id'], 'bill_line_items_tenant_expense_idx');
        });

        Schema::table('item_ledgers', function (Blueprint $table) {
            $table->index(['tenant_id', 'item_id', 'created_at'], 'item_ledgers_tenant_item_created_idx');
            $table->index(['tenant_id', 'identifier', 'foreign_key'], 'item_ledgers_tenant_identifier_fk_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index(['tenant_id', 'party_id'], 'employees_tenant_party_idx');
            $table->index(['tenant_id', 'created_at'], 'employees_tenant_created_idx');
        });

        Schema::table('employee_leave_overtimes', function (Blueprint $table) {
            $table->index(['tenant_id', 'employee_id', 'bs_year', 'bs_month'], 'employee_leave_overtimes_tenant_employee_month_idx');
        });

        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->index(['tenant_id', 'salary_date'], 'employee_salaries_tenant_salary_date_idx');
            $table->index(['tenant_id', 'salary_month'], 'employee_salaries_tenant_salary_month_idx');
            $table->index(['tenant_id', 'employee_id', 'salary_month'], 'employee_salaries_tenant_employee_month_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['tenant_id', 'role'], 'users_tenant_role_idx');
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'payroll_settings_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropIndex('payroll_settings_tenant_created_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tenant_role_idx');
        });

        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropIndex('employee_salaries_tenant_salary_date_idx');
            $table->dropIndex('employee_salaries_tenant_salary_month_idx');
            $table->dropIndex('employee_salaries_tenant_employee_month_idx');
        });

        Schema::table('employee_leave_overtimes', function (Blueprint $table) {
            $table->dropIndex('employee_leave_overtimes_tenant_employee_month_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_tenant_party_idx');
            $table->dropIndex('employees_tenant_created_idx');
        });

        Schema::table('item_ledgers', function (Blueprint $table) {
            $table->dropIndex('item_ledgers_tenant_item_created_idx');
            $table->dropIndex('item_ledgers_tenant_identifier_fk_idx');
        });

        Schema::table('bill_line_items', function (Blueprint $table) {
            $table->dropIndex('bill_line_items_tenant_bill_idx');
            $table->dropIndex('bill_line_items_tenant_item_idx');
            $table->dropIndex('bill_line_items_tenant_expense_idx');
        });

        Schema::table('ledger', function (Blueprint $table) {
            $table->dropIndex('ledger_tenant_created_idx');
            $table->dropIndex('ledger_tenant_type_created_idx');
            $table->dropIndex('ledger_tenant_party_created_idx');
            $table->dropIndex('ledger_tenant_account_created_idx');
            $table->dropIndex('ledger_tenant_ref_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_tenant_created_idx');
            $table->dropIndex('payments_tenant_party_created_idx');
            $table->dropIndex('payments_tenant_account_created_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_tenant_created_idx');
            $table->dropIndex('purchases_tenant_party_created_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_created_idx');
            $table->dropIndex('sales_tenant_party_created_idx');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropIndex('expense_categories_tenant_parent_idx');
            $table->dropIndex('expense_categories_tenant_name_idx');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_tenant_name_idx');
            $table->dropIndex('items_tenant_created_idx');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('accounts_tenant_name_idx');
            $table->dropIndex('accounts_tenant_type_created_idx');
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->dropIndex('parties_tenant_name_idx');
            $table->dropIndex('parties_tenant_created_idx');
        });

        foreach ($this->tenantScopedTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex($tableName . '_tenant_id_index');
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('tenants');
    }
};
