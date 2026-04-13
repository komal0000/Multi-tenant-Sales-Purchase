<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\User;
use App\Services\PartyCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyQuickEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_party_entry_accepts_optional_phone_and_address(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('parties.store'), [
                'name' => 'Quick Party',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('party.name', 'Quick Party')
            ->assertJsonPath('party.phone', null)
            ->assertJsonPath('party.address', null)
            ->assertJsonPath('party.opening_balance', 0)
            ->assertJsonPath('party.opening_balance_side', 'dr');

        $party = Party::query()->where('name', 'Quick Party')->firstOrFail();

        $this->assertNull($party->phone);
        $this->assertNull($party->address);
        $this->assertSame('dr', $party->opening_balance_side);

        $cachedParties = app(PartyCacheService::class)->all();
        $this->assertTrue($cachedParties->contains(fn (Party $cachedParty) => $cachedParty->id === $party->id));
    }

    public function test_quick_party_entry_accepts_full_payload(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('parties.store'), [
                'name' => 'Full Party',
                'phone' => '9800000011',
                'address' => 'Biratnagar-3',
                'opening_balance' => '1500.50',
                'opening_balance_side' => 'cr',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('party.name', 'Full Party')
            ->assertJsonPath('party.phone', '9800000011')
            ->assertJsonPath('party.address', 'Biratnagar-3')
            ->assertJsonPath('party.opening_balance', 1500.5)
            ->assertJsonPath('party.opening_balance_side', 'cr');

        $this->assertDatabaseHas('parties', [
            'name' => 'Full Party',
            'phone' => '9800000011',
            'address' => 'Biratnagar-3',
            'opening_balance_side' => 'cr',
        ]);
    }

    public function test_quick_party_entry_accepts_plus_977_phone_and_normalizes_it(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('parties.store'), [
                'name' => 'Nepal Format Party',
                'phone' => '+977-9800000044',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('party.phone', '9800000044');

        $this->assertDatabaseHas('parties', [
            'name' => 'Nepal Format Party',
            'phone' => '9800000044',
        ]);
    }

    public function test_party_store_redirects_to_party_detail_for_form_submission(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('parties.store'), [
                'name' => 'Form Party',
                'phone' => '9800000099',
                'address' => 'Dharan-8',
            ]);

        $party = Party::query()->where('name', 'Form Party')->firstOrFail();

        $response
            ->assertRedirect(route('parties.show', $party))
            ->assertSessionHas('success', 'Party created successfully.');

        $this->assertDatabaseHas('parties', [
            'name' => 'Form Party',
            'phone' => '9800000099',
            'address' => 'Dharan-8',
        ]);

        $cachedParties = app(PartyCacheService::class)->all();
        $this->assertTrue($cachedParties->contains(fn (Party $cachedParty) => $cachedParty->id === $party->id));
    }

    public function test_parties_index_renders_inline_entry_controls(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('parties.index'));

        $response
            ->assertOk()
            ->assertSee('id="party-inline-entry-row"', false)
            ->assertSee('id="party-inline-name"', false)
            ->assertSee('id="party-inline-opening-balance"', false)
            ->assertSee('id="party-inline-opening-balance-side"', false)
            ->assertSee('Receivable', false)
            ->assertSee('Payable', false)
            ->assertSee('id="party-inline-save"', false)
            ->assertSee('data-party-post-save="reload"', false);
    }

    public function test_quick_party_entry_rejects_phone_longer_than_ten_digits(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('parties.store'), [
                'name' => 'Invalid Phone Party',
                'phone' => '980000001122',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        $this->assertDatabaseMissing('parties', [
            'name' => 'Invalid Phone Party',
        ]);
    }

    public function test_parties_create_page_route_is_not_available(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/parties/create')
            ->assertNotFound();
    }
}
