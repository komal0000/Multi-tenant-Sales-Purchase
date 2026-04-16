@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Cashbook</h1>
            <p class="text-sm text-gray-500">All cash account movements with opening and running balance.</p>
        </div>

        <form method="GET" action="{{ route('reports.cashbook') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label for="account_id" class="text-sm font-medium text-gray-700">Cash Account</label>
                    <select id="account_id" name="account_id" class="select2 mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">All cash accounts</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected(($filters['account_id'] ?? '') === $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    @include('partials.bs-date-selector', ['name' => 'from_date_bs', 'label' => 'From BS Date', 'value' => $filters['from_date_bs'] ?? null])
                </div>
                <div>
                    @include('partials.bs-date-selector', ['name' => 'to_date_bs', 'label' => 'To BS Date', 'value' => $filters['to_date_bs'] ?? null])
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
                    <a href="{{ route('reports.cashbook') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">Opening Balance</p>
                <p class="mt-2 font-mono text-xl font-semibold {{ $openingBalance >= 0 ? 'text-green-600' : 'text-red-500' }}">
                    {{ number_format(abs($openingBalance), 2) }} {{ $openingBalance >= 0 ? 'Receivable' : 'Payable' }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">Period Dr</p>
                <p class="mt-2 font-mono text-xl font-semibold text-blue-600">{{ number_format($periodDebit, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">Period Cr</p>
                <p class="mt-2 font-mono text-xl font-semibold text-orange-500">{{ number_format($periodCredit, 2) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[760px] w-full text-sm font-mono">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-right text-blue-600">Dr</th>
                            <th class="px-4 py-3 text-right text-orange-500">Cr</th>
                            <th class="px-4 py-3 text-right">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $running = (float) $openingBalance; @endphp
                        @forelse ($ledgerRows as $row)
                            @php $running += ((float) $row->dr_amount - (float) $row->cr_amount); @endphp
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-3 text-gray-500">{{ \App\Helpers\DateHelper::fromDateInt((int) $row->date) }}</td>
                                <td class="px-4 py-3 capitalize">{{ $row->type }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $row->reference_text ?? ($row->ref_table . ' / ' . $row->ref_id) }}</td>
                                <td class="px-4 py-3 text-right text-blue-600">{{ $row->dr_amount > 0 ? number_format($row->dr_amount, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right text-orange-500">{{ $row->cr_amount > 0 ? number_format($row->cr_amount, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $running >= 0 ? 'text-green-600' : 'text-red-500' }}">{{ number_format(abs($running), 2) }} {{ $running >= 0 ? 'Receivable' : 'Payable' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center font-sans text-gray-500">
                                    {{ $hasSearched ? 'No cashbook rows found for this range.' : 'Apply search filters to load cashbook data.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
