<?php

namespace App\Http\Requests;

use App\Helpers\DateHelper;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\LedgerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        $cheque = $this->input('cheque_number');

        $this->merge([
            'date_bs' => $this->filled('date_bs') ? trim((string) $this->input('date_bs')) : null,
            'cheque_number' => filled($cheque) ? trim((string) $cheque) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'type' => $this->filled('type') ? $this->input('type') : null,
            'sale_id' => $this->filled('sale_id') ? $this->input('sale_id') : null,
            'purchase_id' => $this->filled('purchase_id') ? $this->input('purchase_id') : null,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()?->tenant_id ?? 0);

        return [
            'date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['nullable', 'in:received,given'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'cheque_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
            'sale_id' => ['nullable', 'integer', Rule::exists('sales', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', Sale::STATUS_ACTIVE))],
            'purchase_id' => ['nullable', 'integer', Rule::exists('purchases', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', Purchase::STATUS_ACTIVE))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (filled($this->input('date_bs'))) {
                try {
                    DateHelper::normalizeBsDate((string) $this->input('date_bs'));
                } catch (\Throwable $exception) {
                    $validator->errors()->add('date_bs', $exception->getMessage());
                }
            }

            $saleId = $this->input('sale_id');
            $purchaseId = $this->input('purchase_id');
            $partyId = (int) $this->input('party_id');

            if (! empty($saleId) && ! empty($purchaseId)) {
                $validator->errors()->add('sale_id', 'A payment can be linked to a sale or purchase, not both.');
                $validator->errors()->add('purchase_id', 'A payment can be linked to a sale or purchase, not both.');

                return;
            }

            if (! empty($saleId)) {
                $isPartyMatched = Sale::query()
                    ->whereKey($saleId)
                    ->where('party_id', $partyId)
                    ->where('status', Sale::STATUS_ACTIVE)
                    ->exists();

                if (! $isPartyMatched) {
                    $validator->errors()->add('sale_id', 'The selected sale does not belong to the chosen party.');
                }
            }

            if (! empty($purchaseId)) {
                $isPartyMatched = Purchase::query()
                    ->whereKey($purchaseId)
                    ->where('party_id', $partyId)
                    ->where('status', Purchase::STATUS_ACTIVE)
                    ->exists();

                if (! $isPartyMatched) {
                    $validator->errors()->add('purchase_id', 'The selected purchase does not belong to the chosen party.');
                }
            }
        });
    }
}
