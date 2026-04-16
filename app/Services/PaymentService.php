<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

            if (! empty($data['sale_id']) && ! empty($data['purchase_id'])) {
                throw new InvalidArgumentException('Payment cannot link to both a sale and a purchase.');
            }

            if (! empty($data['sale_id'])) {
                $isPartyMatched = Sale::query()
                    ->whereKey($data['sale_id'])
                    ->where('party_id', $data['party_id'])
                    ->exists();

                if (! $isPartyMatched) {
                    throw new InvalidArgumentException('The selected sale does not belong to the chosen party.');
                }
            }

            if (! empty($data['purchase_id'])) {
                $isPartyMatched = Purchase::query()
                    ->whereKey($data['purchase_id'])
                    ->where('party_id', $data['party_id'])
                    ->exists();

                if (! $isPartyMatched) {
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
                'notes' => $data['notes'] ?? null,
            ]);

            $this->ledger->recordPayment($payment);

            return $payment->load(['party', 'account', 'sale', 'purchase']);
        });
    }

    /**
     * @param  array<int, array{party_id:int|string, account_id:int|string, amount:float|int|string, notes:?string}>  $rows
     * @return Collection<int, Payment>
     */
    public function createBatch(array $rows, string $type, string $paymentDateAd): Collection
    {
        return DB::transaction(function () use ($rows, $type, $paymentDateAd) {
            $timestamp = Carbon::parse($paymentDateAd.' '.now()->format('H:i:s'));
            $payments = collect();

            foreach ($rows as $row) {
                $payments->push($this->createBatchRow($row, $type, $timestamp));
            }

            return $payments;
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $this->ledger->reversePayment($payment);
            $payment->delete();
        });
    }

    /**
     * @param  array{party_id:int|string, account_id:int|string, amount:float|int|string, notes:?string}  $row
     */
    private function createBatchRow(array $row, string $type, Carbon $timestamp): Payment
    {
        $partyExists = Party::query()->whereKey($row['party_id'] ?? null)->exists();
        if (! $partyExists) {
            throw new InvalidArgumentException('The selected party is invalid for this tenant.');
        }

        $accountExists = Account::query()->whereKey($row['account_id'] ?? null)->exists();
        if (! $accountExists) {
            throw new InvalidArgumentException('The selected account is invalid for this tenant.');
        }

        $payment = Payment::query()->create([
            'party_id' => $row['party_id'],
            'amount' => $row['amount'],
            'type' => $type,
            'account_id' => $row['account_id'],
            'cheque_number' => null,
            'sale_id' => null,
            'purchase_id' => null,
            'notes' => $row['notes'] ?? null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->ledger->recordPayment($payment);

        return $payment->load(['party', 'account']);
    }
}
