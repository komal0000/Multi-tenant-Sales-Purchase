<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Sale;

class LedgerService
{
    public function recordSale(Sale $sale): void
    {
        Ledger::query()->create([
            'party_id' => $sale->party_id,
            'account_id' => null,
            'dr_amount' => $sale->total,
            'cr_amount' => 0,
            'type' => 'sale',
            'ref_id' => $sale->id,
            'ref_table' => 'sales',
        ]);
    }

    public function reverseSale(Sale $sale): void
    {
        Ledger::query()->create([
            'party_id' => $sale->party_id,
            'account_id' => null,
            'dr_amount' => 0,
            'cr_amount' => $sale->total,
            'type' => 'sale',
            'ref_id' => $sale->id,
            'ref_table' => 'sales',
        ]);
    }

    public function recordPurchase(Purchase $purchase): void
    {
        Ledger::query()->create([
            'party_id' => $purchase->party_id,
            'account_id' => null,
            'dr_amount' => 0,
            'cr_amount' => $purchase->total,
            'type' => 'purchase',
            'ref_id' => $purchase->id,
            'ref_table' => 'purchases',
        ]);
    }

    public function reversePurchase(Purchase $purchase): void
    {
        Ledger::query()->create([
            'party_id' => $purchase->party_id,
            'account_id' => null,
            'dr_amount' => $purchase->total,
            'cr_amount' => 0,
            'type' => 'purchase',
            'ref_id' => $purchase->id,
            'ref_table' => 'purchases',
        ]);
    }

    public function recordPayment(Payment $payment): void
    {
        $isReceived = $payment->type === 'received';

        Ledger::query()->create([
            'party_id' => $payment->party_id,
            'account_id' => null,
            'dr_amount' => $isReceived ? 0 : $payment->amount,
            'cr_amount' => $isReceived ? $payment->amount : 0,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);

        Ledger::query()->create([
            'party_id' => null,
            'account_id' => $payment->account_id,
            'dr_amount' => $isReceived ? $payment->amount : 0,
            'cr_amount' => $isReceived ? 0 : $payment->amount,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);
    }

    public function reversePayment(Payment $payment): void
    {
        $isReceived = $payment->type === 'received';

        Ledger::query()->create([
            'party_id' => $payment->party_id,
            'account_id' => null,
            'dr_amount' => $isReceived ? $payment->amount : 0,
            'cr_amount' => $isReceived ? 0 : $payment->amount,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);

        Ledger::query()->create([
            'party_id' => null,
            'account_id' => $payment->account_id,
            'dr_amount' => $isReceived ? 0 : $payment->amount,
            'cr_amount' => $isReceived ? $payment->amount : 0,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);
    }

    public function partyBalance(string $partyId): float
    {
        $ledgerBalance = (float) (Ledger::query()
            ->where('party_id', $partyId)
            ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->value('balance') ?? 0);

        $party = Party::query()->find($partyId);

        return $ledgerBalance + $this->openingSigned((float) ($party?->opening_balance ?? 0), $party?->opening_balance_side ?? 'dr');
    }

    public function accountBalance(string $accountId): float
    {
        $ledgerBalance = (float) (Ledger::query()
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->value('balance') ?? 0);

        $account = Account::query()->find($accountId);

        return $ledgerBalance + $this->openingSigned((float) ($account?->opening_balance ?? 0), $account?->opening_balance_side ?? 'dr');
    }

    private function openingSigned(float $amount, string $side): float
    {
        return $side === 'cr' ? -$amount : $amount;
    }
}
