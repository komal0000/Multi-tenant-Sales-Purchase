<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
