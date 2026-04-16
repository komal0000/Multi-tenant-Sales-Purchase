<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sales, Purchase & Ledger System' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.bsDateConfig = @json($bsDateConfig ?? ['years' => [], 'months' => [], 'monthMap' => []]);
    </script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="{{ asset('css/layout.css') }}" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/layout.js') }}" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-800" x-data="{ sidebarOpen: false }">
    @php
        $navigationItems = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'section' => 'main'],
            ['label' => 'Parties', 'route' => 'parties.index', 'active' => 'parties.*', 'icon' => 'parties', 'section' => 'main'],
            ['label' => 'Accounts', 'route' => 'accounts.index', 'active' => 'accounts.*', 'icon' => 'accounts', 'section' => 'main'],
            ['label' => 'Employees', 'route' => 'employees.index', 'active' => 'employees.*', 'icon' => 'employees', 'section' => 'main'],
            ['label' => 'Sales', 'route' => 'sales.index', 'active' => 'sales.*', 'icon' => 'sales', 'section' => 'main'],
            ['label' => 'Purchases', 'route' => 'purchases.index', 'active' => 'purchases.*', 'icon' => 'purchases', 'section' => 'main'],
            ['label' => 'Payments', 'route' => 'payments.index', 'active' => 'payments.*', 'icon' => 'payments', 'section' => 'main'],
            ['label' => 'Employee Salary', 'route' => 'employee-salaries.index', 'active' => 'employee-salaries.*', 'icon' => 'employee-salaries', 'section' => 'main'],
            ['label' => 'Sales Report', 'route' => 'reports.sales', 'active' => 'reports.sales', 'icon' => 'sales', 'section' => 'reports'],
            ['label' => 'Purchase Report', 'route' => 'reports.purchases', 'active' => 'reports.purchases', 'icon' => 'purchases', 'section' => 'reports'],
            ['label' => 'Cashbook', 'route' => 'reports.cashbook', 'active' => 'reports.cashbook', 'icon' => 'cashbook', 'section' => 'reports'],
            ['label' => 'Profit / Loss', 'route' => 'reports.profit-loss', 'active' => 'reports.profit-loss', 'icon' => 'profit-loss', 'section' => 'reports'],
            ['label' => 'Items', 'route' => 'items.index', 'active' => 'items.*', 'icon' => 'items', 'section' => 'settings'],
            ['label' => 'Expense Categories', 'route' => 'expense-categories.index', 'active' => 'expense-categories.*', 'icon' => 'expense-categories', 'section' => 'settings'],
        ];

        if (auth()->check() && auth()->user()->isAdmin()) {
            $navigationItems[] = ['label' => 'Settings', 'route' => 'settings.index', 'active' => 'settings.*', 'icon' => 'settings', 'section' => 'settings'];
        }

        $routeName = request()->route()?->getName();
        $routeSegments = $routeName ? explode('.', $routeName) : [];

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
        ];

        if (! empty($routeSegments) && $routeSegments[0] !== 'dashboard') {
            $currentNav = collect($navigationItems)
                ->first(fn (array $item) => request()->routeIs($item['active']));

            $resourceLabel = $currentNav['label'] ?? ucfirst(str_replace('_', ' ', $routeSegments[0]));
            $resourceRoute = $currentNav['route'] ?? 'dashboard';

            $breadcrumbs[] = ['label' => $resourceLabel, 'url' => route($resourceRoute)];

            $action = $routeSegments[1] ?? null;
            if ($action === 'create') {
                $breadcrumbs[] = ['label' => "New {$resourceLabel}", 'url' => null];
            } elseif ($action === 'edit') {
                $breadcrumbs[] = ['label' => "Edit {$resourceLabel}", 'url' => null];
            } elseif ($action === 'show') {
                $breadcrumbs[] = ['label' => "{$resourceLabel} Detail", 'url' => null];
            }
        }
    @endphp

    <div class="flex min-h-screen">
        <aside class="hidden w-72 shrink-0 border-r border-gray-200 bg-white lg:flex lg:flex-col">
            <div class="border-b border-gray-200 px-6 py-6">
                <a href="{{ route('dashboard') }}" class="text-2xl font-semibold text-indigo-600">LedgerApp</a>
                <p class="mt-2 text-sm text-gray-500">Sales, purchase, and ledger control in one place.</p>
            </div>
            @include('partials.navigation-links', ['items' => $navigationItems, 'variant' => 'vertical'])
            @auth
                <div class="border-t border-gray-200 px-6 py-4">
                    <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ auth()->user()->email }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-red-300 hover:text-red-600">Logout</button>
                    </form>
                </div>
            @endauth
        </aside>

        <div class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden" x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"></div>
        <aside class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:hidden" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-5">
                <div>
                    <a href="{{ route('dashboard') }}" class="ledger-brand-pill">LedgerApp</a>
                    <p class="mt-2 text-sm text-gray-500">Sales, purchase, and ledger control.</p>
                </div>
                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600" @click="sidebarOpen = false">Close</button>
            </div>
            @include('partials.navigation-links', ['items' => $navigationItems, 'variant' => 'vertical'])
            @auth
                <div class="border-t border-gray-200 px-5 py-4">
                    <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ auth()->user()->email }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-red-300 hover:text-red-600">Logout</button>
                    </form>
                </div>
            @endauth
        </aside>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/90 backdrop-blur lg:hidden">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6">
                    <div>
                        <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-indigo-600">LedgerApp</a>
                        <p class="text-xs text-gray-500">Sales, Purchase & Ledger System</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @auth
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600">Logout</button>
                            </form>
                        @endauth
                        <button type="button" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600" @click="sidebarOpen = true">Menu</button>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 xl:px-8">
                <div class="mb-5 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600">
                    <nav class="flex flex-wrap items-center gap-2" aria-label="Breadcrumb">
                        @foreach ($breadcrumbs as $breadcrumb)
                            @if ($loop->index > 0)
                                <span class="text-gray-400">/</span>
                            @endif

                            @if ($breadcrumb['url'])
                                <a href="{{ $breadcrumb['url'] }}" class="font-medium text-indigo-600 hover:text-indigo-700">{{ $breadcrumb['label'] }}</a>
                            @else
                                <span class="font-semibold text-gray-800">{{ $breadcrumb['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                </div>

                @if (session('success'))
                    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold">Please fix the following issues:</p>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.quick-party-entry')
    @stack('scripts')
</body>
</html>


