<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRouteIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_tenant_is_forbidden_by_tenant_middleware(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        User::query()
            ->whereKey($user->id)
            ->update(['tenant_id' => null]);

        $user->refresh();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_cross_tenant_sale_show_returns_not_found(): void
    {
        $defaultTenantId = $this->defaultTenantId();
        $otherTenant = $this->createOtherTenant();

        $party = Party::query()->create([
            'name' => 'Tenant A Sale Party',
            'phone' => null,
            'tenant_id' => $defaultTenantId,
        ]);

        $sale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 1200,
            'tenant_id' => $defaultTenantId,
        ]);

        /** @var User $otherTenantUser */
        $otherTenantUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 1,
        ]);

        $this->actingAs($otherTenantUser)
            ->get(route('sales.show', $sale))
            ->assertNotFound();
    }

    public function test_cross_tenant_admin_cannot_delete_other_tenant_sale(): void
    {
        $defaultTenantId = $this->defaultTenantId();
        $otherTenant = $this->createOtherTenant();

        $party = Party::query()->create([
            'name' => 'Tenant A Protected Sale Party',
            'phone' => null,
            'tenant_id' => $defaultTenantId,
        ]);

        $sale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 2500,
            'tenant_id' => $defaultTenantId,
        ]);

        /** @var User $otherTenantAdmin */
        $otherTenantAdmin = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 0,
        ]);

        $this->actingAs($otherTenantAdmin)
            ->delete(route('sales.destroy', $sale))
            ->assertNotFound();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cross_tenant_payment_show_returns_not_found(): void
    {
        $defaultTenantId = $this->defaultTenantId();
        $otherTenant = $this->createOtherTenant();

        $party = Party::query()->create([
            'name' => 'Tenant A Payment Party',
            'phone' => null,
            'tenant_id' => $defaultTenantId,
        ]);

        $account = Account::query()->create([
            'name' => 'Tenant A Cash',
            'type' => 'cash',
            'tenant_id' => $defaultTenantId,
        ]);

        $payment = Payment::query()->create([
            'party_id' => $party->id,
            'amount' => 500,
            'type' => 'received',
            'account_id' => $account->id,
            'sale_id' => null,
            'purchase_id' => null,
            'tenant_id' => $defaultTenantId,
        ]);

        /** @var User $otherTenantUser */
        $otherTenantUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 1,
        ]);

        $this->actingAs($otherTenantUser)
            ->get(route('payments.show', $payment))
            ->assertNotFound();
    }

    public function test_payment_search_rejects_other_tenant_party_filter(): void
    {
        $defaultTenantId = $this->defaultTenantId();
        $otherTenant = $this->createOtherTenant();

        $party = Party::query()->create([
            'name' => 'Tenant A Filter Party',
            'phone' => null,
            'tenant_id' => $defaultTenantId,
        ]);

        /** @var User $otherTenantUser */
        $otherTenantUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 1,
        ]);

        $this->actingAs($otherTenantUser)
            ->getJson(route('payments.search-sales', ['party_id' => $party->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['party_id']);
    }

    private function defaultTenantId(): int
    {
        return (int) Tenant::query()->where('code', 'default')->value('id');
    }

    private function createOtherTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Other Tenant',
            'code' => 'other-tenant',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
        ]);
    }
}
