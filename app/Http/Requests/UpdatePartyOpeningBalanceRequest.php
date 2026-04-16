<?php

namespace App\Http\Requests;

use App\Helpers\DateHelper;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartyOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_side' => ['required', 'in:dr,cr'],
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
