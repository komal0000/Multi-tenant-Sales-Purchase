<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Payment;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $partyExists = Party::query()->whereKey($data['party_id'] ?? null)->exists();
            if (! $partyExists) {
                throw new InvalidArgumentException('The selected party is invalid for this tenant.');
            }

            $accountExists = Account::query()->whereKey($data['account_id'] ?? null)->exists();
            if (! $accountExists) {
                throw new InvalidArgumentException('The selected account is invalid for this tenant.');
            }

            if (!empty($data['sale_id']) && !empty($data['purchase_id'])) {
                throw new InvalidArgumentException('Payment cannot link to both a sale and a purchase.');
            }

            if (!empty($data['sale_id'])) {
                $isPartyMatched = Sale::query()
                    ->whereKey($data['sale_id'])
                    ->where('party_id', $data['party_id'])
                    ->exists();

                if (!$isPartyMatched) {
                    throw new InvalidArgumentException('The selected sale does not belong to the chosen party.');
                }
            }

            if (!empty($data['purchase_id'])) {
                $isPartyMatched = Purchase::query()
                    ->whereKey($data['purchase_id'])
                    ->where('party_id', $data['party_id'])
                    ->exists();

                if (!$isPartyMatched) {
                    throw new InvalidArgumentException('The selected purchase does not belong to the chosen party.');
                }
            }

            $payment = Payment::query()->create([
                'party_id' => $data['party_id'],
                'amount' => $data['amount'],
                'type' => $data['type'],
                'account_id' => $data['account_id'],
                'cheque_number' => $data['cheque_number'] ?? null,
                'sale_id' => $data['sale_id'] ?: null,
                'purchase_id' => $data['purchase_id'] ?: null,
            ]);

            $this->ledger->recordPayment($payment);

            return $payment->load(['party', 'account', 'sale', 'purchase']);
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $this->ledger->reversePayment($payment);
            $payment->delete();
        });
    }
}
