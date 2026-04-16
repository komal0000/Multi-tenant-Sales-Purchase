<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Party;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPurchaseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_summary_contains_generalized_and_item_wise_rows(): void
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

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

        Sale::query()->create([
            'party_id' => $party->id,
            'total' => 700,
            'status' => Sale::STATUS_CANCELLED,
            'created_at' => '2026-04-01 12:00:00',
            'updated_at' => '2026-04-01 12:00:00',
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
                    && (int) ($summary['bill_count'] ?? 0) === 1
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
        app(LedgerService::class)->ensureCompatibilitySchema();

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

        Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 550,
            'status' => Purchase::STATUS_CANCELLED,
            'created_at' => '2026-04-02 12:00:00',
            'updated_at' => '2026-04-02 12:00:00',
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
                    && (int) ($summary['bill_count'] ?? 0) === 1
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

    public function test_stock_fifo_report_calculates_running_quantity_issue_value_and_closing_layers(): void
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        /** @var User $user */
        $user = User::factory()->create();

        $item = Item::query()->create([
            'name' => 'FIFO Item',
            'qty' => 1,
            'rate' => 20,
            'cost_price' => 10,
        ]);

        ItemLedger::query()->create([
            'tenant_id' => $user->tenant_id,
            'item_id' => $item->id,
            'type' => 'in',
            'qty' => 5,
            'rate' => 10,
            'identifier' => 'opening_stock',
            'foreign_key' => $item->id,
            'created_at' => '2026-04-01 08:00:00',
        ]);

        ItemLedger::query()->create([
            'tenant_id' => $user->tenant_id,
            'item_id' => $item->id,
            'type' => 'in',
            'qty' => 3,
            'rate' => 12,
            'identifier' => 'purchase',
            'foreign_key' => 0,
            'created_at' => '2026-04-02 08:00:00',
        ]);

        ItemLedger::query()->create([
            'tenant_id' => $user->tenant_id,
            'item_id' => $item->id,
            'type' => 'out',
            'qty' => 6,
            'rate' => 0,
            'identifier' => 'sale',
            'foreign_key' => 0,
            'created_at' => '2026-04-03 08:00:00',
        ]);

        ItemLedger::query()->create([
            'tenant_id' => $user->tenant_id,
            'item_id' => $item->id,
            'type' => 'out',
            'qty' => 1,
            'rate' => 0,
            'identifier' => 'sale',
            'foreign_key' => 0,
            'created_at' => '2026-04-04 08:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reports.stock-ledger', [
            'item_id' => $item->id,
            'from_date_bs' => DateHelper::adToBs('2026-04-02'),
            'to_date_bs' => DateHelper::adToBs('2026-04-04'),
        ]));

        $response
            ->assertOk()
            ->assertViewHas('reportRows', function (array $rows) use ($item): bool {
                if (count($rows) !== 1) {
                    return false;
                }

                $row = $rows[0];

                return (int) $row['item_id'] === (int) $item->id
                    && (float) $row['opening_qty'] === 5.0
                    && (float) $row['opening_value'] === 50.0
                    && count($row['rows']) === 3
                    && (float) $row['rows'][1]['issue_value'] === 62.0
                    && (float) $row['rows'][2]['issue_value'] === 12.0
                    && (float) $row['closing_qty'] === 1.0
                    && (float) $row['closing_value'] === 12.0
                    && count($row['closing_layers']) === 1
                    && (float) $row['closing_layers'][0]['qty'] === 1.0
                    && (float) $row['closing_layers'][0]['rate'] === 12.0;
            });
    }
}
