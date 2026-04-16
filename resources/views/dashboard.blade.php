@extends('layouts.app')

@section('content')
    <div class="space-y-8">
        <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total Receivable</p>
                <p class="mt-2 font-mono text-2xl font-semibold text-green-600 sm:text-3xl">{{ number_format($totalReceivable, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total Payable</p>
                <p class="mt-2 font-mono text-2xl font-semibold text-red-500 sm:text-3xl">{{ number_format($totalPayable, 2) }}</p>
            </div>
            @foreach ($accounts as $account)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ $account->name }} Balance</p>
                    <p class="mt-2 font-mono text-2xl font-semibold sm:text-3xl {{ $account->balance >= 0 ? 'text-green-600' : 'text-red-500' }}">
                        {{ number_format(abs($account->balance), 2) }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Access</h2>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Home Shortcuts</span>
                </div>
                @php
                    $quickLinks = [
                        ['label' => 'Parties', 'route' => 'parties.index', 'icon' => 'parties', 'hint' => 'Customers and suppliers'],
                        ['label' => 'Accounts', 'route' => 'accounts.index', 'icon' => 'accounts', 'hint' => 'Cash and bank ledgers'],
                        ['label' => 'Sales', 'route' => 'sales.index', 'icon' => 'sales', 'hint' => 'Create and track bills'],
                        ['label' => 'Purchases', 'route' => 'purchases.index', 'icon' => 'purchases', 'hint' => 'Vendor purchase entries'],
                    ];
                @endphp
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($quickLinks as $link)
                        <a href="{{ route($link['route']) }}" class="group flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 transition hover:border-indigo-200 hover:bg-indigo-50/40">
                            <span class="quick-shortcut-icon inline-flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition group-hover:bg-indigo-100 group-hover:text-indigo-700">
                                @include('partials.nav-icon', ['name' => $link['icon'], 'class' => 'h-4 w-4'])
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $link['label'] }}</span>
                                <span class="block text-xs text-gray-500">{{ $link['hint'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Settings</h2>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Configuration</span>
                </div>
                @php
                    $settingLinks = [
                        ['label' => 'Items', 'route' => 'items.index', 'icon' => 'items', 'hint' => 'Manage stock and rates'],
                        ['label' => 'Expense Categories', 'route' => 'expense-categories.index', 'icon' => 'expense-categories', 'hint' => 'Maintain expense hierarchy'],
                    ];
                @endphp
                @if (auth()->user()?->isAdmin())
                    @php
                        $settingLinks[] = ['label' => 'System Settings', 'route' => 'settings.index', 'icon' => 'settings', 'hint' => 'Payroll and users'];
                    @endphp
                @endif
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($settingLinks as $link)
                        <a href="{{ route($link['route']) }}" class="group flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 transition hover:border-indigo-200 hover:bg-indigo-50/40">
                            <span class="quick-shortcut-icon inline-flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition group-hover:bg-indigo-100 group-hover:text-indigo-700">
                                @include('partials.nav-icon', ['name' => $link['icon'], 'class' => 'h-4 w-4'])
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $link['label'] }}</span>
                                <span class="block text-xs text-gray-500">{{ $link['hint'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Sales</h2>
                    <a href="{{ route('sales.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700">View all</a>
                </div>
                <div class="space-y-3">
                    @forelse ($recentSales as $sale)
                        <a href="{{ route('sales.show', $sale) }}" class="block rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="font-medium">{{ $sale->party->name }}</span>
                                <span class="font-mono text-indigo-700">{{ number_format($sale->total, 2) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ $sale->created_at->format('d M Y, h:i A') }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">No sales yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Purchases</h2>
                    <a href="{{ route('purchases.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700">View all</a>
                </div>
                <div class="space-y-3">
                    @forelse ($recentPurchases as $purchase)
                        <a href="{{ route('purchases.show', $purchase) }}" class="block rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="font-medium">{{ $purchase->party->name }}</span>
                                <span class="font-mono text-indigo-700">{{ number_format($purchase->total, 2) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ $purchase->created_at->format('d M Y, h:i A') }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">No purchases yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Payments</h2>
                    <a href="{{ route('payments.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700">View all</a>
                </div>
                <div class="space-y-3">
                    @forelse ($recentPayments as $payment)
                        <a href="{{ route('payments.show', $payment) }}" class="block rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="font-medium">{{ $payment->party->name }}</span>
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $payment->type === 'received' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ ucfirst($payment->type) }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-sm text-gray-500">
                                <span>{{ $payment->account->name }}</span>
                                <span class="font-mono text-gray-700">{{ number_format($payment->amount, 2) }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">No payments yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
