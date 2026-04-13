<?php

namespace App\Http\Requests;

use App\Support\NepalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        $phone = filled($phone) ? trim((string) $phone) : null;

        if ($phone !== null && preg_match(NepalPhone::INPUT_PATTERN, $phone) === 1) {
            $phone = NepalPhone::normalizeForStorage($phone);
        }

        $email = $this->input('email');

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'business_name' => trim((string) $this->input('business_name', '')),
            'phone' => $phone,
            'email' => filled($email) ? strtolower(trim((string) $email)) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'regex:' . NepalPhone::STORAGE_PATTERN,
                Rule::unique('users', 'phone'),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
