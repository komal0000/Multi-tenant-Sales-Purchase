<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_search_returns_paginated_results_for_selected_party(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $partyA = Party::query()->create([
            'name' => 'Party A',
            'phone' => null,
        ]);

        $partyB = Party::query()->create([
            'name' => 'Party B',
            'phone' => null,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            Sale::query()->create([
                'party_id' => $partyA->id,
                'total' => $i * 100,
            ]);
        }

        for ($i = 1; $i <= 4; $i++) {
            Sale::query()->create([
                'party_id' => $partyB->id,
                'total' => $i * 50,
            ]);
        }

        $firstPage = $this->actingAs($user)
            ->getJson(route('payments.search-sales', ['party_id' => $partyA->id]));

        $firstPage
            ->assertOk()
            ->assertJsonCount(20, 'results')
            ->assertJsonPath('pagination.more', true);

        $firstPageIds = collect($firstPage->json('results'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame(0, Sale::query()
            ->whereIn('id', $firstPageIds)
            ->where('party_id', '!=', $partyA->id)
            ->count());

        $secondPage = $this->actingAs($user)
            ->getJson(route('payments.search-sales', ['party_id' => $partyA->id, 'page' => 2]));

        $secondPage
            ->assertOk()
            ->assertJsonCount(5, 'results')
            ->assertJsonPath('pagination.more', false);
    }

    public function test_sales_search_without_party_returns_empty_results(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('payments.search-sales'));

        $response
            ->assertOk()
            ->assertJsonCount(0, 'results')
            ->assertJsonPath('pagination.more', false);
    }

    public function test_sales_search_excludes_cancelled_sales(): void
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Cancelled Sales Party',
            'phone' => null,
        ]);

        $activeSale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 100,
            'status' => Sale::STATUS_ACTIVE,
        ]);

        Sale::query()->create([
            'party_id' => $party->id,
            'total' => 200,
            'status' => Sale::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('payments.search-sales', ['party_id' => $party->id]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', (string) $activeSale->id)
            ->assertJsonPath('pagination.more', false);
    }

    public function test_purchase_search_returns_paginated_results_for_selected_party(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $partyA = Party::query()->create([
            'name' => 'Purchase Party A',
            'phone' => null,
        ]);

        $partyB = Party::query()->create([
            'name' => 'Purchase Party B',
            'phone' => null,
        ]);

        for ($i = 1; $i <= 23; $i++) {
            Purchase::query()->create([
                'party_id' => $partyA->id,
                'total' => $i * 70,
            ]);
        }

        for ($i = 1; $i <= 3; $i++) {
            Purchase::query()->create([
                'party_id' => $partyB->id,
                'total' => $i * 90,
            ]);
        }

        $firstPage = $this->actingAs($user)
            ->getJson(route('payments.search-purchases', ['party_id' => $partyA->id]));

        $firstPage
            ->assertOk()
            ->assertJsonCount(20, 'results')
            ->assertJsonPath('pagination.more', true);

        $secondPage = $this->actingAs($user)
            ->getJson(route('payments.search-purchases', ['party_id' => $partyA->id, 'page' => 2]));

        $secondPage
            ->assertOk()
            ->assertJsonCount(3, 'results')
            ->assertJsonPath('pagination.more', false);

        $secondPageIds = collect($secondPage->json('results'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame(0, Purchase::query()
            ->whereIn('id', $secondPageIds)
            ->where('party_id', '!=', $partyA->id)
            ->count());
    }

    public function test_purchase_search_excludes_cancelled_purchases(): void
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Cancelled Purchases Party',
            'phone' => null,
        ]);

        $activePurchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 120,
            'status' => Purchase::STATUS_ACTIVE,
        ]);

        Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 240,
            'status' => Purchase::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('payments.search-purchases', ['party_id' => $party->id]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', (string) $activePurchase->id)
            ->assertJsonPath('pagination.more', false);
    }
}
