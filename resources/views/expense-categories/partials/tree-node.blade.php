@php
    $hasChildren = !empty($node['children'] ?? []);
@endphp

<li class="space-y-1" x-data="{ open: true }">
    <div class="flex items-center gap-2" style="padding-left: {{ $level * 18 }}px;">
        @if ($hasChildren)
            <button type="button" @click="open = !open" class="inline-flex h-6 w-6 items-center justify-center rounded border border-gray-300 text-xs text-gray-600 hover:bg-gray-50">
                <span x-text="open ? '▾' : '▸'"></span>
            </button>
        @else
            <span class="inline-flex h-6 w-6 items-center justify-center text-xs text-gray-400">•</span>
        @endif

        <button
            type="button"
            @if ($hasChildren)
                @click="open = !open"
            @endif
            class="text-left text-sm {{ $level === 0 ? 'font-semibold text-gray-900' : 'text-gray-700' }}"
        >
            {{ $node['name'] }}
        </button>

        @if (!empty($node['is_orphan']))
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">Orphan</span>
        @endif

        @if (!empty($node['is_circular']))
            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700">Circular Blocked</span>
        @endif
    </div>

    @if ($hasChildren)
        <ul x-show="open" x-transition class="space-y-1">
            @foreach ($node['children'] as $child)
                @include('expense-categories.partials.tree-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
