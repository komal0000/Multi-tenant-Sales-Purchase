@props([
    'generalized' => [],
    'itemWise' => [],
    'expenseWise' => [],
    'includeExpense' => false,
])

<div class="grid gap-4 {{ $includeExpense ? 'xl:grid-cols-3' : 'xl:grid-cols-2' }}">
    @include('reports.partials.breakdown-table', [
        'title' => 'Generalized',
        'rows' => $generalized,
        'labelHeading' => 'Description',
        'emptyText' => 'No generalized lines found.',
    ])

    @include('reports.partials.breakdown-table', [
        'title' => 'Item Wise',
        'rows' => $itemWise,
        'labelHeading' => 'Item',
        'emptyText' => 'No item lines found.',
    ])

    @if ($includeExpense)
        @include('reports.partials.breakdown-table', [
            'title' => 'Expense Category Wise',
            'rows' => $expenseWise,
            'labelHeading' => 'Expense Category',
            'emptyText' => 'No expense category lines found.',
        ])
    @endif
</div>
