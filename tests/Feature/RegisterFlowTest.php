<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_register_page(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create your LedgerApp account');
    }

    public function test_user_can_register_and_is_logged_in_as_tenant_admin(): void
    {
        $response = $this->post(route('register.store'), [
            'business_name' => 'Acme Traders',
            'name' => 'Acme Owner',
            'phone' => '9800001234',
            'email' => 'owner@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('phone', 9800001234)->first();

        $this->assertNotNull($user);
        $this->assertSame(0, (int) $user->role);
        $this->assertNotNull($user->tenant_id);

        $this->assertDatabaseHas('tenants', [
            'id' => $user->tenant_id,
            'name' => 'Acme Traders',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
        ]);

        $tenant = Tenant::query()->find($user->tenant_id);

        $this->assertNotNull($tenant);
        $this->assertStringStartsWith('acme-traders', (string) $tenant->code);
    }

    public function test_registration_requires_unique_phone(): void
    {
        User::factory()->create([
            'phone' => 9800009999,
        ]);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'business_name' => 'New Company',
                'name' => 'New Owner',
                'phone' => '9800009999',
                'email' => 'new-owner@test.local',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['phone']);

        $this->assertGuest();
    }

    public function test_login_page_shows_register_call_to_action(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('New here? Create account')
            ->assertSee('Register now');
    }
}
