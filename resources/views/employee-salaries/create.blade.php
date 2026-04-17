@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Salary Sheet</h1>
                    <p class="mt-1 text-sm text-gray-500">Calculate monthly salary from employee base salary, leave days, and overtime days.</p>
                </div>
                <a href="{{ route('employees.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Manage Employees</a>
            </div>

            <form method="GET" action="{{ route('employee-salaries.create') }}" class="mt-5 grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                <div>
                    <label for="salary_month_bs" class="block text-sm font-medium text-gray-700">Salary Month (BS)</label>
                    <input id="salary_month_bs" name="salary_month_bs" type="text" value="{{ old('salary_month_bs', $salaryMonthBs) }}" placeholder="YYYY-MM" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                </div>
                <div class="flex items-center gap-3 md:pb-0.5">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Load Sheet</button>
                    <a href="{{ route('employee-salaries.create') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Current Month</a>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('employee-salaries.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="salary_month_bs" value="{{ old('salary_month_bs', $salaryMonthBs) }}">

            <div class="rounded-xl border border-gray-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                Leave fine per day: <span class="font-semibold">{{ number_format((float) $payrollSetting->leave_fine_per_day, 2) }}</span>
                •
                Overtime money per day: <span class="font-semibold">{{ number_format((float) $payrollSetting->overtime_money_per_day, 2) }}</span>
                <span class="ml-2 text-indigo-600">(Change these from Dashboard as admin)</span>
            </div>

            <div class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-3">
                <div>
                    @include('partials.bs-date-selector', ['name' => 'salary_date_bs', 'label' => 'Salary Date (BS)', 'value' => old('salary_date_bs', $salaryDateBs)])
                </div>
                <div>
                    <label for="account_id" class="block text-sm font-medium text-gray-700">Expense Account</label>
                    <select id="account_id" name="account_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                        <option value="">Select account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id', $selectedAccountId) === (string) $account->id)>
                                {{ ucfirst($account->type) }} - {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="remarks" class="block text-sm font-medium text-gray-700">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Optional note for this month">{{ old('remarks') }}</textarea>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-[1200px] w-full text-sm">
                        <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Employee</th>
                                <th class="px-4 py-3 text-right">Base Salary</th>
                                <th class="px-4 py-3 text-right">Leave Fine / Day</th>
                                <th class="px-4 py-3 text-right">Overtime Money / Day</th>
                                <th class="px-4 py-3 text-right">Leave Days</th>
                                <th class="px-4 py-3 text-right">Overtime Days</th>
                                <th class="px-4 py-3 text-right">Leave Deduction</th>
                                <th class="px-4 py-3 text-right">Overtime Amount</th>
                                <th class="px-4 py-3 text-right">Net Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sheetRows as $row)
                                @php
                                    $employee = $row['employee'];
                                    $leaveValue = old("leaves.{$employee->id}", $row['leave_days']);
                                    $overtimeValue = old("overtimes.{$employee->id}", $row['overtime_days']);
                                @endphp
                                <tr
                                    class="border-t border-gray-100 hover:bg-gray-50/60"
                                    data-salary-row
                                    data-leave-rate="{{ number_format((float) $row['leave_fine_per_day'], 4, '.', '') }}"
                                    data-overtime-rate="{{ number_format((float) $row['overtime_money_per_day'], 4, '.', '') }}"
                                    data-base-salary="{{ number_format((float) $row['basic_salary'], 2, '.', '') }}"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900">{{ $employee->party?->name ?? '-' }}</p>
                                        <p class="text-xs text-gray-500">{{ $employee->party?->phone ?? '-' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $row['basic_salary'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $row['leave_fine_per_day'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $row['overtime_money_per_day'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.5"
                                            name="leaves[{{ $employee->id }}]"
                                            value="{{ $leaveValue }}"
                                            class="salary-leave-input w-24 rounded-lg border border-gray-300 px-2 py-1 text-right text-sm"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.5"
                                            name="overtimes[{{ $employee->id }}]"
                                            value="{{ $overtimeValue }}"
                                            class="salary-overtime-input w-24 rounded-lg border border-gray-300 px-2 py-1 text-right text-sm"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-red-600 salary-deduction">{{ number_format((float) $row['deduction'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-green-700 salary-allowance">{{ number_format((float) $row['allowance'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-indigo-700 salary-net">{{ number_format((float) $row['net_salary'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                        No employees found. Create employees first to generate a salary sheet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="save_as_expense" value="0">
                    <input type="checkbox" name="save_as_expense" value="1" @checked(old('save_as_expense', '1') === '1') class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Save as expense (creates payment + ledger entry)
                </label>

                <div class="flex items-center gap-3">
                    <a href="{{ route('employee-salaries.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Salary Sheet</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const rows = document.querySelectorAll('[data-salary-row]');

            const formatAmount = (value) => {
                const amount = Number.isFinite(value) ? value : 0;
                return amount.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            };

            rows.forEach((row) => {
                const leaveRate = parseFloat(row.dataset.leaveRate || '0');
                const overtimeRate = parseFloat(row.dataset.overtimeRate || '0');
                const baseSalary = parseFloat(row.dataset.baseSalary || '0');
                const leaveInput = row.querySelector('.salary-leave-input');
                const overtimeInput = row.querySelector('.salary-overtime-input');
                const deductionCell = row.querySelector('.salary-deduction');
                const allowanceCell = row.querySelector('.salary-allowance');
                const netCell = row.querySelector('.salary-net');

                const recalc = () => {
                    const leaveDays = parseFloat(leaveInput?.value || '0') || 0;
                    const overtimeDays = parseFloat(overtimeInput?.value || '0') || 0;

                    const deduction = leaveRate * leaveDays;
                    const allowance = overtimeRate * overtimeDays;
                    const net = baseSalary + allowance - deduction;

                    if (deductionCell) {
                        deductionCell.textContent = formatAmount(deduction);
                    }
                    if (allowanceCell) {
                        allowanceCell.textContent = formatAmount(allowance);
                    }
                    if (netCell) {
                        netCell.textContent = formatAmount(net);
                    }
                };

                leaveInput?.addEventListener('input', recalc);
                overtimeInput?.addEventListener('input', recalc);
                recalc();
            });
        })();
    </script>
@endsection
