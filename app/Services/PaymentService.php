<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $this->assertTenantScopedRelations(
                $data['party_id'] ?? null,
                $data['account_id'] ?? null
            );
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
                'date' => (int) ($data['date'] ?? DateHelper::currentBsInt()),
                'created_at' => $data['created_at'] ?? null,
                'updated_at' => $data['updated_at'] ?? null,
            ]);

            $this->ledger->recordPayment($payment);

            return $payment->load(['party', 'account', 'sale', 'purchase']);
        });
    }

    public function updateStandalone(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $this->assertStandalone($payment);
            $this->assertTenantScopedRelations(
                $data['party_id'] ?? null,
                $data['account_id'] ?? null
            );

            $payment->forceFill([
                'party_id' => $data['party_id'],
                'amount' => $data['amount'],
                'type' => $data['type'],
                'account_id' => $data['account_id'],
                'cheque_number' => $data['cheque_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'date' => (int) ($data['date'] ?? $payment->date),
                'created_at' => $data['created_at'] ?? $payment->created_at,
                'updated_at' => $data['updated_at'] ?? now(),
            ])->save();

            $this->ledger->removeEntries('payments', $payment->id);
            $this->ledger->recordPayment($payment);

            return $payment->load(['party', 'account', 'sale', 'purchase']);
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $this->ledger->removeEntries('payments', $payment->id);
            $payment->delete();
        });
    }

    public function deleteStandalone(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $this->assertStandalone($payment);
            $this->ledger->removeEntries('payments', $payment->id);
            $payment->delete();
        });
    }

    public function timestampForBsDate(string $paymentDateAd, ?string $time = null): Carbon
    {
        return Carbon::parse($paymentDateAd.' '.($time ?: now()->format('H:i:s')));
    }

    private function assertTenantScopedRelations(mixed $partyId, mixed $accountId): void
    {
        $partyExists = Party::query()->whereKey($partyId)->exists();
        if (! $partyExists) {
            throw new InvalidArgumentException('The selected party is invalid for this tenant.');
        }

        $accountExists = Account::query()->whereKey($accountId)->exists();
        if (! $accountExists) {
            throw new InvalidArgumentException('The selected account is invalid for this tenant.');
        }
    }

    private function assertStandalone(Payment $payment): void
    {
        if ($payment->sale_id || $payment->purchase_id) {
            throw new InvalidArgumentException('Linked bill payments cannot be changed from mass payment.');
        }
    }
}
