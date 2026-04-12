<?php

namespace App\Http\Requests;

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
        ];
    }
}
