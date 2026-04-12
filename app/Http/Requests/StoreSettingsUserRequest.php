<?php

namespace App\Http\Requests;

use App\Support\NepalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettingsUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $phone = $this->input('phone');
        $phone = filled($phone) ? trim((string) $phone) : null;

        if ($phone !== null && preg_match(NepalPhone::INPUT_PATTERN, $phone) === 1) {
            $phone = NepalPhone::normalizeForStorage($phone);
        }

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'phone' => $phone,
            'email' => filled($email) ? trim((string) $email) : null,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()?->tenant_id ?? 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'regex:' . NepalPhone::STORAGE_PATTERN,
                Rule::unique('users', 'phone')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
