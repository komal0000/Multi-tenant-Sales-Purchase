<?php

namespace App\Http\Requests;

use App\Support\NepalPhone;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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

        $this->merge([
            'phone' => $phone,
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'regex:' . NepalPhone::STORAGE_PATTERN],
            'password' => ['required', 'string'],
        ];
    }
}
