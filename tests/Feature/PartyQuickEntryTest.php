<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\LedgerService;
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

    public function test_opening_balance_is_synced_to_single_ledger_row_on_create_and_update(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson(route('parties.store'), [
                'name' => 'Synced Opening Party',
                'phone' => 'ACC-001',
                'opening_balance' => '1500.50',
                'opening_balance_side' => 'cr',
            ])
            ->assertCreated();

        $party = Party::query()->where('name', 'Synced Opening Party')->firstOrFail();

        $openingRows = Ledger::query()
            ->where('type', 'opening_balance')
            ->where('ref_table', 'parties')
            ->where('ref_id', $party->id)
            ->where('party_id', $party->id)
            ->get();

        $this->assertCount(1, $openingRows);
        $this->assertEqualsWithDelta(0.0, (float) $openingRows->first()->dr_amount, 0.0001);
        $this->assertEqualsWithDelta(1500.5, (float) $openingRows->first()->cr_amount, 0.0001);
        $this->assertSame(-1500.5, app(LedgerService::class)->partyBalance((string) $party->id));

        $this
            ->actingAs($user)
            ->patch(route('parties.opening-balance.update', $party), [
                'opening_balance' => '800.00',
                'opening_balance_side' => 'dr',
            ])
            ->assertRedirect(route('parties.show', $party));

        $updatedRows = Ledger::query()
            ->where('type', 'opening_balance')
            ->where('ref_table', 'parties')
            ->where('ref_id', $party->id)
            ->where('party_id', $party->id)
            ->get();

        $this->assertCount(1, $updatedRows);
        $this->assertEqualsWithDelta(800.0, (float) $updatedRows->first()->dr_amount, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $updatedRows->first()->cr_amount, 0.0001);
        $this->assertSame(800.0, app(LedgerService::class)->partyBalance((string) $party->id));
    }

    public function test_quick_party_entry_preserves_arbitrary_phone_text(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('parties.store'), [
                'name' => 'Arbitrary Phone Party',
                'phone' => ' +977-9800000044 ext 22 ',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('party.phone', '+977-9800000044 ext 22');

        $this->assertDatabaseHas('parties', [
            'name' => 'Arbitrary Phone Party',
            'phone' => '+977-9800000044 ext 22',
        ]);
    }

    public function test_party_balance_endpoint_and_ledger_quick_payment_follow_current_balance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        PayrollSetting::query()->create([
            'leave_fine_per_day' => 0,
            'overtime_money_per_day' => 0,
            'payment_sidebar_limit' => 3,
        ]);

        $party = Party::query()->create([
            'name' => 'Ledger Link Party',
            'phone' => null,
            'opening_balance' => 300,
            'opening_balance_side' => 'cr',
        ]);

        Ledger::query()->create([
            'party_id' => $party->id,
            'account_id' => null,
            'dr_amount' => 500,
            'cr_amount' => 0,
            'type' => 'sale',
            'ref_id' => 7001,
            'ref_table' => 'sales',
            'created_at' => '2026-04-05 10:00:00',
        ]);

        Ledger::query()->create([
            'party_id' => $party->id,
            'account_id' => null,
            'dr_amount' => 0,
            'cr_amount' => 100,
            'type' => 'payment',
            'ref_id' => 8001,
            'ref_table' => 'payments',
            'created_at' => '2026-04-06 10:00:00',
        ]);

        Ledger::query()->create([
            'party_id' => $party->id,
            'account_id' => null,
            'dr_amount' => 0,
            'cr_amount' => 50,
            'type' => 'payment',
            'ref_id' => 8002,
            'ref_table' => 'payments',
            'created_at' => '2026-04-07 10:00:00',
        ]);

        $balanceResponse = $this
            ->actingAs($user)
            ->getJson(route('payments.party-balance', ['party_id' => $party->id]));

        $balanceResponse
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('balance', 50)
                ->where('formatted_amount', '50.00')
                ->where('label', 'Receivable')
                ->where('direction', 'received')
                ->where('sidebar_limit', 3)
                ->has('recent_entries', 3)
                ->etc()
            );

        $ledgerResponse = $this
            ->actingAs($user)
            ->get(route('parties.ledger', $party));

        $ledgerResponse
            ->assertOk()
            ->assertSee('Quick Payment')
            ->assertSee('quick-payment-modal', false)
            ->assertSee('quick-payment-form', false)
            ->assertSee('name="party_id" value="'.$party->id.'"', false)
            ->assertSee('name="date_bs"', false)
            ->assertSee('Opening Balance');
    }

    public function test_payment_create_view_renders_recent_ledger_sidebar_panel(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Sidebar Party',
            'phone' => null,
        ]);

        $response = $this->actingAs($user)->get(route('payments.create', [
            'party_id' => $party->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('Recent Ledger')
            ->assertSee('payment-sidebar-entries', false)
            ->assertSee('max-h-[200px]', false)
            ->assertSee('hidden lg:block', false);
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

    public function test_parties_index_uses_quick_add_modal_trigger(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('parties.index'));

        $response
            ->assertOk()
            ->assertDontSee('id="party-inline-entry-row"', false)
            ->assertSee('data-open-quick-party-entry', false)
            ->assertSee('data-party-post-save="reload"', false);
    }

    public function test_employee_party_balance_endpoint_defaults_settled_advances_to_given(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Employee Advance Party',
            'phone' => null,
        ]);

        Employee::query()->create([
            'party_id' => $party->id,
            'salary' => 1200,
        ]);

        $this->actingAs($user)
            ->getJson(route('payments.party-balance', ['party_id' => $party->id]))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('balance', 0)
                ->where('label', 'Settled')
                ->where('direction', 'given')
                ->etc()
            );
    }

    public function test_employee_party_ledger_quick_payment_defaults_to_given_for_settled_balance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Employee Quick Payment Party',
            'phone' => null,
        ]);

        Employee::query()->create([
            'party_id' => $party->id,
            'salary' => 2000,
        ]);

        $this->actingAs($user)
            ->get(route('parties.ledger', $party))
            ->assertOk()
            ->assertSee('option value="given" selected', false);
    }

    public function test_quick_party_entry_accepts_phone_longer_than_ten_digits(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('parties.store'), [
                'name' => 'Long Phone Party',
                'phone' => '980000001122',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('party.phone', '980000001122');

        $this->assertDatabaseHas('parties', [
            'name' => 'Long Phone Party',
            'phone' => '980000001122',
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
