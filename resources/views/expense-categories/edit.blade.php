@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-900">Edit Expense Category</h1>
        <p class="mt-1 text-sm text-gray-500">Update expense category name or parent mapping.</p>

        <form action="{{ route('expense-categories.update', $category) }}" method="POST" class="mt-6 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Category Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
            </div>

            <div>
                <label for="parent_category_id" class="block text-sm font-medium text-gray-700">Parent Category</label>
                <select id="parent_category_id" name="parent_category_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">No parent</option>
                    @foreach ($parentOptions as $parent)
                        <option value="{{ $parent->id }}" @selected((string) old('parent_category_id', old('parent_id', $category->parent_category_id)) === (string) $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('expense-categories.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update Category</button>
            </div>
        </form>
    </div>
@endsection
