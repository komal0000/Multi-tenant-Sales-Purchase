<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()?->tenant_id ?? 0);

        return [
            'salary_month_bs' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'salary_date_bs' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'leaves' => ['nullable', 'array'],
            'leaves.*' => ['nullable', 'numeric', 'min:0'],
            'overtimes' => ['nullable', 'array'],
            'overtimes.*' => ['nullable', 'numeric', 'min:0'],
            'save_as_expense' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
