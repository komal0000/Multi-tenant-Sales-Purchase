<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemLedger;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
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

    public function test_payment_store_honors_standalone_direction_and_forces_linked_bill_direction(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Direction Party',
            'phone' => null,
        ]);

        $sale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 1000,
        ]);

        $purchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 500,
        ]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'party_id' => $party->id,
                'amount' => 125,
                'type' => 'given',
                'account_id' => $cash->id,
            ])
            ->assertRedirect();

        $standalonePayment = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame('given', $standalonePayment->type);
        $this->assertNull($standalonePayment->sale_id);
        $this->assertNull($standalonePayment->purchase_id);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'party_id' => $party->id,
                'amount' => 200,
                'type' => 'given',
                'account_id' => $cash->id,
                'sale_id' => $sale->id,
            ])
            ->assertRedirect();

        $salePayment = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame('received', $salePayment->type);
        $this->assertSame($sale->id, $salePayment->sale_id);
        $this->assertNull($salePayment->purchase_id);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'party_id' => $party->id,
                'amount' => 75,
                'type' => 'received',
                'account_id' => $cash->id,
                'purchase_id' => $purchase->id,
            ])
            ->assertRedirect();

        $purchasePayment = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame('given', $purchasePayment->type);
        $this->assertSame($purchase->id, $purchasePayment->purchase_id);
        $this->assertNull($purchasePayment->sale_id);
    }

    public function test_sale_store_allows_overpayment_and_tracks_extra_as_party_advance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Overpaid Sale Party',
            'phone' => null,
        ]);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'party_id' => $party->id,
                'date_bs' => DateHelper::getCurrentBS(),
                'items' => [
                    [
                        'line_type' => 'general',
                        'description' => 'General sale line',
                        'qty' => '1',
                        'rate' => '100',
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $cash->id,
                        'amount' => '150',
                    ],
                ],
            ])
            ->assertRedirect(route('sales.create'));

        $sale = Sale::query()->latest('id')->with('payments')->firstOrFail();

        $this->assertEqualsWithDelta(100.0, (float) $sale->total, 0.01);
        $this->assertCount(1, $sale->payments);
        $this->assertEqualsWithDelta(150.0, (float) $sale->payments->first()->amount, 0.01);
        $this->assertSame($sale->id, $sale->payments->first()->sale_id);
        $this->assertSame(-50.0, app(LedgerService::class)->partyBalance((string) $party->id));
        $this->assertSame(150.0, app(LedgerService::class)->accountBalance((string) $cash->id));
    }

    public function test_purchase_store_allows_overpayment_and_tracks_extra_as_party_advance(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Overpaid Purchase Party',
            'phone' => null,
        ]);

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'party_id' => $party->id,
                'date_bs' => DateHelper::getCurrentBS(),
                'items' => [
                    [
                        'line_type' => 'general',
                        'description' => 'General purchase line',
                        'qty' => '1',
                        'rate' => '100',
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $cash->id,
                        'amount' => '150',
                    ],
                ],
            ])
            ->assertRedirect(route('purchases.create'));

        $purchase = Purchase::query()->latest('id')->with('payments')->firstOrFail();

        $this->assertEqualsWithDelta(100.0, (float) $purchase->total, 0.01);
        $this->assertCount(1, $purchase->payments);
        $this->assertEqualsWithDelta(150.0, (float) $purchase->payments->first()->amount, 0.01);
        $this->assertSame($purchase->id, $purchase->payments->first()->purchase_id);
        $this->assertSame(50.0, app(LedgerService::class)->partyBalance((string) $party->id));
        $this->assertSame(-150.0, app(LedgerService::class)->accountBalance((string) $cash->id));
    }

    public function test_linked_sale_payment_can_overpay_after_bill_is_settled(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Later Overpaid Sale Party',
            'phone' => null,
        ]);

        $sale = app(SaleService::class)->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Sale bill',
                    'qty' => 1,
                    'rate' => 100,
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'party_id' => $party->id,
                'amount' => 100,
                'type' => 'received',
                'account_id' => $cash->id,
                'sale_id' => $sale->id,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'party_id' => $party->id,
                'amount' => 50,
                'type' => 'received',
                'account_id' => $cash->id,
                'sale_id' => $sale->id,
            ])
            ->assertRedirect();

        $sale->refresh();
        $this->assertEqualsWithDelta(150.0, (float) $sale->payments()->sum('amount'), 0.01);
        $this->assertSame(-50.0, app(LedgerService::class)->partyBalance((string) $party->id));

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Advance Received 50.00')
            ->assertSee('Add Payment');
    }

    public function test_linked_purchase_payment_can_overpay_after_bill_is_settled(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $party = Party::query()->create([
            'name' => 'Later Overpaid Purchase Party',
            'phone' => null,
        ]);

        $purchase = app(PurchaseService::class)->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'general',
                    'description' => 'Purchase bill',
                    'qty' => 1,
                    'rate' => 100,
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'party_id' => $party->id,
                'amount' => 100,
                'type' => 'given',
                'account_id' => $cash->id,
                'purchase_id' => $purchase->id,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'party_id' => $party->id,
                'amount' => 50,
                'type' => 'given',
                'account_id' => $cash->id,
                'purchase_id' => $purchase->id,
            ])
            ->assertRedirect();

        $purchase->refresh();
        $this->assertEqualsWithDelta(150.0, (float) $purchase->payments()->sum('amount'), 0.01);
        $this->assertSame(50.0, app(LedgerService::class)->partyBalance((string) $party->id));

        $this->actingAs($user)
            ->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Advance Given 50.00')
            ->assertSee('Add Payment');
    }

    public function test_sale_create_page_shows_advance_received_footer_and_does_not_render_old_overpayment_warnings(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Advance Received')
            ->assertDontSee('Payment cannot exceed bill total.')
            ->assertDontSee('Payment total cannot exceed bill total.');
    }

    public function test_purchase_create_page_shows_advance_given_footer_and_does_not_render_old_overpayment_warnings(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $this->actingAs($user)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Advance Given')
            ->assertDontSee('Payment cannot exceed bill total.')
            ->assertDontSee('Payment total cannot exceed bill total.');
    }

    public function test_payment_delete_is_soft_delete_and_removes_ledger_entries(): void
    {
        $ledger = app(LedgerService::class);
        $paymentService = app(PaymentService::class);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

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
        $this->assertSame(2, Ledger::query()->count());

        $this->actingAs($admin)
            ->delete(route('payments.destroy', $payment))
            ->assertRedirect(route('payments.index'));

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSame(0.0, $ledger->partyBalance($party->id));
        $this->assertSame(0.0, $ledger->accountBalance($cash->id));
        $this->assertSame(0, Ledger::query()->count());
    }

    public function test_sale_delete_marks_bill_cancelled_and_removes_linked_ledgers(): void
    {
        $ledger = app(LedgerService::class);
        $saleService = app(SaleService::class);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $item = Item::query()->create([
            'name' => 'Delete Sale Item',
            'qty' => 10,
            'rate' => 500,
            'cost_price' => 250,
        ]);

        $party = Party::query()->create([
            'name' => 'Delete Sale Party',
            'phone' => null,
        ]);

        $sale = $saleService->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'item',
                    'item_id' => $item->id,
                    'qty' => 2,
                    'rate' => 500,
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
        $item->refresh();
        $this->assertEqualsWithDelta(8.0, (float) $item->qty, 0.0001);
        $this->assertSame(2, ItemLedger::query()->count());

        $this->actingAs($admin)
            ->delete(route('sales.destroy', $sale))
            ->assertRedirect(route('sales.index'));

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => Sale::STATUS_CANCELLED,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('payments', ['id' => $paymentId]);
        $this->assertSame(0.0, $ledger->partyBalance($party->id));
        $this->assertSame(0.0, $ledger->accountBalance($cash->id));
        $this->assertSame(0, Ledger::query()->count());
        $this->assertSame(1, ItemLedger::query()->count());

        $item->refresh();
        $this->assertEqualsWithDelta(10.0, (float) $item->qty, 0.0001);
    }

    public function test_purchase_delete_marks_bill_cancelled_and_removes_linked_ledgers(): void
    {
        $ledger = app(LedgerService::class);
        $purchaseService = app(PurchaseService::class);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        $item = Item::query()->create([
            'name' => 'Delete Purchase Item',
            'qty' => 3,
            'rate' => 350,
            'cost_price' => 150,
        ]);

        $party = Party::query()->create([
            'name' => 'Delete Purchase Party',
            'phone' => null,
        ]);

        $purchase = $purchaseService->create([
            'party_id' => $party->id,
            'items' => [
                [
                    'line_type' => 'item',
                    'item_id' => $item->id,
                    'qty' => 2,
                    'rate' => 250,
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
        $item->refresh();
        $this->assertEqualsWithDelta(5.0, (float) $item->qty, 0.0001);
        $this->assertSame(2, ItemLedger::query()->count());

        $this->actingAs($admin)
            ->delete(route('purchases.destroy', $purchase))
            ->assertRedirect(route('purchases.index'));

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => Purchase::STATUS_CANCELLED,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('payments', ['id' => $paymentId]);
        $this->assertSame(0.0, $ledger->partyBalance($party->id));
        $this->assertSame(0.0, $ledger->accountBalance($cash->id));
        $this->assertSame(0, Ledger::query()->count());
        $this->assertSame(1, ItemLedger::query()->count());

        $item->refresh();
        $this->assertEqualsWithDelta(3.0, (float) $item->qty, 0.0001);
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

    public function test_item_qty_cache_matches_net_item_ledger_after_cancellations(): void
    {
        $saleService = app(SaleService::class);
        $purchaseService = app(PurchaseService::class);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 0]);

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

        $this->actingAs($admin)
            ->delete(route('sales.destroy', $sale))
            ->assertRedirect(route('sales.index'));

        $item->refresh();
        $this->assertEqualsWithDelta(7.0, (float) $item->qty, 0.0001);

        $this->actingAs($admin)
            ->delete(route('purchases.destroy', $purchase))
            ->assertRedirect(route('purchases.index'));

        $item->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $item->qty, 0.0001);

        $netMovement = ItemLedger::query()
            ->where('item_id', $item->id)
            ->get()
            ->reduce(fn (float $carry, ItemLedger $entry) => $carry + (($entry->type === 'in' ? 1 : -1) * (float) $entry->qty), 0.0);

        $this->assertEqualsWithDelta($netMovement, (float) $item->qty, 0.0001);
        $this->assertSame(0, ItemLedger::query()->count());
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
        $this->assertSame(2, Ledger::query()->where('type', 'opening_balance')->count());
    }

    public function test_dashboard_totals_exclude_employee_linked_party_balances(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $customerParty = Party::query()->create([
            'name' => 'Customer Party',
            'phone' => null,
            'opening_balance' => 200,
            'opening_balance_side' => 'dr',
        ]);

        $employeeParty = Party::query()->create([
            'name' => 'Employee Party',
            'phone' => null,
        ]);

        Employee::query()->create([
            'party_id' => $employeeParty->id,
            'salary' => 5000,
        ]);

        $cash = Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        app(PaymentService::class)->create([
            'party_id' => $employeeParty->id,
            'amount' => 5000,
            'type' => 'given',
            'account_id' => $cash->id,
            'sale_id' => null,
            'purchase_id' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalReceivable', 200.0);
        $response->assertViewHas('totalPayable', 0.0);
        $this->assertSame(5000.0, app(LedgerService::class)->partyBalance((string) $employeeParty->id));
        $this->assertSame(200.0, app(LedgerService::class)->partyBalance((string) $customerParty->id));
    }

    public function test_sales_index_hides_cancelled_sales_unless_requested(): void
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Sales Filter Party',
            'phone' => null,
        ]);

        $activeSale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 100,
            'status' => Sale::STATUS_ACTIVE,
            'created_at' => '2026-04-03 10:00:00',
            'updated_at' => '2026-04-03 10:00:00',
        ]);

        $cancelledSale = Sale::query()->create([
            'party_id' => $party->id,
            'total' => 200,
            'status' => Sale::STATUS_CANCELLED,
            'created_at' => '2026-04-03 12:00:00',
            'updated_at' => '2026-04-03 12:00:00',
        ]);

        $dateBs = DateHelper::adToBs('2026-04-03');

        $defaultResponse = $this->actingAs($user)->get(route('sales.index', [
            'from_date_bs' => $dateBs,
            'to_date_bs' => $dateBs,
        ]));

        $defaultResponse
            ->assertOk()
            ->assertViewHas('sales', function ($sales) use ($activeSale, $cancelledSale): bool {
                $ids = $sales->getCollection()->pluck('id')->all();

                return in_array($activeSale->id, $ids, true)
                    && ! in_array($cancelledSale->id, $ids, true);
            });

        $withCancelledResponse = $this->actingAs($user)->get(route('sales.index', [
            'from_date_bs' => $dateBs,
            'to_date_bs' => $dateBs,
            'show_cancelled' => 1,
        ]));

        $withCancelledResponse
            ->assertOk()
            ->assertViewHas('sales', function ($sales) use ($activeSale, $cancelledSale): bool {
                $ids = $sales->getCollection()->pluck('id')->all();

                return in_array($activeSale->id, $ids, true)
                    && in_array($cancelledSale->id, $ids, true);
            });
    }

    public function test_purchases_index_hides_cancelled_purchases_unless_requested(): void
    {
        app(LedgerService::class)->ensureCompatibilitySchema();

        /** @var User $user */
        $user = User::factory()->create();

        $party = Party::query()->create([
            'name' => 'Purchases Filter Party',
            'phone' => null,
        ]);

        $activePurchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 150,
            'status' => Purchase::STATUS_ACTIVE,
            'created_at' => '2026-04-04 10:00:00',
            'updated_at' => '2026-04-04 10:00:00',
        ]);

        $cancelledPurchase = Purchase::query()->create([
            'party_id' => $party->id,
            'total' => 250,
            'status' => Purchase::STATUS_CANCELLED,
            'created_at' => '2026-04-04 12:00:00',
            'updated_at' => '2026-04-04 12:00:00',
        ]);

        $dateBs = DateHelper::adToBs('2026-04-04');

        $defaultResponse = $this->actingAs($user)->get(route('purchases.index', [
            'from_date_bs' => $dateBs,
            'to_date_bs' => $dateBs,
        ]));

        $defaultResponse
            ->assertOk()
            ->assertViewHas('purchases', function ($purchases) use ($activePurchase, $cancelledPurchase): bool {
                $ids = $purchases->getCollection()->pluck('id')->all();

                return in_array($activePurchase->id, $ids, true)
                    && ! in_array($cancelledPurchase->id, $ids, true);
            });

        $withCancelledResponse = $this->actingAs($user)->get(route('purchases.index', [
            'from_date_bs' => $dateBs,
            'to_date_bs' => $dateBs,
            'show_cancelled' => 1,
        ]));

        $withCancelledResponse
            ->assertOk()
            ->assertViewHas('purchases', function ($purchases) use ($activePurchase, $cancelledPurchase): bool {
                $ids = $purchases->getCollection()->pluck('id')->all();

                return in_array($activePurchase->id, $ids, true)
                    && in_array($cancelledPurchase->id, $ids, true);
            });
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
