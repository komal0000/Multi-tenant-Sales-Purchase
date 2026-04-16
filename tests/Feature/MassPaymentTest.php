<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_index_shows_mass_payment_actions(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Mass Received')
            ->assertSee('Mass Given');
    }

    public function test_mass_payment_pages_render_with_fixed_direction_context(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('payments.mass-received.create'))
            ->assertOk()
            ->assertSee('Mass Received')
            ->assertSee('Total Payment Amount');

        $this->actingAs($user)
            ->get(route('payments.mass-given.create'))
            ->assertOk()
            ->assertSee('Mass Given')
            ->assertSee('Total Entries');
    }

    public function test_mass_received_store_creates_multiple_payments_with_selected_date_notes_and_ledgers(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $partyA = Party::query()->create(['name' => 'Mass Party A', 'phone' => null]);
        $partyB = Party::query()->create(['name' => 'Mass Party B', 'phone' => null]);
        $cash = Account::query()->create(['name' => 'Cash', 'type' => 'cash']);
        $bank = Account::query()->create(['name' => 'Bank', 'type' => 'bank']);

        $dateBs = DateHelper::adToBs('2026-04-10');

        $this->actingAs($user)
            ->post(route('payments.mass-received.store'), [
                'date_bs' => $dateBs,
                'rows' => [
                    [
                        'party_id' => $partyA->id,
                        'account_id' => $cash->id,
                        'amount' => '1200.00',
                        'notes' => 'First note',
                    ],
                    [
                        'party_id' => $partyB->id,
                        'account_id' => $bank->id,
                        'amount' => '450.50',
                        'notes' => 'Second note',
                    ],
                ],
            ])
            ->assertRedirect(route('payments.index'));

        $this->assertSame(2, Payment::query()->count());
        $this->assertDatabaseHas('payments', [
            'party_id' => $partyA->id,
            'account_id' => $cash->id,
            'amount' => 1200.00,
            'type' => 'received',
            'notes' => 'First note',
        ]);
        $this->assertDatabaseHas('payments', [
            'party_id' => $partyB->id,
            'account_id' => $bank->id,
            'amount' => 450.50,
            'type' => 'received',
            'notes' => 'Second note',
        ]);

        $this->assertSame(4, Ledger::query()->count());
        $this->assertSame(2, Payment::query()->whereDate('created_at', '2026-04-10')->count());
    }

    public function test_mass_given_store_ignores_blank_rows_and_sets_given_type(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create(['name' => 'Mass Party', 'phone' => null]);
        $cash = Account::query()->create(['name' => 'Cash', 'type' => 'cash']);

        $this->actingAs($user)
            ->post(route('payments.mass-given.store'), [
                'date_bs' => DateHelper::getCurrentBS(),
                'rows' => [
                    [
                        'party_id' => $party->id,
                        'account_id' => $cash->id,
                        'amount' => '100.00',
                        'notes' => 'Paid',
                    ],
                    [
                        'party_id' => '',
                        'account_id' => '',
                        'amount' => '',
                        'notes' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('payments.index'));

        $this->assertSame(1, Payment::query()->count());
        $this->assertDatabaseHas('payments', [
            'party_id' => $party->id,
            'type' => 'given',
            'notes' => 'Paid',
        ]);
    }

    public function test_mass_payment_store_validates_rows_and_tenant_scoped_ids(): void
    {
        $defaultTenant = Tenant::query()->where('code', 'default')->firstOrFail();
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'code' => 'other-mass-payment',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
        ]);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $defaultTenant->id]);

        $otherParty = Party::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Party',
            'phone' => null,
        ]);

        $otherAccount = Account::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Cash',
            'type' => 'cash',
        ]);

        $this->actingAs($user)
            ->post(route('payments.mass-received.store'), [
                'date_bs' => DateHelper::getCurrentBS(),
                'rows' => [
                    [
                        'party_id' => $otherParty->id,
                        'account_id' => $otherAccount->id,
                        'amount' => '0',
                        'notes' => str_repeat('x', 300),
                    ],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['rows.0.party_id', 'rows.0.account_id', 'rows.0.amount', 'rows.0.notes']);
    }
}
