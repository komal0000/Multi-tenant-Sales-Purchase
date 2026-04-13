<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parentCategoryId = $this->input('parent_category_id', $this->input('parent_id'));
        $parentCategoryId = filled($parentCategoryId) ? (int) $parentCategoryId : null;

        if ($parentCategoryId === 0) {
            $parentCategoryId = null;
        }

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'parent_category_id' => $parentCategoryId,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()?->tenant_id ?? 0);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'parent_category_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
