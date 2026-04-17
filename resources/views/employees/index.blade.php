@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Employees</h1>
                <p class="text-sm text-gray-500">Manage employee party details and base salary together.</p>
            </div>
            <a href="{{ route('employees.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">New Employee</a>
        </div>

        <form method="GET" action="{{ route('employees.index') }}" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Search</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Party name or phone" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
            </div>
            <div class="flex items-center gap-3 md:pb-0.5">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
                <a href="{{ route('employees.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[760px] w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-right">Salary</th>
                            <th class="px-4 py-3 text-left">Address</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr class="border-t border-gray-100 hover:bg-gray-50/70">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $employee->party?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $employee->party?->phone ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">{{ number_format((float) $employee->salary, 2) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $employee->party?->address ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('employees.show', $employee) }}" class="text-sm text-indigo-600 hover:text-indigo-700">View</a>
                                        <a href="{{ route('employees.edit', $employee) }}" class="text-sm text-gray-700 hover:text-gray-900">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500">No employees found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $employees->links() }}
    </div>
@endsection
