<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class BillLinkedPaymentsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_show_paginate_linked_payments(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Sale Party',
            'phone' => null,
        ]);

        $account = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $sale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 1000,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            Payment::query()->create([
                'party_id' => $party->id,
                'amount' => 10,
                'type' => 'received',
                'account_id' => $account->id,
                'sale_id' => $sale->id,
                'purchase_id' => null,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('sales.show', $sale));

        $response->assertOk();
        $response->assertViewHas('linkedPayments', function ($linkedPayments) {
            return $linkedPayments instanceof LengthAwarePaginator
                && $linkedPayments->total() === 25
                && $linkedPayments->perPage() === 20;
        });
    }

    public function test_purchase_show_paginate_linked_payments(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Purchase Party',
            'phone' => null,
        ]);

        $account = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $purchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 1000,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            Payment::query()->create([
                'party_id' => $party->id,
                'amount' => 10,
                'type' => 'given',
                'account_id' => $account->id,
                'sale_id' => null,
                'purchase_id' => $purchase->id,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('purchases.show', $purchase));

        $response->assertOk();
        $response->assertViewHas('linkedPayments', function ($linkedPayments) {
            return $linkedPayments instanceof LengthAwarePaginator
                && $linkedPayments->total() === 25
                && $linkedPayments->perPage() === 20;
        });
    }
}
