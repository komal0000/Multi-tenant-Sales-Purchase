<?php

namespace App\Http\Requests;

use App\Helpers\DateHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMassPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tenantId = (int) ($this->user()?->tenant_id ?? 0);

        $rows = collect($this->input('rows', []))
            ->map(function ($row) {
                $row = is_array($row) ? $row : [];

                return [
                    'party_id' => filled($row['party_id'] ?? null) ? (string) $row['party_id'] : null,
                    'account_id' => filled($row['account_id'] ?? null) ? (string) $row['account_id'] : null,
                    'amount' => filled($row['amount'] ?? null) ? $row['amount'] : null,
                    'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                ];
            })
            ->filter(function (array $row): bool {
                return filled($row['party_id'])
                    || filled($row['account_id'])
                    || filled($row['amount'])
                    || filled($row['notes']);
            })
            ->values()
            ->all();

        $this->merge([
            'tenant_id' => $tenantId,
            'date_bs' => filled($this->input('date_bs')) ? trim((string) $this->input('date_bs')) : null,
            'rows' => $rows,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->input('tenant_id') ?? 0);

        return [
            'date_bs' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'rows.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'rows.*.amount' => ['required', 'numeric', 'min:0.01'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! filled($this->input('date_bs'))) {
                return;
            }

            try {
                DateHelper::normalizeBsDate((string) $this->input('date_bs'));
            } catch (\Throwable $exception) {
                $validator->errors()->add('date_bs', $exception->getMessage());
            }
        });
    }
}
