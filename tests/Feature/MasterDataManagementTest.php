<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Party;
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
}
