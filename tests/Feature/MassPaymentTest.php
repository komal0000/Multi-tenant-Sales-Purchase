<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\LedgerService;
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
            ->assertSee('Mass Payment');
    }

    public function test_unified_mass_payment_page_renders(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('payments.mass.create'))
            ->assertOk()
            ->assertSee('Mass Payment')
            ->assertSee('Total Entries');
    }

    public function test_mass_payment_load_returns_standalone_and_linked_rows_with_read_only_flags(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create(['name' => 'Mass Party', 'phone' => null]);
        $cash = Account::query()->create(['name' => 'Cash', 'type' => 'cash']);
        $date = '2026-04-10 09:30:00';

        $standalone = Payment::query()->create([
            'party_id' => $party->id,
            'account_id' => $cash->id,
            'amount' => 1200,
            'type' => 'received',
            'notes' => 'Standalone row',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $sale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 500,
            'status' => Sale::STATUS_ACTIVE,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        Payment::query()->create([
            'party_id' => $party->id,
            'account_id' => $cash->id,
            'amount' => 200,
            'type' => 'received',
            'sale_id' => $sale->id,
            'notes' => 'Linked row',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('payments.mass.load', [
                'date_bs' => DateHelper::adToBs('2026-04-10'),
            ]));

        $response->assertOk();
        $rows = $response->json('rows');

        $this->assertCount(2, $rows);
        $this->assertSame((string) $standalone->id, (string) collect($rows)->firstWhere('id', $standalone->id)['id']);
        $this->assertFalse((bool) collect($rows)->firstWhere('id', $standalone->id)['is_linked']);
        $this->assertTrue((bool) collect($rows)->firstWhere('linked_label', 'Sale #'.$sale->id.' / 500.00')['is_linked']);
    }

    public function test_mass_payment_store_creates_standalone_row_with_selected_date_notes_and_ledgers(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create(['name' => 'Mass Party A', 'phone' => null]);
        $cash = Account::query()->create(['name' => 'Cash', 'type' => 'cash']);
        $dateBs = DateHelper::adToBs('2026-04-11');

        $response = $this->actingAs($user)
            ->postJson(route('payments.mass.rows.store'), [
                'date_bs' => $dateBs,
                'type' => 'received',
                'party_id' => $party->id,
                'account_id' => $cash->id,
                'amount' => '450.50',
                'notes' => 'First note',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('payments', [
            'party_id' => $party->id,
            'account_id' => $cash->id,
            'amount' => 450.50,
            'type' => 'received',
            'notes' => 'First note',
            'date' => DateHelper::toDateInt($dateBs),
        ]);
        $this->assertSame(2, Ledger::query()->count());
        $this->assertSame(1, Payment::query()->where('date', DateHelper::toDateInt($dateBs))->count());
    }

    public function test_mass_payment_update_changes_standalone_row_and_keeps_ledgers_in_sync(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create(['name' => 'Update Party', 'phone' => null]);
        $otherParty = Party::query()->create(['name' => 'Other Party', 'phone' => null]);
        $cash = Account::query()->create(['name' => 'Cash', 'type' => 'cash']);
        $bank = Account::query()->create(['name' => 'Bank', 'type' => 'bank']);

        $payment = Payment::query()->create([
            'party_id' => $party->id,
            'account_id' => $cash->id,
            'amount' => 100,
            'type' => 'received',
            'notes' => 'Before',
            'created_at' => '2026-04-12 10:00:00',
            'updated_at' => '2026-04-12 10:00:00',
        ]);

        app(LedgerService::class)->recordPayment($payment);

        $this->actingAs($user)
            ->patchJson(route('payments.mass.rows.update', $payment), [
                'date_bs' => DateHelper::adToBs('2026-04-12'),
                'type' => 'given',
                'party_id' => $otherParty->id,
                'account_id' => $bank->id,
                'amount' => '150.00',
                'notes' => 'Updated',
            ])
            ->assertOk();

        $payment->refresh();
        $this->assertSame('given', $payment->type);
        $this->assertSame($otherParty->id, $payment->party_id);
        $this->assertSame($bank->id, $payment->account_id);
        $this->assertSame('Updated', $payment->notes);
        $this->assertSame(2, Ledger::query()->count());
    }

    public function test_mass_payment_delete_removes_standalone_row_and_ledgers(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $party = Party::query()->create(['name' => 'Delete Party', 'phone' => null]);
        $cash = Account::query()->create(['name' => 'Cash', 'type' => 'cash']);

        $payment = Payment::query()->create([
            'party_id' => $party->id,
            'account_id' => $cash->id,
            'amount' => 200,
            'type' => 'received',
            'created_at' => '2026-04-13 11:00:00',
            'updated_at' => '2026-04-13 11:00:00',
        ]);

        app(LedgerService::class)->recordPayment($payment);

        $this->actingAs($admin)
            ->deleteJson(route('payments.mass.rows.destroy', $payment))
            ->assertOk();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSame(0, Ledger::query()->count());
    }

    public function test_linked_bill_rows_cannot_be_updated_or_deleted_from_mass_payment(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $party = Party::query()->create(['name' => 'Linked Party', 'phone' => null]);
        $cash = Account::query()->create(['name' => 'Cash', 'type' => 'cash']);
        $purchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 300,
        ]);

        $payment = Payment::query()->create([
            'party_id' => $party->id,
            'account_id' => $cash->id,
            'amount' => 150,
            'type' => 'given',
            'purchase_id' => $purchase->id,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('payments.mass.rows.update', $payment), [
                'date_bs' => DateHelper::getCurrentBS(),
                'type' => 'given',
                'party_id' => $party->id,
                'account_id' => $cash->id,
                'amount' => '175.00',
                'notes' => 'Blocked',
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->deleteJson(route('payments.mass.rows.destroy', $payment))
            ->assertStatus(422);
    }

    public function test_payment_entry_screens_show_account_notice_when_no_account_exists(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('payments.create'))
            ->assertOk()
            ->assertSee('No cash or bank account is available yet.');

        $this->actingAs($user)->get(route('payments.mass.create'))
            ->assertOk()
            ->assertSee('No cash or bank account is available yet.');
    }
}
