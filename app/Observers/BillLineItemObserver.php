<?php

namespace App\Observers;

use App\Models\BillLineItem;
use App\Models\Item;
use App\Models\ItemLedger;

class BillLineItemObserver
{
    public function created(BillLineItem $billLineItem): void
    {
        if ($billLineItem->line_type !== 'item' || ! filled($billLineItem->item_id)) {
            return;
        }

        $item = Item::query()->find($billLineItem->item_id);
        if (! $item) {
            return;
        }

        $movementQty = (float) ($billLineItem->qty ?? 0);
        if ($movementQty <= 0) {
            return;
        }

        $ledgerType = $billLineItem->bill_type === 'purchase' ? 'in' : 'out';
        $ledgerRate = $billLineItem->bill_type === 'purchase'
            ? (float) $billLineItem->rate
            : (float) $item->cost_price;

        ItemLedger::query()->create([
            'item_id' => $billLineItem->item_id,
            'type' => $ledgerType,
            'qty' => $movementQty,
            'rate' => $ledgerRate,
            'identifier' => $billLineItem->bill_type,
            'foreign_key' => $billLineItem->id,
        ]);

        if ($billLineItem->bill_type === 'purchase' && (float) $item->cost_price !== (float) $billLineItem->rate) {
            // Keep latest known purchase rate as current item cost price.
            $item->forceFill(['cost_price' => (float) $billLineItem->rate])->save();
        }

        // items.qty is a fast cache; item_ledgers remain the stock movement source of truth.
        $item->increment('qty', $movementQty * ($ledgerType === 'in' ? 1 : -1));
    }
}
