<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_item_from_items_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('items.store'), [
                'name' => 'Demo Item',
                'qty' => '10',
                'rate' => '250.50',
                'cost_price' => '200.00',
            ])
            ->assertRedirect(route('items.index'));

        $this->assertDatabaseHas('items', [
            'name' => 'Demo Item',
            'tenant_id' => $user->tenant_id,
            'qty' => 10,
        ]);

        $this->assertDatabaseHas('item_ledgers', [
            'item_id' => Item::query()->where('name', 'Demo Item')->value('id'),
            'identifier' => 'opening_stock',
            'type' => 'in',
            'qty' => 10,
            'rate' => 200,
        ]);

        $this->actingAs($user)
            ->get(route('items.index'))
            ->assertOk()
            ->assertSee('Demo Item');
    }

    public function test_user_can_create_expense_category_with_parent(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $parent = ExpenseCategory::query()->create([
            'name' => 'Office Expense',
        ]);

        $this->actingAs($user)
            ->post(route('expense-categories.store'), [
                'name' => 'Stationery',
                'parent_category_id' => $parent->id,
            ])
            ->assertRedirect(route('expense-categories.index'));

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Stationery',
            'parent_id' => $parent->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->actingAs($user)
            ->get(route('expense-categories.index'))
            ->assertOk()
            ->assertSee('Stationery')
            ->assertSee('Office Expense');
    }

    public function test_user_can_edit_and_delete_item(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $item = Item::query()->create([
            'name' => 'Editable Item',
            'qty' => 0,
            'rate' => 100,
            'cost_price' => 80,
        ]);

        $this->actingAs($user)
            ->get(route('items.edit', $item))
            ->assertOk()
            ->assertSee('Edit Item');

        $this->actingAs($user)
            ->patch(route('items.update', $item), [
                'name' => 'Updated Item',
                'qty' => '0',
                'rate' => '150.00',
                'cost_price' => '120.00',
            ])
            ->assertRedirect(route('items.index'));

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Updated Item',
        ]);

        $this->actingAs($user)
            ->delete(route('items.destroy', $item))
            ->assertRedirect(route('items.index'));

        $this->assertDatabaseMissing('items', [
            'id' => $item->id,
        ]);
    }

    public function test_user_can_update_single_opening_stock_row_for_item(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('items.store'), [
                'name' => 'Opening Stock Item',
                'qty' => '5',
                'rate' => '150.0000',
                'cost_price' => '120.0000',
            ])
            ->assertRedirect(route('items.index'));

        $item = Item::query()->where('name', 'Opening Stock Item')->firstOrFail();

        $this->assertSame(1, ItemLedger::query()
            ->where('item_id', $item->id)
            ->where('identifier', 'opening_stock')
            ->count());

        $this->actingAs($user)
            ->patch(route('items.update', $item), [
                'name' => 'Opening Stock Item',
                'qty' => '2.5',
                'rate' => '175.0000',
                'cost_price' => '140.2500',
            ])
            ->assertRedirect(route('items.index'));

        $item->refresh();

        $openingRow = ItemLedger::query()
            ->where('item_id', $item->id)
            ->where('identifier', 'opening_stock')
            ->firstOrFail();

        $this->assertSame(1, ItemLedger::query()
            ->where('item_id', $item->id)
            ->where('identifier', 'opening_stock')
            ->count());
        $this->assertEqualsWithDelta(2.5, (float) $openingRow->qty, 0.0001);
        $this->assertEqualsWithDelta(140.25, (float) $openingRow->rate, 0.0001);
        $this->assertEqualsWithDelta(2.5, (float) $item->qty, 0.0001);
    }

    public function test_user_can_edit_and_delete_expense_category(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $parent = ExpenseCategory::query()->create([
            'name' => 'Main Parent',
        ]);

        $category = ExpenseCategory::query()->create([
            'name' => 'Editable Category',
            'parent_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('expense-categories.edit', $category))
            ->assertOk()
            ->assertSee('Edit Expense Category');

        $this->actingAs($user)
            ->patch(route('expense-categories.update', $category), [
                'name' => 'Updated Category',
                'parent_category_id' => $parent->id,
            ])
            ->assertRedirect(route('expense-categories.index'));

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'parent_id' => $parent->id,
        ]);

        $this->actingAs($user)
            ->delete(route('expense-categories.destroy', $category))
            ->assertRedirect(route('expense-categories.index'));

        $this->assertDatabaseMissing('expense_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_sales_and_purchase_create_pages_default_new_lines_to_general(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee("line_type: 'general'", false)
            ->assertSee('x-model.number="draftItem.total"', false);

        $this->actingAs($user)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee("line_type: 'general'", false)
            ->assertSee('x-model.number="draftItem.total"', false);
    }

    public function test_party_index_uses_quick_add_modal_instead_of_inline_entry_row(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('parties.index'))
            ->assertOk()
            ->assertSee('data-open-quick-party-entry', false)
            ->assertSee('data-party-post-save="reload"', false)
            ->assertDontSee('party-inline-entry-row', false);
    }

    public function test_account_ledger_uses_table_only_print_hook(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Print Cash',
            'type' => 'cash',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.ledger', $account))
            ->assertOk()
            ->assertSee('printAccountLedgerTable()', false)
            ->assertSee('account-ledger-print-table', false)
            ->assertDontSee('window.print()', false);
    }

    public function test_account_pages_render_edit_and_delete_actions(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $account = Account::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Editable Cash',
            'type' => 'cash',
        ]);

        $this->actingAs($admin)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSee(route('accounts.edit', $account), false)
            ->assertSee(route('accounts.destroy', $account), false);

        $this->actingAs($admin)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertSee('Edit Account')
            ->assertSee(route('accounts.edit', $account), false);
    }

    public function test_user_can_edit_unused_account_name_and_type(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Original Account',
            'type' => 'cash',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.edit', $account))
            ->assertOk()
            ->assertSee('Edit Account')
            ->assertSee('Original Account');

        $this->actingAs($user)
            ->patch(route('accounts.update', $account), [
                'name' => 'Updated Account',
                'type' => 'bank',
            ])
            ->assertRedirect(route('accounts.show', $account))
            ->assertSessionHas('success', 'Account updated successfully.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Account',
            'type' => 'bank',
        ]);
    }

    public function test_account_type_change_is_blocked_after_external_usage(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = Account::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Used Account',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Used Account Party',
        ]);

        Payment::query()->create([
            'tenant_id' => $user->tenant_id,
            'party_id' => $party->id,
            'amount' => 50,
            'type' => 'received',
            'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->from(route('accounts.edit', $account))
            ->patch(route('accounts.update', $account), [
                'name' => 'Used Account Renamed',
                'type' => 'bank',
            ])
            ->assertRedirect(route('accounts.edit', $account))
            ->assertSessionHasErrors('type');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Used Account',
            'type' => 'cash',
        ]);
    }

    public function test_admin_can_delete_unused_account_and_remove_its_opening_balance_ledger(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $account = Account::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Delete Me Cash',
            'type' => 'cash',
            'opening_balance' => 1000,
            'opening_balance_side' => 'dr',
            'opening_balance_date' => DateHelper::currentBsInt(),
        ]);

        app(LedgerService::class)->syncAccountOpeningBalance($account);

        $this->assertDatabaseHas('ledger', [
            'account_id' => $account->id,
            'type' => 'opening_balance',
            'ref_table' => 'accounts',
            'ref_id' => $account->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('success', 'Account deleted successfully.');

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id,
        ]);

        $this->assertDatabaseMissing('ledger', [
            'account_id' => $account->id,
            'type' => 'opening_balance',
            'ref_table' => 'accounts',
            'ref_id' => $account->id,
        ]);
    }

    public function test_admin_cannot_delete_account_with_payment_history(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $account = Account::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Payment History Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Payment History Party',
        ]);

        Payment::query()->create([
            'tenant_id' => $admin->tenant_id,
            'party_id' => $party->id,
            'amount' => 75,
            'type' => 'received',
            'account_id' => $account->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', 'This account has payment history and cannot be deleted.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_admin_cannot_delete_account_with_non_opening_ledger_history(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $account = Account::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Ledger History Cash',
            'type' => 'cash',
        ]);

        Ledger::query()->create([
            'tenant_id' => $admin->tenant_id,
            'party_id' => null,
            'account_id' => $account->id,
            'dr_amount' => 50,
            'cr_amount' => 0,
            'type' => 'payment',
            'ref_id' => 999,
            'ref_table' => 'payments',
            'date' => DateHelper::currentBsInt(),
        ]);

        $this->actingAs($admin)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', 'This account has ledger history and cannot be deleted.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_admin_cannot_delete_account_used_in_employee_salary(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $account = Account::query()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Salary Cash',
            'type' => 'cash',
        ]);

        EmployeeSalary::query()->create([
            'tenant_id' => $admin->tenant_id,
            'employee_name' => 'Salary User',
            'salary_date' => now()->toDateString(),
            'salary_month' => now()->format('Y-m'),
            'basic_salary' => 1000,
            'allowance' => 0,
            'deduction' => 0,
            'leave_days' => 0,
            'overtime_days' => 0,
            'net_salary' => 1000,
            'account_id' => $account->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', 'This account is linked to employee salary records and cannot be deleted.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_successful_sale_store_redirects_back_to_create_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Sale Redirect Party',
        ]);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'party_id' => $party->id,
                'date_bs' => DateHelper::getCurrentBS(),
                'items' => [
                    [
                        'line_type' => 'general',
                        'description' => 'General sale line',
                        'qty' => '1',
                        'rate' => '100',
                    ],
                ],
            ])
            ->assertRedirect(route('sales.create'))
            ->assertSessionHas('success', 'Sale created successfully.');
    }

    public function test_successful_purchase_store_redirects_back_to_create_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Purchase Redirect Party',
        ]);

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'party_id' => $party->id,
                'date_bs' => DateHelper::getCurrentBS(),
                'items' => [
                    [
                        'line_type' => 'general',
                        'description' => 'General purchase line',
                        'qty' => '1',
                        'rate' => '100',
                    ],
                ],
            ])
            ->assertRedirect(route('purchases.create'))
            ->assertSessionHas('success', 'Purchase created successfully.');
    }

    public function test_admin_can_view_and_update_payment_sidebar_setting(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Payment Sidebar Rows');

        $this->actingAs($admin)
            ->patch(route('settings.payroll.update'), [
                'leave_fine_per_day' => '100',
                'overtime_money_per_day' => '200',
                'payment_sidebar_limit' => '7',
            ])
            ->assertRedirect(route('settings.index'));

        $setting = PayrollSetting::query()->firstOrFail();
        $this->assertSame(7, (int) $setting->payment_sidebar_limit);
    }

    public function test_employee_create_and_update_manage_linked_party_details(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('employees.store'), [
                'name' => 'Auto Party Employee',
                'phone' => '9800000011',
                'address' => 'Kathmandu',
                'salary' => '25000',
            ])
            ->assertRedirect();

        $employee = Employee::query()->with('party')->firstOrFail();

        $this->assertDatabaseHas('parties', [
            'id' => $employee->party_id,
            'name' => 'Auto Party Employee',
            'phone' => '9800000011',
            'address' => 'Kathmandu',
        ]);

        $this->actingAs($user)
            ->patch(route('employees.update', $employee), [
                'name' => 'Updated Employee',
                'phone' => '9800000099',
                'address' => 'Pokhara',
                'salary' => '30000',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $employee->refresh();
        $employee->load('party');

        $this->assertSame('Updated Employee', $employee->party?->name);
        $this->assertSame('9800000099', $employee->party?->phone);
        $this->assertSame('Pokhara', $employee->party?->address);
        $this->assertEqualsWithDelta(30000, (float) $employee->salary, 0.01);
    }

    public function test_party_ledger_shows_quick_payment_modal_trigger(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Ledger Modal Party',
            'phone' => null,
        ]);

        Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $this->actingAs($user)
            ->get(route('parties.ledger', $party))
            ->assertOk()
            ->assertSee('data-open-quick-payment-modal', false)
            ->assertSee('Quick Payment');
    }

    public function test_account_opening_balance_update_keeps_backfilled_date_when_form_date_is_omitted(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        app(LedgerService::class)->ensureCompatibilitySchema();

        $openingDate = 20830105;

        $accountId = DB::table('accounts')->insertGetId([
            'name' => 'Backfilled Account',
            'type' => 'cash',
            'opening_balance' => 100,
            'opening_balance_side' => 'dr',
            'opening_balance_date' => null,
            'tenant_id' => $user->tenant_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ledger')->insert([
            'party_id' => null,
            'account_id' => $accountId,
            'dr_amount' => 100,
            'cr_amount' => 0,
            'type' => 'opening_balance',
            'ref_id' => $accountId,
            'ref_table' => 'accounts',
            'date' => $openingDate,
            'tenant_id' => $user->tenant_id,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('accounts.opening-balance.update', $accountId), [
                'opening_balance' => '250',
                'opening_balance_side' => 'dr',
            ])
            ->assertRedirect(route('accounts.show', $accountId));

        $this->assertSame(
            $openingDate,
            (int) DB::table('accounts')->where('id', $accountId)->value('opening_balance_date')
        );

        $this->assertDatabaseHas('ledger', [
            'account_id' => $accountId,
            'type' => 'opening_balance',
            'ref_table' => 'accounts',
            'date' => $openingDate,
        ]);
    }

    public function test_cashbook_labels_account_opening_rows_as_opening_balance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        app(LedgerService::class)->ensureCompatibilitySchema();

        $account = Account::query()->create([
            'name' => 'Cashbook Opening Account',
            'type' => 'cash',
            'opening_balance' => 0,
        ]);

        $openingDate = DateHelper::currentBsInt();

        DB::table('ledger')->insert([
            'party_id' => null,
            'account_id' => $account->id,
            'dr_amount' => 75,
            'cr_amount' => 0,
            'type' => 'opening_balance',
            'ref_id' => $account->id,
            'ref_table' => 'accounts',
            'date' => $openingDate,
            'tenant_id' => $user->tenant_id,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('reports.cashbook', [
                'account_id' => $account->id,
                'from_date_bs' => DateHelper::fromDateInt($openingDate),
                'to_date_bs' => DateHelper::fromDateInt($openingDate),
            ]))
            ->assertOk()
            ->assertDontSee('Accounts / '.$account->id)
            ->assertSee(number_format(75, 2));
    }

    public function test_account_ledger_does_not_double_count_opening_balance_without_from_date_filter(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        app(LedgerService::class)->ensureCompatibilitySchema();

        $account = Account::query()->create([
            'name' => 'Running Balance Cash',
            'type' => 'cash',
            'opening_balance' => 10000,
            'opening_balance_side' => 'dr',
            'opening_balance_date' => DateHelper::currentBsInt(),
        ]);

        app(LedgerService::class)->syncAccountOpeningBalance($account);

        $this->actingAs($user)
            ->get(route('accounts.ledger', $account))
            ->assertOk()
            ->assertViewHas('openingBalance', 0)
            ->assertSee('Balance Before Range')
            ->assertSee('10,000.00 Receivable')
            ->assertDontSee('20,000.00 Receivable');
    }

    public function test_cashbook_does_not_treat_current_rows_as_opening_balance_without_from_date_filter(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        app(LedgerService::class)->ensureCompatibilitySchema();

        $account = Account::query()->create([
            'name' => 'Cashbook Balance Cash',
            'type' => 'cash',
            'opening_balance' => 10000,
            'opening_balance_side' => 'dr',
            'opening_balance_date' => DateHelper::currentBsInt(),
        ]);

        app(LedgerService::class)->syncAccountOpeningBalance($account);

        $this->actingAs($user)
            ->get(route('reports.cashbook', [
                'account_id' => $account->id,
            ]))
            ->assertOk()
            ->assertViewHas('openingBalance', 0)
            ->assertSee('Balance Before Range')
            ->assertSee('10,000.00')
            ->assertDontSee('20,000.00');
    }

    public function test_sales_and_purchase_pages_show_account_notice_when_accounts_are_missing(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('No cash or bank account is available yet.');

        $this->actingAs($user)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('No cash or bank account is available yet.');
    }

    public function test_sidebar_renders_separate_sales_entry_and_list_links(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee(route('sales.create'), false)
            ->assertSee(route('sales.index'), false)
            ->assertSee('>Sales<', false)
            ->assertSee('>List Sales<', false);
    }

    public function test_sales_create_page_renders_quick_item_and_contextual_quick_party_hooks(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Item::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Demo Item',
            'qty' => 0,
            'rate' => 120,
            'cost_price' => 90,
        ]);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Quick Add Item')
            ->assertSee('id="sale-item-select"', false)
            ->assertSee('aria-label="Add line">+</button>', false)
            ->assertSee('data-quick-party-hide-opening="true"', false)
            ->assertSee('sales-entry-line-type', false)
            ->assertDontSee("addClass('mt-4')", false)
            ->assertDontSee('(Stock:', false);
    }

    public function test_purchase_create_page_renders_quick_item_and_quick_expense_category_hooks(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Item::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Purchase Item',
            'qty' => 0,
            'rate' => 120,
            'cost_price' => 90,
        ]);

        ExpenseCategory::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Travel',
        ]);

        $this->actingAs($user)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Quick Add Item')
            ->assertSee('Quick Add Category')
            ->assertSee('id="purchase-item-select"', false)
            ->assertSee('id="purchase-expense-category-select"', false)
            ->assertSee('aria-label="Add line">+</button>', false)
            ->assertSee('data-quick-party-hide-opening="true"', false)
            ->assertSee('purchase-entry-line-type', false)
            ->assertDontSee('(Stock:', false);
    }

    public function test_sales_and_purchase_total_enter_key_uses_item_commit_and_return_hook(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('@keydown.enter.prevent="commitItemFromTotal()"', false);

        $this->actingAs($user)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('@keydown.enter.prevent="commitItemFromTotal()"', false);
    }

    public function test_item_store_returns_json_payload_for_quick_add_requests(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('items.store'), [
                'name' => 'Quick Add Item',
                'rate' => '350',
                'cost_price' => '275',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Item created successfully.')
            ->assertJsonPath('item.name', 'Quick Add Item')
            ->assertJsonPath('item.rate', 350)
            ->assertJsonPath('item.cost_price', 275)
            ->assertJsonPath('item.qty', 0);

        $this->assertDatabaseHas('items', [
            'tenant_id' => $user->tenant_id,
            'name' => 'Quick Add Item',
            'qty' => 0,
        ]);
    }

    public function test_expense_category_store_returns_json_payload_for_quick_add_requests(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $parent = ExpenseCategory::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Operations',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('expense-categories.store'), [
                'name' => 'Fuel',
                'parent_category_id' => $parent->id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Expense category created successfully.')
            ->assertJsonPath('category.name', 'Fuel')
            ->assertJsonPath('category.parent_category_id', $parent->id);

        $this->assertDatabaseHas('expense_categories', [
            'tenant_id' => $user->tenant_id,
            'name' => 'Fuel',
            'parent_id' => $parent->id,
        ]);
    }
}
