<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Party;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $partyExists = Party::query()->whereKey($data['party_id'])->exists();
            if (! $partyExists) {
                throw new InvalidArgumentException('The selected party is invalid for this tenant.');
            }

            $sale = Sale::query()->create([
                'party_id' => $data['party_id'],
                'total' => 0,
            ]);

            foreach ($data['items'] as $item) {
                $lineType = $item['line_type'] ?? null;
                if (! in_array($lineType, ['item', 'general'], true)) {
                    throw new InvalidArgumentException('Sale item line type must be item or general.');
                }

                if ($lineType === 'item' && ! filled($item['item_id'] ?? null)) {
                    throw new InvalidArgumentException('Item lines require item_id.');
                }

                if ($lineType === 'item' && ! Item::query()->whereKey($item['item_id'])->exists()) {
                    throw new InvalidArgumentException('Selected item is invalid for this tenant.');
                }

                if ($lineType === 'general' && ! filled($item['description'] ?? null)) {
                    throw new InvalidArgumentException('General lines require description.');
                }

                if (! filled($item['qty'] ?? null) || (float) $item['qty'] <= 0) {
                    throw new InvalidArgumentException('Quantity must be greater than zero.');
                }

                if (! array_key_exists('rate', $item) || (float) $item['rate'] < 0) {
                    throw new InvalidArgumentException('Rate must be provided and cannot be negative.');
                }

                $sale->items()->create([
                    'bill_type' => 'sale',
                    'line_type' => $lineType,
                    'item_id' => $item['item_id'] ?? null,
                    'description' => $item['description'] ?? null,
                    'expense_category_id' => null,
                    'qty' => $item['qty'] ?? null,
                    'rate' => $item['rate'],
                ]);
            }

            $sale->update([
                'total' => $sale->items()->sum('total'),
            ]);

            $this->ledger->recordSale($sale->fresh());

            foreach ($data['payments'] ?? [] as $paymentData) {
                $accountExists = Account::query()->whereKey($paymentData['account_id'] ?? null)->exists();
                if (! $accountExists) {
                    throw new InvalidArgumentException('Selected payment account is invalid for this tenant.');
                }

                $payment = $sale->payments()->create([
                    'party_id' => $sale->party_id,
                    'amount' => $paymentData['amount'],
                    'type' => 'received',
                    'account_id' => $paymentData['account_id'],
                    'cheque_number' => $paymentData['cheque_number'] ?? null,
                    'purchase_id' => null,
                ]);

                $this->ledger->recordPayment($payment);
            }

            return $sale->load(['party', 'items.item', 'items.expenseCategory', 'payments.account']);
        });
    }

    public function delete(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale->loadMissing(['payments', 'items']);

            foreach ($sale->payments as $payment) {
                $this->ledger->reversePayment($payment);
                $payment->delete();
            }

            foreach ($sale->items->where('line_type', 'item') as $item) {
                ItemLedger::query()->create([
                    'item_id' => $item->item_id,
                    'type' => 'in',
                    'qty' => $item->qty,
                    'rate' => $item->rate,
                    'identifier' => 'sale_reversal',
                    'foreign_key' => $item->id,
                ]);

                $item->item()->increment('qty', (float) $item->qty);
            }

            $this->ledger->reverseSale($sale);
            $sale->delete();
        });
    }
}
