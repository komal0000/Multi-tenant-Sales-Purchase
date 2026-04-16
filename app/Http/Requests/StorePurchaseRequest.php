<?php

namespace App\Http\Requests;

use App\Helpers\DateHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        $payload['items'] = collect($payload['items'] ?? [])
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'line_type' => $item['line_type'] ?? 'general',
                    'item_id' => $item['item_id'] ?? null,
                    'description' => filled($item['description'] ?? null)
                        ? trim((string) $item['description'])
                        : null,
                    'expense_category_id' => $item['expense_category_id'] ?? null,
                    'qty' => $item['qty'] ?? null,
                    'rate' => $item['rate'] ?? null,
                ];
            })
            ->filter(fn (array $item) => filled($item['item_id']) || filled($item['description']) || filled($item['expense_category_id']) || filled($item['qty']) || filled($item['rate']))
            ->values()
            ->all();

        $payload['payments'] = collect($payload['payments'] ?? [])
            ->map(function ($payment) {
                $payment = is_array($payment) ? $payment : [];

                return [
                    'account_id' => $payment['account_id'] ?? null,
                    'amount' => $payment['amount'] ?? null,
                    'cheque_number' => filled($payment['cheque_number'] ?? null)
                        ? trim((string) $payment['cheque_number'])
                        : null,
                ];
            })
            ->filter(fn (array $payment) => filled($payment['account_id']) || filled($payment['amount']) || filled($payment['cheque_number']))
            ->values()
            ->all();

        $this->replace($payload);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()?->tenant_id ?? 0);

        return [
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'date_bs' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.line_type' => ['required', 'in:item,general,expense'],
            'items.*.item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'items.*.description' => ['nullable', 'string'],
            'items.*.expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'items.*.qty' => ['nullable', 'numeric', 'min:0.0001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.cheque_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            try {
                DateHelper::normalizeBsDate((string) $this->input('date_bs'));
            } catch (\Throwable $exception) {
                $validator->errors()->add('date_bs', $exception->getMessage());
            }

            foreach ($this->input('items', []) as $index => $item) {
                $lineType = $item['line_type'] ?? null;

                if ($lineType === 'item' && ! filled($item['item_id'] ?? null)) {
                    $validator->errors()->add("items.$index.item_id", 'Select an item for item lines.');
                }

                if ($lineType === 'general' && ! filled($item['description'] ?? null)) {
                    $validator->errors()->add("items.$index.description", 'Description is required for general lines.');
                }

                if ($lineType === 'expense' && ! filled($item['expense_category_id'] ?? null)) {
                    $validator->errors()->add("items.$index.expense_category_id", 'Select an expense category for expense lines.');
                }

                if (! filled($item['qty'] ?? null) || (float) ($item['qty'] ?? 0) <= 0) {
                    $validator->errors()->add("items.$index.qty", 'Quantity must be greater than zero.');
                }
            }
        });
    }
}
