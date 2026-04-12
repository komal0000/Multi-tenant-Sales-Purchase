<?php

namespace App\Http\Requests;

use App\Support\NepalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('edit_email');
        $phone = $this->input('edit_phone');
        $phone = filled($phone) ? trim((string) $phone) : null;

        if ($phone !== null && preg_match(NepalPhone::INPUT_PATTERN, $phone) === 1) {
            $phone = NepalPhone::normalizeForStorage($phone);
        }

        $this->merge([
            'edit_name' => trim((string) $this->input('edit_name', '')),
            'edit_phone' => $phone,
            'edit_email' => filled($email) ? trim((string) $email) : null,
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $ignoreUserId = is_object($user) ? $user->id : $user;
        $tenantId = (int) ($this->user()?->tenant_id ?? 0);

        return [
            'edit_user_id' => ['required', 'integer'],
            'edit_name' => ['required', 'string', 'max:255'],
            'edit_phone' => [
                'required',
                'regex:' . NepalPhone::STORAGE_PATTERN,
                Rule::unique('users', 'phone')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($ignoreUserId),
            ],
            'edit_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($ignoreUserId),
            ],
            'edit_password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
