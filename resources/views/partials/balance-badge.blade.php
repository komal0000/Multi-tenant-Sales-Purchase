@props(['balance'])

<div class="flex items-center gap-2">
    <span class="font-mono text-lg font-semibold {{ $balance > 0 ? 'text-green-600' : ($balance < 0 ? 'text-red-500' : 'text-gray-500') }}">
        {{ number_format(abs($balance), 2) }}
    </span>
    @if ($balance > 0)
        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Receivable</span>
    @elseif ($balance < 0)
        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">Payable</span>
    @else
        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">Clear</span>
    @endif
</div>

