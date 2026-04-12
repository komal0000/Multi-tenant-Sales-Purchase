<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BsDateDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_purchase_and_payment_filters_default_to_current_bs_date(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $todayBs = DateHelper::getCurrentBS();

        foreach (['sales.index', 'purchases.index', 'payments.index'] as $routeName) {
            $response = $this->actingAs($user)->get(route($routeName));

            $response
                ->assertOk()
                ->assertViewHas('filters', function (array $filters) use ($todayBs): bool {
                    return ($filters['from_date_bs'] ?? null) === $todayBs
                        && ($filters['to_date_bs'] ?? null) === $todayBs;
                });
        }
    }
}
