<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Party;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $partyExists = Party::query()->whereKey($data['party_id'])->exists();
            if (! $partyExists) {
                throw new InvalidArgumentException('The selected party is invalid for this tenant.');
            }

            $purchase = Purchase::query()->create([
                'party_id' => $data['party_id'],
                'total' => 0,
            ]);

            foreach ($data['items'] as $item) {
                $lineType = $item['line_type'] ?? null;
                if (! in_array($lineType, ['item', 'general', 'expense'], true)) {
                    throw new InvalidArgumentException('Purchase item line type must be item, general, or expense.');
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

                if ($lineType === 'expense' && ! filled($item['expense_category_id'] ?? null)) {
                    throw new InvalidArgumentException('Expense lines require expense_category_id.');
                }

                if ($lineType === 'expense' && ! ExpenseCategory::query()->whereKey($item['expense_category_id'])->exists()) {
                    throw new InvalidArgumentException('Selected expense category is invalid for this tenant.');
                }

                if (! filled($item['qty'] ?? null) || (float) $item['qty'] <= 0) {
                    throw new InvalidArgumentException('Quantity must be greater than zero.');
                }

                if (! array_key_exists('rate', $item) || (float) $item['rate'] < 0) {
                    throw new InvalidArgumentException('Rate must be provided and cannot be negative.');
                }

                $purchase->items()->create([
                    'bill_type' => 'purchase',
                    'line_type' => $lineType,
                    'item_id' => $item['item_id'] ?? null,
                    'description' => $item['description'] ?? null,
                    'expense_category_id' => $item['expense_category_id'] ?? null,
                    'qty' => $item['qty'] ?? null,
                    'rate' => $item['rate'],
                ]);
            }

            $purchase->update([
                'total' => $purchase->items()->sum('total'),
            ]);

            $this->ledger->recordPurchase($purchase->fresh());

            foreach ($data['payments'] ?? [] as $paymentData) {
                $accountExists = Account::query()->whereKey($paymentData['account_id'] ?? null)->exists();
                if (! $accountExists) {
                    throw new InvalidArgumentException('Selected payment account is invalid for this tenant.');
                }

                $payment = $purchase->payments()->create([
                    'party_id' => $purchase->party_id,
                    'amount' => $paymentData['amount'],
                    'type' => 'given',
                    'account_id' => $paymentData['account_id'],
                    'cheque_number' => $paymentData['cheque_number'] ?? null,
                    'sale_id' => null,
                ]);

                $this->ledger->recordPayment($payment);
            }

            return $purchase->load(['party', 'items.item', 'items.expenseCategory', 'payments.account']);
        });
    }

    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $purchase->loadMissing(['payments', 'items']);

            foreach ($purchase->payments as $payment) {
                $this->ledger->reversePayment($payment);
                $payment->delete();
            }

            foreach ($purchase->items->where('line_type', 'item') as $item) {
                ItemLedger::query()->create([
                    'item_id' => $item->item_id,
                    'type' => 'out',
                    'qty' => $item->qty,
                    'rate' => $item->rate,
                    'identifier' => 'purchase_reversal',
                    'foreign_key' => $item->id,
                ]);

                $item->item()->decrement('qty', (float) $item->qty);
            }

            $this->ledger->reversePurchase($purchase);
            $purchase->delete();
        });
    }
}
