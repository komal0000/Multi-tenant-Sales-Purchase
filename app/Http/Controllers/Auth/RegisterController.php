<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private readonly Factory $auth,
        private readonly DatabaseManager $db,
    ) {
    }

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var array{0: Tenant, 1: User} $created */
        $created = $this->db->transaction(function () use ($validated): array {
            $tenant = Tenant::query()->create([
                'name' => $validated['business_name'],
                'code' => $this->generateTenantCode($validated['business_name']),
                'timezone' => 'Asia/Kathmandu',
                'currency_code' => 'NPR',
            ]);

            $email = filled($validated['email'] ?? null)
                ? $validated['email']
                : "owner.{$validated['phone']}@ledger.local";

            $user = User::query()->create([
                'name' => $validated['name'],
                'phone' => (int) $validated['phone'],
                'email' => $email,
                'password' => $validated['password'],
                'role' => 0,
                'tenant_id' => $tenant->id,
            ]);

            return [$tenant, $user];
        });

        [, $user] = $created;

        $this->auth->guard()->login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Registration successful. Welcome to LedgerApp.');
    }

    private function generateTenantCode(string $businessName): string
    {
        $base = Str::of($businessName)
            ->trim()
            ->lower()
            ->slug('-')
            ->limit(40, '')
            ->value();

        $prefix = $base !== '' ? $base : 'tenant';

        do {
            $candidate = $prefix . '-' . Str::lower(Str::random(6));
        } while (Tenant::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
