# SKILL: Transactions — Sale, Purchase & Payment Services

## Purpose
This skill guides writing the **service layer** for creating, updating, and deleting Sales, Purchases, and Payments.
Always wrap in `DB::transaction()`. Always call `LedgerService` after every write.

---

## Rules Summary
- ✅ Use `DB::transaction()` for every operation
- ✅ Call `LedgerService` to record ledger entries
- ✅ Soft delete Sales/Purchases — never hard delete
- ✅ When deleting a payment, create **reverse ledger entries** (do NOT delete ledger rows)
- ❌ Never update a bill total manually — recalculate from items
- ❌ Payment can link to sale_id OR purchase_id, not both

---

## SaleService

```php
class SaleService
{
    public function __construct(private LedgerService $ledger) {}

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::create([
                'party_id' => $data['party_id'],
                'total'    => 0, // will be updated after items
            ]);

            $total = 0;
            foreach ($data['items'] as $item) {
                $itemTotal = $item['qty'] * $item['price'];
                $sale->items()->create([
                    'particular' => $item['particular'],
                    'qty'        => $item['qty'],
                    'price'      => $item['price'],
                    'total'      => $itemTotal,
                ]);
                $total += $itemTotal;
            }

            $sale->update(['total' => $total]);
            $this->ledger->recordSale($sale);

            return $sale;
        });
    }

    public function delete(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            // Soft delete only — ledger stays untouched
            $sale->delete();
        });
    }
}
```

---

## PurchaseService

Same structure as SaleService — swap `Sale` → `Purchase`, `recordSale` → `recordPurchase`.

```php
class PurchaseService
{
    public function __construct(private LedgerService $ledger) {}

    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $purchase = Purchase::create([
                'party_id' => $data['party_id'],
                'total'    => 0,
            ]);

            $total = 0;
            foreach ($data['items'] as $item) {
                $itemTotal = $item['qty'] * $item['price'];
                $purchase->items()->create([
                    'particular' => $item['particular'],
                    'qty'        => $item['qty'],
                    'price'      => $item['price'],
                    'total'      => $itemTotal,
                ]);
                $total += $itemTotal;
            }

            $purchase->update(['total' => $total]);
            $this->ledger->recordPurchase($purchase);

            return $purchase;
        });
    }

    public function delete(Purchase $purchase): void
    {
        DB::transaction(fn() => $purchase->delete());
    }
}
```

---

## PaymentService

```php
class PaymentService
{
    public function __construct(private LedgerService $ledger) {}

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            // Enforce: only one of sale_id or purchase_id
            if (!empty($data['sale_id']) && !empty($data['purchase_id'])) {
                throw new \InvalidArgumentException('Payment cannot link to both a sale and a purchase.');
            }

            $payment = Payment::create([
                'party_id'    => $data['party_id'],
                'amount'      => $data['amount'],
                'type'        => $data['type'], // received | given
                'account_id'  => $data['account_id'],
                'sale_id'     => $data['sale_id'] ?? null,
                'purchase_id' => $data['purchase_id'] ?? null,
            ]);

            $this->ledger->recordPayment($payment);

            return $payment;
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->ledger->reversePayment($payment);
            $payment->delete();
        });
    }
}
```

---

## Validation Rules (for FormRequest or manual)

### Sale / Purchase
```php
'party_id'       => 'required|integer|exists:parties,id',
'items'          => 'required|array|min:1',
'items.*.particular' => 'required|string|max:255',
'items.*.qty'    => 'required|numeric|min:0.01',
'items.*.price'  => 'required|numeric|min:0',
```

### Payment
```php
'party_id'    => 'required|integer|exists:parties,id',
'amount'      => 'required|numeric|min:0.01',
'type'        => 'required|in:received,given',
'account_id'  => 'required|integer|exists:accounts,id',
'sale_id'     => 'nullable|integer|exists:sales,id',
'purchase_id' => 'nullable|integer|exists:purchases,id',
// Custom rule: sale_id and purchase_id cannot both be present
```

---

## Notes on Advance Payments
- If both `sale_id` and `purchase_id` are null → it's an advance
- Still creates 2 ledger entries (party + account) exactly the same way
- No special handling needed — the ledger balance reflects the advance automatically
