@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Salary Slip</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $salary->party?->name ?? $salary->employee_name }}{{ ($salary->party?->phone ?? $salary->employee_code) ? ' • ' . ($salary->party?->phone ?? $salary->employee_code) : '' }}</p>
                    <p class="text-sm text-gray-400">Month: {{ $salary->salary_month }} • BS {{ $salary->salary_date_bs }} / AD {{ $salary->salary_date->format('d M Y') }}</p>
                    <p class="text-sm text-gray-400">Party: {{ $salary->party?->name ?? '-' }}</p>
                </div>
                <a href="{{ route('employee-salaries.print', $salary) }}" target="_blank" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Print Salary</a>
            </div>

            <div class="mt-6 overflow-hidden rounded-lg border border-gray-200">
                <table class="w-full text-sm">
                    <tbody>
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-3 text-gray-600">Basic Salary</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $salary->basic_salary, 2) }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-3 text-gray-600">Allowance</td>
                            <td class="px-4 py-3 text-right font-mono text-green-700">{{ number_format((float) $salary->allowance, 2) }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-3 text-gray-600">Deduction</td>
                            <td class="px-4 py-3 text-right font-mono text-red-600">{{ number_format((float) $salary->deduction, 2) }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-3 text-gray-600">Leave Days</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $salary->leave_days, 2) }}</td>
                        </tr>
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-3 text-gray-600">Overtime Days</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $salary->overtime_days, 2) }}</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-800">Net Salary</td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-semibold text-indigo-700">{{ number_format((float) $salary->net_salary, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                <p class="font-semibold">Expense Status</p>
                @if ($salary->expense_payment_id)
                    <p class="mt-1">Posted to account: {{ $salary->account?->name ?? '-' }}</p>
                    <p class="text-xs text-gray-500">Payment ID: {{ $salary->expense_payment_id }}{{ $salary->expense_saved_at ? ' • ' . $salary->expense_saved_at->format('d M Y h:i A') : '' }}</p>
                @else
                    <p class="mt-1 text-yellow-700">Expense entry has not been posted for this salary.</p>
                @endif
            </div>

            @if ($salary->remarks)
                <div class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                    <p class="font-semibold">Remarks</p>
                    <p class="mt-1">{{ $salary->remarks }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
