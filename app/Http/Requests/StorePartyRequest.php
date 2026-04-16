<?php

namespace App\Http\Requests;

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
        ];
    }
}
