<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_delete_financial_records(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 1]);

        $party = Party::query()->create([
            'name' => 'Protected Party',
            'phone' => null,
        ]);

        $account = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $sale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 100,
        ]);

        $purchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 100,
        ]);

        $payment = Payment::query()->create([
            'party_id' => $party->id,
            'amount' => 50,
            'type' => 'received',
            'account_id' => $account->id,
            'sale_id' => null,
            'purchase_id' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('parties.destroy', $party))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('sales.destroy', $sale))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('purchases.destroy', $purchase))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('payments.destroy', $payment))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $account))
            ->assertForbidden();
    }

    public function test_admin_can_delete_party(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $party = Party::query()->create([
            'name' => 'Delete Allowed Party',
            'phone' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('parties.destroy', $party))
            ->assertRedirect(route('parties.index'));

        $this->assertDatabaseMissing('parties', [
            'id' => $party->id,
        ]);
    }
}
