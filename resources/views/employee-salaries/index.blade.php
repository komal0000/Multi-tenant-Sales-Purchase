@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 sm:text-3xl">Employee Salary</h1>
                <p class="text-sm text-gray-500">Search salary records to keep listing fast for large datasets.</p>
            </div>
            <a href="{{ route('employee-salaries.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">Salary Sheet</a>
        </div>

        <form method="GET" action="{{ route('employee-salaries.index') }}" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Employee</label>
                <select id="employee-salary-employee-filter" name="employee_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                                {{ $employee->party?->name ?? '-' }}
                            @if ($employee->party?->phone)
                                ({{ $employee->party->phone }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            @include('partials.bs-date-selector', ['name' => 'from_date_bs', 'label' => 'From BS Date', 'value' => $filters['from_date_bs'] ?? null])
            @include('partials.bs-date-selector', ['name' => 'to_date_bs', 'label' => 'To BS Date', 'value' => $filters['to_date_bs'] ?? null])
            <div>
                <label class="block text-sm font-medium text-gray-700">Keyword</label>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Employee, phone or month" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
            </div>
            <div class="flex items-center gap-3 md:pb-0.5">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
                <a href="{{ route('employee-salaries.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Employee</th>
                            <th class="px-4 py-3 text-left">Month</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-right">Leave Days</th>
                            <th class="px-4 py-3 text-right">Overtime Days</th>
                            <th class="px-4 py-3 text-right">Basic</th>
                            <th class="px-4 py-3 text-right">Allowance</th>
                            <th class="px-4 py-3 text-right">Deduction</th>
                            <th class="px-4 py-3 text-right">Net Salary</th>
                            <th class="px-4 py-3 text-left">Expense</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salaries as $salary)
                            <tr class="border-t border-gray-100 hover:bg-gray-50/70">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $salary->party?->name ?? $salary->employee_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $salary->party?->phone ?? ($salary->employee_code ?: '-') }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $salary->salary_month }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $salary->salary_date_bs }} ({{ $salary->salary_date->format('d M Y') }})</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $salary->leave_days, 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $salary->overtime_days, 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $salary->basic_salary, 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-green-700">{{ number_format((float) $salary->allowance, 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-red-600">{{ number_format((float) $salary->deduction, 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-indigo-700">{{ number_format((float) $salary->net_salary, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if ($salary->expense_payment_id)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Posted</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700">Pending</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-4">
                                        <a href="{{ route('employee-salaries.show', $salary) }}" class="text-sm text-indigo-600 hover:text-indigo-700">View</a>
                                        <a href="{{ route('employee-salaries.print', $salary) }}" target="_blank" class="text-sm text-gray-700 hover:text-gray-900">Print</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-10 text-center text-gray-500">
                                    {{ $hasSearched ? 'No salary records found.' : 'Use search filters to load salary records.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($hasSearched)
            {{ $salaries->links() }}
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
                return;
            }

            window.jQuery('#employee-salary-employee-filter').select2({
                width: '100%',
                placeholder: 'All employees',
                allowClear: true,
            });
        });
    </script>
@endpush
