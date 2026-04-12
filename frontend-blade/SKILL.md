# SKILL: Frontend UI — Blade + Tailwind (Ledger System)

## Purpose
This skill guides building the Blade UI for the Sales, Purchase & Ledger system.
Aesthetic direction: **clean, utilitarian accounting tool** — not flashy, but sharp and trustworthy.

---

## Design Principles

- **Tone**: Professional ledger/accounting app. Think Tally, but clean.
- **Colors**: Neutral base (gray/slate), red for debit/payable, green for credit/receivable
- **Typography**: Monospace for numbers, clean sans-serif for labels
- **Density**: Compact tables, clear actions, no wasted space
- **Trust signals**: Show totals clearly, confirm destructive actions

---

## Color Conventions (Tailwind classes)

| Meaning | Class |
|---|---|
| Positive balance (receivable) | `text-green-600` |
| Negative balance (payable) | `text-red-500` |
| DR amount | `text-blue-600` |
| CR amount | `text-orange-500` |
| Soft delete / inactive | `opacity-50 line-through` |
| Action buttons | `bg-indigo-600 hover:bg-indigo-700` |

---

## Layout Skeleton

```blade
{{-- resources/views/layouts/app.blade.php --}}
<html>
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<nav class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-6 text-sm font-medium">
    <a href="{{ route('dashboard') }}" class="font-bold text-lg text-indigo-600">📒 LedgerApp</a>
    <a href="{{ route('parties.index') }}">Parties</a>
    <a href="{{ route('sales.index') }}">Sales</a>
    <a href="{{ route('purchases.index') }}">Purchases</a>
    <a href="{{ route('payments.index') }}">Payments</a>
    <a href="{{ route('accounts.index') }}">Accounts</a>
</nav>

<main class="max-w-6xl mx-auto px-6 py-8">
    @yield('content')
</main>

</body>
</html>
```

---

## Bill Table Pattern

```blade
<table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
    <thead class="bg-gray-100 text-gray-500 uppercase text-xs tracking-wide">
        <tr>
            <th class="px-4 py-2 text-left">Particular</th>
            <th class="px-4 py-2 text-right">Qty</th>
            <th class="px-4 py-2 text-right">Price</th>
            <th class="px-4 py-2 text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $item)
        <tr class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-2">{{ $item->particular }}</td>
            <td class="px-4 py-2 text-right font-mono">{{ $item->qty }}</td>
            <td class="px-4 py-2 text-right font-mono">{{ number_format($item->price, 2) }}</td>
            <td class="px-4 py-2 text-right font-mono font-semibold">{{ number_format($item->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50 font-bold">
        <tr>
            <td colspan="3" class="px-4 py-2 text-right">Total</td>
            <td class="px-4 py-2 text-right font-mono text-indigo-700">{{ number_format($sale->total, 2) }}</td>
        </tr>
    </tfoot>
</table>
```

---

## Balance Display Pattern

```blade
@php $balance = $ledger->partyBalance($party->id); @endphp

<div class="flex items-center gap-2">
    <span class="text-sm text-gray-500">Balance:</span>
    <span class="font-mono font-bold text-lg 
        {{ $balance > 0 ? 'text-green-600' : ($balance < 0 ? 'text-red-500' : 'text-gray-400') }}">
        {{ number_format(abs($balance), 2) }}
    </span>
    @if($balance > 0)
        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Receivable</span>
    @elseif($balance < 0)
        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Payable</span>
    @else
        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Clear</span>
    @endif
</div>
```

---

## Dynamic Item Entry (Alpine.js)

Use Alpine.js for adding/removing items without full page reload:

```blade
<div x-data="itemForm()">
    <template x-for="(item, index) in items" :key="index">
        <div class="flex gap-2 mb-2">
            <input x-model="item.particular" placeholder="Particular" class="input flex-1" />
            <input x-model.number="item.qty" type="number" placeholder="Qty" class="input w-20" @input="calcTotal(index)" />
            <input x-model.number="item.price" type="number" placeholder="Price" class="input w-28" @input="calcTotal(index)" />
            <span x-text="item.total.toFixed(2)" class="font-mono w-24 text-right pt-2"></span>
            <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-600">✕</button>
        </div>
    </template>
    <button type="button" @click="addItem()" class="text-sm text-indigo-600 hover:underline">+ Add Item</button>
</div>

<script>
function itemForm() {
    return {
        items: [{ particular: '', qty: 1, price: 0, total: 0 }],
        addItem() { this.items.push({ particular: '', qty: 1, price: 0, total: 0 }); },
        removeItem(i) { this.items.splice(i, 1); },
        calcTotal(i) { this.items[i].total = this.items[i].qty * this.items[i].price; },
    }
}
</script>
```

---

## Ledger Statement View

```blade
<table class="w-full text-sm font-mono border border-gray-200 rounded">
    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-500">
        <tr>
            <th class="px-3 py-2 text-left">Date</th>
            <th class="px-3 py-2 text-left">Type</th>
            <th class="px-3 py-2 text-right text-blue-600">DR</th>
            <th class="px-3 py-2 text-right text-orange-500">CR</th>
            <th class="px-3 py-2 text-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        @php $running = 0; @endphp
        @foreach($ledgerRows as $row)
            @php $running += ($row->dr_amount - $row->cr_amount); @endphp
            <tr class="border-t border-gray-100">
                <td class="px-3 py-1.5 text-gray-500">{{ $row->created_at->format('d M Y') }}</td>
                <td class="px-3 py-1.5 capitalize">{{ $row->type }}</td>
                <td class="px-3 py-1.5 text-right text-blue-600">
                    {{ $row->dr_amount > 0 ? number_format($row->dr_amount, 2) : '—' }}
                </td>
                <td class="px-3 py-1.5 text-right text-orange-500">
                    {{ $row->cr_amount > 0 ? number_format($row->cr_amount, 2) : '—' }}
                </td>
                <td class="px-3 py-1.5 text-right font-semibold {{ $running >= 0 ? 'text-green-600' : 'text-red-500' }}">
                    {{ number_format(abs($running), 2) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
```

---

## Confirm Delete Pattern

```blade
<form action="{{ route('payments.destroy', $payment) }}" method="POST"
      onsubmit="return confirm('Delete this payment? This will reverse the ledger entries.')">
    @csrf @method('DELETE')
    <button class="text-red-500 hover:text-red-700 text-sm">Delete</button>
</form>
```
