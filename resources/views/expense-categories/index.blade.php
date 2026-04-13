@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Expense Categories</h1>
                <p class="text-sm text-gray-500">Create reusable expense heads for purchase expense lines.</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-800">Add Expense Category</h2>
            <form action="{{ route('expense-categories.store') }}" method="POST" class="mt-4 grid gap-3 md:grid-cols-12">
                @csrf
                <div class="md:col-span-6">
                    <label for="name" class="block text-xs font-medium text-gray-600">Category Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-4">
                    <label for="parent_category_id" class="block text-xs font-medium text-gray-600">Parent Category</label>
                    <select id="parent_category_id" name="parent_category_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">No parent</option>
                        @foreach ($parentOptions as $parent)
                            <option value="{{ $parent->id }}" @selected((string) old('parent_category_id', old('parent_id')) === (string) $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2 md:flex md:items-end">
                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Category Tree</h2>
                    <p class="text-xs text-gray-500">Recursive, collapsible hierarchy with orphan and cycle protection.</p>
                </div>
                <a href="{{ route('expense-categories.tree') }}" target="_blank" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">View JSON Tree</a>
            </div>

            @if (empty($categoryTree))
                <p class="mt-4 text-sm text-gray-500">No categories available.</p>
            @else
                <ul class="mt-4 space-y-1">
                    @foreach ($categoryTree as $node)
                        @include('expense-categories.partials.tree-node', ['node' => $node, 'level' => 0])
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Parent</th>
                            <th class="px-4 py-3 text-left">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $category->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $category->parent?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $category->created_at?->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('expense-categories.edit', $category) }}" class="text-sm text-indigo-600 hover:text-indigo-700">Edit</a>
                                        <form action="{{ route('expense-categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete this expense category?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-gray-500">No expense categories created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $categories->links() }}
    </div>
@endsection
