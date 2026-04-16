@props([
    'title',
    'rows' => [],
    'labelHeading' => 'Label',
    'emptyText' => 'No rows available.',
])

<section class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-200 px-4 py-3">
        <h3 class="text-sm font-semibold text-gray-800">{{ $title }}</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">{{ $labelHeading }}</th>
                    <th class="px-4 py-2 text-right">Qty</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-2 text-gray-700">{{ $row['label'] }}</td>
                        <td class="px-4 py-2 text-right font-mono text-gray-600">{{ number_format((float) ($row['qty'] ?? 0), 4) }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold text-indigo-700">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">{{ $emptyText }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
