<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_fine_per_day' => ['required', 'numeric', 'min:0'],
            'overtime_money_per_day' => ['required', 'numeric', 'min:0'],
            'payment_sidebar_limit' => ['required', 'integer', 'min:1', 'max:25'],
        ];
    }
}
