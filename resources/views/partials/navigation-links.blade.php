@php
    $items = $items ?? [];
    $variant = $variant ?? 'horizontal';
@endphp

@if ($variant === 'vertical')
    <nav class="space-y-1 px-4 py-6 text-sm font-semibold" aria-label="Primary navigation">
        @foreach ($items as $item)
            <a
                href="{{ route($item['route']) }}"
                class="block rounded-xl px-4 py-3 {{ request()->routeIs($item['active']) ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600' }}"
            >
                {{ $item['label'] }}
            </a>
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
