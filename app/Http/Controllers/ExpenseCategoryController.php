<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Models\BillLineItem;
use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        [$categoryTree, ] = $this->buildCategoryTreePayload();

        $categories = ExpenseCategory::query()
            ->with('parent:id,name')
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $term = '%' . trim((string) $keyword) . '%';

                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->where('name', 'like', $term)
                        ->orWhereHas('parent', fn ($parentQuery) => $parentQuery->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $parentOptions = ExpenseCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('expense-categories.index', [
            'categories' => $categories,
            'parentOptions' => $parentOptions,
            'categoryTree' => $categoryTree,
            'filters' => [
                'keyword' => $filters['keyword'] ?? null,
            ],
        ]);
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('update', $expenseCategory);

        $parentOptions = ExpenseCategory::query()
            ->where('id', '!=', $expenseCategory->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('expense-categories.edit', [
            'category' => $expenseCategory,
            'parentOptions' => $parentOptions,
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $validated = $request->validated();
        $parentColumn = ExpenseCategory::parentColumn();

        $payload = [
            'name' => $validated['name'],
            $parentColumn => $validated['parent_category_id'] ?? null,
        ];

        ExpenseCategory::query()->create($payload);

        return redirect()
            ->route('expense-categories.index')
            ->with('success', 'Expense category created successfully.');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('update', $expenseCategory);

        $validated = $request->validated();
        $parentColumn = ExpenseCategory::parentColumn();

        $expenseCategory->update([
            'name' => $validated['name'],
            $parentColumn => $validated['parent_category_id'] ?? null,
        ]);

        return redirect()
            ->route('expense-categories.index')
            ->with('success', 'Expense category updated successfully.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete', $expenseCategory);

        if (BillLineItem::query()->where('expense_category_id', $expenseCategory->id)->exists()) {
            return redirect()
                ->route('expense-categories.index')
                ->with('error', 'This expense category is used in bills and cannot be deleted.');
        }

        $expenseCategory->delete();

        return redirect()
            ->route('expense-categories.index')
            ->with('success', 'Expense category deleted successfully.');
    }

    public function getCategoryTree(): JsonResponse
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        [$categoryTree, $orphanIds] = $this->buildCategoryTreePayload();

        return response()->json([
            'data' => $categoryTree,
            'meta' => [
                'orphan_count' => count($orphanIds),
            ],
        ]);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, int>}
     */
    private function buildCategoryTreePayload(): array
    {
        $parentColumn = ExpenseCategory::parentColumn();

        $roots = ExpenseCategory::query()
            ->roots()
            ->orderBy('name')
            ->with('allChildren')
            ->get();

        $orphans = ExpenseCategory::query()
            ->whereNotNull($parentColumn)
            ->where($parentColumn, '!=', 0)
            ->whereDoesntHave('parent')
            ->orderBy('name')
            ->with('allChildren')
            ->get();

        $orphanIds = $orphans->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $rootNodes = $roots
            ->concat($orphans)
            ->unique('id')
            ->values();

        return [
            $this->serializeTreeNodes($rootNodes, [], $orphanIds),
            $orphanIds,
        ];
    }

    /**
     * @param Collection<int, ExpenseCategory> $categories
     * @param array<int, int> $path
     * @param array<int, int> $orphanIds
     * @return array<int, array<string, mixed>>
     */
    private function serializeTreeNodes(Collection $categories, array $path, array $orphanIds): array
    {
        return $categories
            ->map(function (ExpenseCategory $category) use ($path, $orphanIds): array {
                $id = (int) $category->id;

                if (in_array($id, $path, true)) {
                    return [
                        'id' => $id,
                        'name' => (string) $category->name,
                        'parent_category_id' => $category->parent_category_id,
                        'children' => [],
                        'is_orphan' => in_array($id, $orphanIds, true),
                        'is_circular' => true,
                    ];
                }

                $nextPath = [...$path, $id];
                $children = $category->relationLoaded('allChildren')
                    ? $category->allChildren
                    : $category->children;

                return [
                    'id' => $id,
                    'name' => (string) $category->name,
                    'parent_category_id' => $category->parent_category_id,
                    'children' => $this->serializeTreeNodes($children, $nextPath, $orphanIds),
                    'is_orphan' => in_array($id, $orphanIds, true),
                    'is_circular' => false,
                ];
            })
            ->values()
            ->all();
    }
}
