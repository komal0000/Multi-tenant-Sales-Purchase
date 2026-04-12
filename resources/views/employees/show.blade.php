@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $employee->party?->name ?? '-' }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Phone: {{ $employee->party?->phone ?? '-' }}</p>
                </div>
                <a href="{{ route('employees.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</a>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Linked Party</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $employee->party?->name }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Party Phone</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $employee->party?->phone ?? '-' }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4">
                    <p class="text-sm text-gray-500">Base Salary</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((float) $employee->salary, 2) }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
