<?php

namespace Tests\Feature;

use App\Http\Controllers\ExpenseCategoryController;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ExpenseCategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_category_tree_returns_recursive_root_hierarchy(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $parentColumn = ExpenseCategory::parentColumn();

        $root = ExpenseCategory::query()->create([
            'name' => 'Office Expense',
        ]);

        $child = ExpenseCategory::query()->create([
            'name' => 'Travel',
            $parentColumn => $root->id,
        ]);

        ExpenseCategory::query()->create([
            'name' => 'Taxi',
            $parentColumn => $child->id,
        ]);

        $response = $this->actingAs($user)->get(route('expense-categories.tree'));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Office Expense')
            ->assertJsonPath('data.0.children.0.name', 'Travel')
            ->assertJsonPath('data.0.children.0.children.0.name', 'Taxi')
            ->assertJsonPath('data.0.children.0.children.0.children', []);
    }

    public function test_tree_serializer_marks_orphan_nodes(): void
    {
        $this->actingAs(User::factory()->create());

        $parentColumn = ExpenseCategory::parentColumn();

        $orphan = new ExpenseCategory();
        $orphan->id = 201;
        $orphan->name = 'Broken Parent Category';
        $orphan->setAttribute($parentColumn, 999999);
        $orphan->setRelation('allChildren', collect());

        $controller = app(ExpenseCategoryController::class);
        $method = new ReflectionMethod($controller, 'serializeTreeNodes');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $result */
        $result = $method->invoke($controller, collect([$orphan]), [], [201]);

        $this->assertTrue((bool) ($result[0]['is_orphan'] ?? false));
        $this->assertSame([], $result[0]['children'] ?? null);
    }

    public function test_tree_serializer_blocks_circular_references(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $parentColumn = ExpenseCategory::parentColumn();

        $a = new ExpenseCategory();
        $a->id = 101;
        $a->name = 'A';
        $a->setAttribute($parentColumn, null);

        $b = new ExpenseCategory();
        $b->id = 102;
        $b->name = 'B';
        $b->setAttribute($parentColumn, 101);

        $a->setRelation('allChildren', collect([$b]));
        $b->setRelation('allChildren', collect([$a]));

        $controller = app(ExpenseCategoryController::class);
        $method = new ReflectionMethod($controller, 'serializeTreeNodes');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $result */
        $result = $method->invoke($controller, collect([$a]), [], []);

        $this->assertTrue((bool) ($result[0]['children'][0]['children'][0]['is_circular'] ?? false));
    }
}
