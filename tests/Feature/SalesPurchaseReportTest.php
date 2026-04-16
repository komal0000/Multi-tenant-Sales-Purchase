<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\ExpenseCategory;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPurchaseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_summary_contains_generalized_and_item_wise_rows(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Report Party',
            'phone' => '9800000101',
        ]);

        $sale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 300,
            'created_at' => '2026-04-01 10:00:00',
            'updated_at' => '2026-04-01 10:00:00',
        ]);

        $sale->items()->create([
            'bill_type' => 'sale',
            'line_type' => 'item',
            'item_id' => null,
            'description' => 'Fallback Item Label',
            'qty' => 2,
            'rate' => 100,
        ]);

        $sale->items()->create([
            'bill_type' => 'sale',
            'line_type' => 'general',
            'item_id' => null,
            'description' => 'Transport Charge',
            'qty' => 1,
            'rate' => 100,
        ]);

        $dateBs = DateHelper::adToBs('2026-04-01');

        $response = $this->actingAs($user)->get(route('reports.sales', [
            'from_date_bs' => $dateBs,
            'to_date_bs' => $dateBs,
        ]));

        $response
            ->assertOk()
            ->assertViewHas('summary', function (array $summary): bool {
                return (float) ($summary['total_amount'] ?? 0) === 300.0
                    && count($summary['generalized'] ?? []) > 0
                    && count($summary['item_wise'] ?? []) > 0;
            })
            ->assertViewHas('dateWiseRows', function (array $rows): bool {
                return count($rows) > 0
                    && count($rows[0]['generalized'] ?? []) > 0
                    && count($rows[0]['item_wise'] ?? []) > 0;
            });
    }

    public function test_purchase_report_contains_expense_category_breakdowns_in_all_tabs(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Purchase Party',
            'phone' => '9800000102',
        ]);

        $expenseCategory = ExpenseCategory::query()->create([
            'name' => 'Transportation',
            ExpenseCategory::parentColumn() => null,
        ]);

        $purchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 450,
            'created_at' => '2026-04-02 11:00:00',
            'updated_at' => '2026-04-02 11:00:00',
        ]);

        $purchase->items()->create([
            'bill_type' => 'purchase',
            'line_type' => 'general',
            'item_id' => null,
            'description' => 'Packing Charge',
            'expense_category_id' => null,
            'qty' => 1,
            'rate' => 150,
        ]);

        $purchase->items()->create([
            'bill_type' => 'purchase',
            'line_type' => 'expense',
            'item_id' => null,
            'description' => null,
            'expense_category_id' => $expenseCategory->id,
            'qty' => 1,
            'rate' => 300,
        ]);

        $dateBs = DateHelper::adToBs('2026-04-02');

        $response = $this->actingAs($user)->get(route('reports.purchases', [
            'from_date_bs' => $dateBs,
            'to_date_bs' => $dateBs,
        ]));

        $response
            ->assertOk()
            ->assertViewHas('summary', function (array $summary): bool {
                return (float) ($summary['total_amount'] ?? 0) === 450.0
                    && count($summary['expense_wise'] ?? []) > 0;
            })
            ->assertViewHas('dateWiseRows', function (array $rows): bool {
                return count($rows) > 0 && count($rows[0]['expense_wise'] ?? []) > 0;
            })
            ->assertViewHas('partyWiseRows', function (array $rows): bool {
                return count($rows) > 0 && count($rows[0]['expense_wise'] ?? []) > 0;
            })
            ->assertViewHas('datePartyWiseRows', function (array $rows): bool {
                return count($rows) > 0
                    && count($rows[0]['parties'] ?? []) > 0
                    && count($rows[0]['parties'][0]['expense_wise'] ?? []) > 0;
            });
    }
}
