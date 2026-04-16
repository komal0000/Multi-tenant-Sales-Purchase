<?php

namespace App\Http\Requests;

use App\Helpers\DateHelper;
use Illuminate\Foundation\Http\FormRequest;

class StorePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address');
        $phone = $this->input('phone');
        $phone = filled($phone) ? trim((string) $phone) : null;

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'phone' => $phone,
            'address' => filled($address) ? trim((string) $address) : null,
            'opening_balance_date_bs' => filled($this->input('opening_balance_date_bs'))
                ? trim((string) $this->input('opening_balance_date_bs'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_side' => ['nullable', 'in:dr,cr'],
            'opening_balance_date_bs' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! filled($this->input('opening_balance_date_bs'))) {
                return;
            }

            try {
                DateHelper::normalizeBsDate((string) $this->input('opening_balance_date_bs'));
            } catch (\Throwable $exception) {
                $validator->errors()->add('opening_balance_date_bs', $exception->getMessage());
            }
        });
    }
}
