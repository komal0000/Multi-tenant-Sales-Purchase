<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Item;
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
            'qty' => 5,
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
                'qty' => '12.5',
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
}
