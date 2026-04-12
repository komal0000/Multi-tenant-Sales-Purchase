<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class LedgerFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_ledger_flows_balance_correctly(): void
    {
        $ledger = app(LedgerService::class);
        $saleService = app(SaleService::class);
        $purchaseService = app(PurchaseService::class);
        $paymentService = app(PaymentService::class);

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $ram = Party::query()->create([
            'name' => 'Ram Traders',
            'phone' => null,
        ]);

        $sita = Party::query()->create([
            'name' => 'Sita Suppliers',
            'phone' => null,
        ]);

        $saleService->create([
            'party_id' => $ram->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Goods',
                    'qty' => 1,
                    'rate' => 1000,
                ],
            ],
        ]);

        $this->assertSame(1000.0, $ledger->partyBalance($ram->id));

        $paymentService->create([
            'party_id' => $ram->id,
            'amount' => 400,
            'type' => 'received',
            'account_id' => $cash->id,
            'sale_id' => null,
            'purchase_id' => null,
        ]);

        $this->assertSame(600.0, $ledger->partyBalance($ram->id));
        $this->assertSame(400.0, $ledger->accountBalance($cash->id));

        $purchaseService->create([
            'party_id' => $sita->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Materials',
                    'qty' => 1,
                    'rate' => 500,
                ],
            ],
        ]);

        $this->assertSame(-500.0, $ledger->partyBalance($sita->id));

        $paymentService->create([
            'party_id' => $sita->id,
            'amount' => 200,
            'type' => 'given',
            'account_id' => $cash->id,
            'sale_id' => null,
            'purchase_id' => null,
        ]);

        $this->assertSame(-300.0, $ledger->partyBalance($sita->id));
        $this->assertSame(200.0, $ledger->accountBalance($cash->id));
    }

    public function test_payment_delete_is_soft_delete_and_reverses_ledger(): void
    {
        $ledger = app(LedgerService::class);
        $paymentService = app(PaymentService::class);

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Ram Traders',
            'phone' => null,
        ]);

        $payment = $paymentService->create([
            'party_id' => $party->id,
            'amount' => 400,
            'type' => 'received',
            'account_id' => $cash->id,
            'sale_id' => null,
            'purchase_id' => null,
        ]);

        $this->assertSame(-400.0, $ledger->partyBalance($party->id));
        $this->assertSame(400.0, $ledger->accountBalance($cash->id));

        $paymentService->delete($payment);

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSame(0.0, $ledger->partyBalance($party->id));
        $this->assertSame(0.0, $ledger->accountBalance($cash->id));
        $this->assertSame(4, Ledger::query()->count());
    }

    public function test_sale_delete_reverses_sale_and_linked_payments(): void
    {
        $ledger = app(LedgerService::class);
        $saleService = app(SaleService::class);

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Delete Sale Party',
            'phone' => null,
        ]);

        $sale = $saleService->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Goods',
                    'qty' => 1,
                    'rate' => 1000,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $cash->id,
                    'amount' => 400,
                    'cheque_number' => null,
                ],
            ],
        ]);

        $paymentId = $sale->payments->first()->id;

        $this->assertSame(600.0, $ledger->partyBalance($party->id));
        $this->assertSame(400.0, $ledger->accountBalance($cash->id));

        $saleService->delete($sale);

        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
        $this->assertSoftDeleted('payments', ['id' => $paymentId]);
        $this->assertSame(0.0, $ledger->partyBalance($party->id));
        $this->assertSame(0.0, $ledger->accountBalance($cash->id));
        $this->assertSame(6, Ledger::query()->count());
    }

    public function test_purchase_delete_reverses_purchase_and_linked_payments(): void
    {
        $ledger = app(LedgerService::class);
        $purchaseService = app(PurchaseService::class);

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Delete Purchase Party',
            'phone' => null,
        ]);

        $purchase = $purchaseService->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Raw Material',
                    'qty' => 1,
                    'rate' => 500,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $cash->id,
                    'amount' => 200,
                    'cheque_number' => null,
                ],
            ],
        ]);

        $paymentId = $purchase->payments->first()->id;

        $this->assertSame(-300.0, $ledger->partyBalance($party->id));
        $this->assertSame(-200.0, $ledger->accountBalance($cash->id));

        $purchaseService->delete($purchase);

        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
        $this->assertSoftDeleted('payments', ['id' => $paymentId]);
        $this->assertSame(0.0, $ledger->partyBalance($party->id));
        $this->assertSame(0.0, $ledger->accountBalance($cash->id));
        $this->assertSame(6, Ledger::query()->count());
    }

    public function test_item_lines_create_item_ledger_entries_using_cost_price_rate(): void
    {
        $saleService = app(SaleService::class);
        $purchaseService = app(PurchaseService::class);

        $item = Item::query()->create([
            'name' => 'Tracked Item',
            'qty' => 0,
            'rate' => 150,
            'cost_price' => 80,
        ]);

        $supplier = Party::query()->create([
            'name' => 'Stock Supplier',
            'phone' => null,
        ]);

        $customer = Party::query()->create([
            'name' => 'Stock Customer',
            'phone' => null,
        ]);

        $purchase = $purchaseService->create([
            'party_id' => $supplier->id,
            'items' => [
                [
                    'line_type' => 'item',
                    'item_id' => $item->id,
                    'qty' => 5,
                    'rate' => 90,
                ],
            ],
            'payments' => [],
        ]);

        $purchaseLine = $purchase->items->first();
        $this->assertNotNull($purchaseLine);

        $purchaseLedger = ItemLedger::query()
            ->where('item_id', $item->id)
            ->where('type', 'in')
            ->latest('id')
            ->first();

        $this->assertNotNull($purchaseLedger);
        $this->assertSame($purchaseLine->id, $purchaseLedger->foreign_key);
        $this->assertEqualsWithDelta(90.0, (float) $purchaseLedger->rate, 0.0001);

        $item->refresh();
        $this->assertEqualsWithDelta(90.0, (float) $item->cost_price, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $item->qty, 0.0001);

        $sale = $saleService->create([
            'party_id' => $customer->id,
            'items' => [
                [
                    'line_type' => 'item',
                    'item_id' => $item->id,
                    'qty' => 2,
                    'rate' => 200,
                ],
            ],
            'payments' => [],
        ]);

        $saleLine = $sale->items->first();
        $this->assertNotNull($saleLine);

        $saleLedger = ItemLedger::query()
            ->where('item_id', $item->id)
            ->where('type', 'out')
            ->latest('id')
            ->first();

        $this->assertNotNull($saleLedger);
        $this->assertSame($saleLine->id, $saleLedger->foreign_key);
        $this->assertEqualsWithDelta(90.0, (float) $saleLedger->rate, 0.0001);

        $item->refresh();
        $this->assertEqualsWithDelta(3.0, (float) $item->qty, 0.0001);
    }

    public function test_general_and_expense_lines_do_not_create_item_ledger_entries(): void
    {
        $saleService = app(SaleService::class);
        $purchaseService = app(PurchaseService::class);

        $saleParty = Party::query()->create([
            'name' => 'Service Customer',
            'phone' => null,
        ]);

        $purchaseParty = Party::query()->create([
            'name' => 'Service Vendor',
            'phone' => null,
        ]);

        $expenseCategory = ExpenseCategory::query()->create([
            'name' => 'Office Expense',
            'parent_id' => null,
        ]);

        $saleService->create([
            'party_id' => $saleParty->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Consulting Service',
                    'qty' => 1,
                    'rate' => 1200,
                ],
            ],
            'payments' => [],
        ]);

        $purchaseService->create([
            'party_id' => $purchaseParty->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Transport Charge',
                    'qty' => 1,
                    'rate' => 250,
                ],
                [
                    'line_type' => 'expense',
                    'expense_category_id' => $expenseCategory->id,
                    'qty' => 1,
                    'rate' => 450,
                ],
            ],
            'payments' => [],
        ]);

        $this->assertSame(0, ItemLedger::query()->count());
    }

    public function test_sale_expense_line_is_rejected_by_constraints(): void
    {
        $saleService = app(SaleService::class);

        $party = Party::query()->create([
            'name' => 'Invalid Expense Sale Party',
            'phone' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $saleService->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'expense',
                    'description' => 'Should Fail',
                    'qty' => 1,
                    'rate' => 100,
                ],
            ],
            'payments' => [],
        ]);
    }

    public function test_purchase_expense_line_is_accepted_without_stock_movement(): void
    {
        $purchaseService = app(PurchaseService::class);

        $party = Party::query()->create([
            'name' => 'Expense Purchase Party',
            'phone' => null,
        ]);

        $expenseCategory = ExpenseCategory::query()->create([
            'name' => 'Rent',
            'parent_id' => null,
        ]);

        $purchase = $purchaseService->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'expense',
                    'expense_category_id' => $expenseCategory->id,
                    'description' => 'Office Rent',
                    'qty' => 1,
                    'rate' => 800,
                ],
            ],
            'payments' => [],
        ]);

        $line = $purchase->items->first();
        $this->assertNotNull($line);
        $this->assertSame('expense', $line->line_type);
        $this->assertSame($expenseCategory->id, $line->expense_category_id);
        $this->assertSame(0, ItemLedger::query()->count());
    }

    public function test_item_qty_cache_matches_net_item_ledger_after_reversals(): void
    {
        $saleService = app(SaleService::class);
        $purchaseService = app(PurchaseService::class);

        $item = Item::query()->create([
            'name' => 'Reversal Item',
            'qty' => 0,
            'rate' => 140,
            'cost_price' => 70,
        ]);

        $supplier = Party::query()->create([
            'name' => 'Reversal Supplier',
            'phone' => null,
        ]);

        $customer = Party::query()->create([
            'name' => 'Reversal Customer',
            'phone' => null,
        ]);

        $purchase = $purchaseService->create([
            'party_id' => $supplier->id,
            'items' => [
                [
                    'line_type' => 'item',
                    'item_id' => $item->id,
                    'qty' => 7,
                    'rate' => 75,
                ],
            ],
            'payments' => [],
        ]);

        $sale = $saleService->create([
            'party_id' => $customer->id,
            'items' => [
                [
                    'line_type' => 'item',
                    'item_id' => $item->id,
                    'qty' => 3,
                    'rate' => 160,
                ],
            ],
            'payments' => [],
        ]);

        $item->refresh();
        $this->assertEqualsWithDelta(4.0, (float) $item->qty, 0.0001);

        $saleService->delete($sale);
        $item->refresh();
        $this->assertEqualsWithDelta(7.0, (float) $item->qty, 0.0001);

        $purchaseService->delete($purchase);
        $item->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $item->qty, 0.0001);

        $netMovement = ItemLedger::query()
            ->where('item_id', $item->id)
            ->get()
            ->reduce(fn (float $carry, ItemLedger $entry) => $carry + (($entry->type === 'in' ? 1 : -1) * (float) $entry->qty), 0.0);

        $this->assertEqualsWithDelta($netMovement, (float) $item->qty, 0.0001);
    }

    public function test_dashboard_totals_include_opening_balances(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Party::query()->create([
            'name' => 'Receivable Party',
            'phone' => null,
            'opening_balance' => 200,
            'opening_balance_side' => 'dr',
        ]);

        Party::query()->create([
            'name' => 'Payable Party',
            'phone' => null,
            'opening_balance' => 300,
            'opening_balance_side' => 'cr',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalReceivable', 200.0);
        $response->assertViewHas('totalPayable', 300.0);
    }

    public function test_ledger_entries_cannot_be_updated_or_deleted(): void
    {
        $entry = Ledger::query()->create([
            'party_id' => Party::query()->create([
                'name' => 'Immutable Party',
                'phone' => null,
            ])->id,
            'account_id' => null,
            'dr_amount' => 100,
            'cr_amount' => 0,
            'type' => 'sale',
            'ref_id' => 111111,
            'ref_table' => 'sales',
        ]);

        $this->expectException(LogicException::class);
        $entry->update(['dr_amount' => 200]);
    }

    public function test_ledger_entries_cannot_be_deleted(): void
    {
        $entry = Ledger::query()->create([
            'party_id' => Party::query()->create([
                'name' => 'Protected Party',
                'phone' => null,
            ])->id,
            'account_id' => null,
            'dr_amount' => 100,
            'cr_amount' => 0,
            'type' => 'sale',
            'ref_id' => 222222,
            'ref_table' => 'sales',
        ]);

        $this->expectException(LogicException::class);
        $entry->delete();
    }
}
