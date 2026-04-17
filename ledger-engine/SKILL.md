# SKILL: Ledger Engine — The Core

## Purpose
This skill defines how to write ledger entries correctly.
The ledger is the **ONLY source of truth** — all balances are derived from it.

---

## The Golden Rule
```
Balance = SUM(dr_amount) - SUM(cr_amount)
```
- **Positive** → party owes YOU / money is IN the account
- **Negative** → YOU owe the party / money went OUT

---

## Ledger Row Structure
Each row affects **exactly one entity**: either a party OR an account. Never both.

### Date Convention (Required)
- Any business `date` field in ledger/business tables must store **Nepali BS date integer** in `YYYYMMDD` format.
- UI/API can accept BS date strings like `YYYY-MM-DD`, but persistence must be integer `YYYYMMDD`.
- Example: `2082-01-03` must be stored as `20820103`.

```php
Ledger::create([
    'party_id'   => $partyId,   // fill ONE of these
    'account_id' => null,        // leave the other null
    'dr_amount'  => 1000,        // fill ONE of these
    'cr_amount'  => 0,           // leave the other 0
    'type'       => 'sale',
    'ref_id'     => $sale->id,
    'ref_table'  => 'sales',
    'date'       => 20820103,
]);
```

---

## DR / CR Reference Table

### Party Ledger
| Situation | DR | CR |
|---|---|---|
| Sale (party owes you) | ✅ total | 0 |
| Purchase (you owe party) | 0 | ✅ total |
| Payment received from party | 0 | ✅ amount |
| Payment given to party | ✅ amount | 0 |

### Account (Cash/Bank) Ledger
| Situation | DR | CR |
|---|---|---|
| Money comes in (received) | ✅ amount | 0 |
| Money goes out (given) | 0 | ✅ amount |

---

## Transaction Flows (Exact Entries)

### 1. Sale (total = 1000)
```
Ledger row 1: party_id=X, dr=1000, cr=0, type=sale, ref=sale_id
```
One entry. Party DR.

### 2. Purchase (total = 500)
```
Ledger row 1: party_id=X, dr=0, cr=500, type=purchase, ref=purchase_id
```
One entry. Party CR.

### 3. Payment Received (amount = 400, account = Cash)
```
Ledger row 1: party_id=X,      dr=0,   cr=400, type=payment, ref=payment_id
Ledger row 2: account_id=Cash, dr=400, cr=0,   type=payment, ref=payment_id
```
Two entries. Party CR + Account DR.

### 4. Payment Given (amount = 200, account = Cash)
```
Ledger row 1: party_id=X,      dr=200, cr=0,   type=payment, ref=payment_id
Ledger row 2: account_id=Cash, dr=0,   cr=200, type=payment, ref=payment_id
```
Two entries. Party DR + Account CR.

---

## LedgerService Template

```php
class LedgerService
{
    public function recordSale(Sale $sale): void
    {
        Ledger::create([
            'party_id'  => $sale->party_id,
            'dr_amount' => $sale->total,
            'cr_amount' => 0,
            'type'      => 'sale',
            'ref_id'    => $sale->id,
            'ref_table' => 'sales',
        ]);
    }

    public function recordPurchase(Purchase $purchase): void
    {
        Ledger::create([
            'party_id'  => $purchase->party_id,
            'dr_amount' => 0,
            'cr_amount' => $purchase->total,
            'type'      => 'purchase',
            'ref_id'    => $purchase->id,
            'ref_table' => 'purchases',
        ]);
    }

    public function recordPayment(Payment $payment): void
    {
        $isReceived = $payment->type === 'received';

        // Party entry
        Ledger::create([
            'party_id'  => $payment->party_id,
            'dr_amount' => $isReceived ? 0 : $payment->amount,
            'cr_amount' => $isReceived ? $payment->amount : 0,
            'type'      => 'payment',
            'ref_id'    => $payment->id,
            'ref_table' => 'payments',
        ]);

        // Account entry
        Ledger::create([
            'account_id' => $payment->account_id,
            'dr_amount'  => $isReceived ? $payment->amount : 0,
            'cr_amount'  => $isReceived ? 0 : $payment->amount,
            'type'       => 'payment',
            'ref_id'     => $payment->id,
            'ref_table'  => 'payments',
        ]);
    }

    public function reversePayment(Payment $payment): void
    {
        $isReceived = $payment->type === 'received';

        // Reverse party entry
        Ledger::create([
            'party_id'  => $payment->party_id,
            'dr_amount' => $isReceived ? $payment->amount : 0,
            'cr_amount' => $isReceived ? 0 : $payment->amount,
            'type'      => 'payment',
            'ref_id'    => $payment->id,
            'ref_table' => 'payments',
        ]);

        // Reverse account entry
        Ledger::create([
            'account_id' => $payment->account_id,
            'dr_amount'  => $isReceived ? 0 : $payment->amount,
            'cr_amount'  => $isReceived ? $payment->amount : 0,
            'type'       => 'payment',
            'ref_id'     => $payment->id,
            'ref_table'  => 'payments',
        ]);
    }
}
```

---

## Balance Query Helpers

```php
// Party balance
public function partyBalance(string $partyId): float
{
    return Ledger::where('party_id', $partyId)
        ->selectRaw('SUM(dr_amount) - SUM(cr_amount) as balance')
        ->value('balance') ?? 0;
}

// Account balance
public function accountBalance(string $accountId): float
{
    return Ledger::where('account_id', $accountId)
        ->selectRaw('SUM(dr_amount) - SUM(cr_amount) as balance')
        ->value('balance') ?? 0;
}
```

---

## Verification Test Cases
After implementing, verify these manually or in tests:

| Action | Expected Party Balance | Expected Account Balance |
|---|---|---|
| Sale 1000 | +1000 | — |
| Payment received 400 (Cash) | +600 | Cash +400 |
| Purchase 500 | -500 | — |
| Payment given 200 (Cash) | -300 | Cash -200 |
