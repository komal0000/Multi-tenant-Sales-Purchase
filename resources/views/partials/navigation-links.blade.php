@php
    $items = $items ?? [];
    $variant = $variant ?? 'horizontal';
    $sectionLabels = [
        'main' => 'Main',
        'reports' => 'Reports',
        'settings' => 'Settings',
    ];
@endphp

@if ($variant === 'vertical')
    <nav class="space-y-4 px-4 py-6 text-sm font-semibold" aria-label="Primary navigation">
        @foreach (collect($items)->groupBy(fn (array $item) => $item['section'] ?? 'main') as $section => $sectionItems)
            <section>
                <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-400">
                    {{ $sectionLabels[$section] ?? ucfirst($section) }}
                </p>
                <div class="mt-2 space-y-1">
                    @foreach ($sectionItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center gap-3 rounded-xl px-4 py-3 {{ request()->routeIs($item['active']) ? 'bg-indigo-50 text-indigo-700 is-active' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600' }}"
                        >
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ request()->routeIs($item['active']) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
                                @include('partials.nav-icon', ['name' => $item['icon'] ?? 'dashboard', 'class' => 'h-4 w-4'])
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </nav>
@else
    <nav class="ledger-nav" aria-label="Primary navigation">
        @foreach ($items as $item)
            <a
                href="{{ route($item['route']) }}"
                class="ledger-nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
            >
                {{ $item['label'] }}
            </a>
            @if (! $loop->last)
                <span class="ledger-nav-separator" aria-hidden="true"></span>
            @endif
        @endforeach
    </nav>
@endif
