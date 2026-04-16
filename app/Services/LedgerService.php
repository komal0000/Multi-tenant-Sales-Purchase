<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Item;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LedgerService
{
    public function ensureCompatibilitySchema(): void
    {
        if (Schema::hasTable('sales') && Schema::hasTable('purchases') && Schema::hasTable('ledger') && Schema::hasTable('parties') && Schema::hasColumn('ledger', 'tenant_id')) {
            $this->ensureBillStatusColumns();
            $this->ensureLedgerSupportsOpeningBalance();
            $this->backfillPartyOpeningBalanceLedgerRows();
        }

        if (Schema::hasTable('items') && Schema::hasTable('item_ledgers') && Schema::hasTable('bill_line_items') && Schema::hasColumn('item_ledgers', 'tenant_id')) {
            $this->ensurePreciseRateColumns();
            $this->backfillItemOpeningStockRows();
        }

        if (Schema::hasTable('payroll_settings') && Schema::hasColumn('payroll_settings', 'tenant_id')) {
            $this->ensurePaymentSidebarLimitColumn();
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'tenant_id')) {
            $this->ensurePaymentNotesColumn();
        }
    }

    public function recordSale(Sale $sale): void
    {
        $this->ensureCompatibilitySchema();

        Ledger::query()->create([
            'party_id' => $sale->party_id,
            'account_id' => null,
            'dr_amount' => $sale->total,
            'cr_amount' => 0,
            'type' => 'sale',
            'ref_id' => $sale->id,
            'ref_table' => 'sales',
        ]);
    }

    public function reverseSale(Sale $sale): void
    {
        $this->ensureCompatibilitySchema();

        Ledger::query()->create([
            'party_id' => $sale->party_id,
            'account_id' => null,
            'dr_amount' => 0,
            'cr_amount' => $sale->total,
            'type' => 'sale',
            'ref_id' => $sale->id,
            'ref_table' => 'sales',
        ]);
    }

    public function recordPurchase(Purchase $purchase): void
    {
        $this->ensureCompatibilitySchema();

        Ledger::query()->create([
            'party_id' => $purchase->party_id,
            'account_id' => null,
            'dr_amount' => 0,
            'cr_amount' => $purchase->total,
            'type' => 'purchase',
            'ref_id' => $purchase->id,
            'ref_table' => 'purchases',
        ]);
    }

    public function reversePurchase(Purchase $purchase): void
    {
        $this->ensureCompatibilitySchema();

        Ledger::query()->create([
            'party_id' => $purchase->party_id,
            'account_id' => null,
            'dr_amount' => $purchase->total,
            'cr_amount' => 0,
            'type' => 'purchase',
            'ref_id' => $purchase->id,
            'ref_table' => 'purchases',
        ]);
    }

    public function recordPayment(Payment $payment): void
    {
        $this->ensureCompatibilitySchema();

        $isReceived = $payment->type === 'received';

        Ledger::query()->create([
            'party_id' => $payment->party_id,
            'account_id' => null,
            'dr_amount' => $isReceived ? 0 : $payment->amount,
            'cr_amount' => $isReceived ? $payment->amount : 0,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);

        Ledger::query()->create([
            'party_id' => null,
            'account_id' => $payment->account_id,
            'dr_amount' => $isReceived ? $payment->amount : 0,
            'cr_amount' => $isReceived ? 0 : $payment->amount,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);
    }

    public function reversePayment(Payment $payment): void
    {
        $this->ensureCompatibilitySchema();

        $isReceived = $payment->type === 'received';

        Ledger::query()->create([
            'party_id' => $payment->party_id,
            'account_id' => null,
            'dr_amount' => $isReceived ? $payment->amount : 0,
            'cr_amount' => $isReceived ? 0 : $payment->amount,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);

        Ledger::query()->create([
            'party_id' => null,
            'account_id' => $payment->account_id,
            'dr_amount' => $isReceived ? 0 : $payment->amount,
            'cr_amount' => $isReceived ? $payment->amount : 0,
            'type' => 'payment',
            'ref_id' => $payment->id,
            'ref_table' => 'payments',
        ]);
    }

    public function partyBalance(string $partyId): float
    {
        $this->ensureCompatibilitySchema();

        return (float) (Ledger::query()
            ->where('party_id', $partyId)
            ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->value('balance') ?? 0);
    }

    public function accountBalance(string $accountId): float
    {
        $this->ensureCompatibilitySchema();

        $ledgerBalance = (float) (Ledger::query()
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(dr_amount) - SUM(cr_amount), 0) as balance')
            ->value('balance') ?? 0);

        $account = Account::query()->find($accountId);

        return $ledgerBalance + $this->openingSigned((float) ($account?->opening_balance ?? 0), $account?->opening_balance_side ?? 'dr');
    }

    private function openingSigned(float $amount, string $side): float
    {
        return $side === 'cr' ? -$amount : $amount;
    }

    public function removeEntries(string $refTable, int|array $refIds): void
    {
        $this->ensureCompatibilitySchema();

        $ids = array_values(array_filter(is_array($refIds) ? $refIds : [$refIds]));

        if ($ids === []) {
            return;
        }

        Ledger::query()
            ->where('ref_table', $refTable)
            ->whereIn('ref_id', $ids)
            ->delete();
    }

    public function syncPartyOpeningBalance(Party $party): void
    {
        $this->ensureCompatibilitySchema();

        Ledger::query()
            ->where('type', 'opening_balance')
            ->where('ref_table', 'parties')
            ->where('ref_id', $party->id)
            ->where('party_id', $party->id)
            ->delete();

        $amount = (float) ($party->opening_balance ?? 0);

        if ($amount <= 0) {
            return;
        }

        Ledger::query()->create([
            'tenant_id' => $party->tenant_id,
            'party_id' => $party->id,
            'account_id' => null,
            'dr_amount' => ($party->opening_balance_side ?? 'dr') === 'cr' ? 0 : $amount,
            'cr_amount' => ($party->opening_balance_side ?? 'dr') === 'cr' ? $amount : 0,
            'type' => 'opening_balance',
            'ref_id' => $party->id,
            'ref_table' => 'parties',
            'created_at' => $party->created_at,
        ]);
    }

    public function syncItemOpeningStock(Item $item, float $openingQty): void
    {
        $this->ensureCompatibilitySchema();

        $openingQty = round(max(0, $openingQty), 4);

        $openingRows = DB::table('item_ledgers')
            ->where('item_id', $item->id)
            ->where('identifier', 'opening_stock')
            ->orderBy('id')
            ->get();

        $primaryRow = $openingRows->first();
        $extraIds = $openingRows->skip(1)->pluck('id')->all();

        if ($extraIds !== []) {
            DB::table('item_ledgers')->whereIn('id', $extraIds)->delete();
        }

        if ($openingQty <= 0) {
            if ($primaryRow) {
                DB::table('item_ledgers')->where('id', $primaryRow->id)->delete();
            }

            $this->syncItemQtyCache($item->id);

            return;
        }

        $payload = [
            'tenant_id' => $item->tenant_id,
            'item_id' => $item->id,
            'type' => 'in',
            'qty' => $openingQty,
            'rate' => round((float) $item->cost_price, 4),
            'identifier' => 'opening_stock',
            'foreign_key' => $item->id,
        ];

        if ($primaryRow) {
            DB::table('item_ledgers')
                ->where('id', $primaryRow->id)
                ->update($payload);
        } else {
            DB::table('item_ledgers')->insert($payload + [
                'created_at' => $item->created_at ?? now(),
            ]);
        }

        $this->syncItemQtyCache($item->id);
    }

    public function syncItemQtyCache(int|string $itemId): void
    {
        $this->ensureCompatibilitySchema();

        $balance = (float) (DB::table('item_ledgers')
            ->where('item_id', $itemId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN qty ELSE -qty END), 0) as balance")
            ->value('balance') ?? 0);

        Item::query()
            ->whereKey($itemId)
            ->update(['qty' => round($balance, 4)]);
    }

    public function attachReferenceText(Collection $ledgerRows): void
    {
        $saleMap = Sale::query()
            ->withTrashed()
            ->with(['items.item:id,name', 'items.expenseCategory:id,name'])
            ->whereIn('id', $ledgerRows->where('ref_table', 'sales')->pluck('ref_id')->unique())
            ->get()
            ->keyBy('id');

        $purchaseMap = Purchase::query()
            ->withTrashed()
            ->with(['items.item:id,name', 'items.expenseCategory:id,name'])
            ->whereIn('id', $ledgerRows->where('ref_table', 'purchases')->pluck('ref_id')->unique())
            ->get()
            ->keyBy('id');

        $paymentMap = Payment::query()
            ->withTrashed()
            ->with('account:id,name')
            ->whereIn('id', $ledgerRows->where('ref_table', 'payments')->pluck('ref_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($ledgerRows as $row) {
            if ($row->type === 'opening_balance' || $row->ref_table === 'parties') {
                $row->reference_text = 'Opening Balance';

                continue;
            }

            if ($row->ref_table === 'sales') {
                $sale = $saleMap->get($row->ref_id);
                $row->reference_text = $sale
                    ? 'Sale / '.$this->itemSummary($sale->items)
                    : 'Sale / '.$row->ref_id;

                continue;
            }

            if ($row->ref_table === 'purchases') {
                $purchase = $purchaseMap->get($row->ref_id);
                $row->reference_text = $purchase
                    ? 'Purchase / '.$this->itemSummary($purchase->items)
                    : 'Purchase / '.$row->ref_id;

                continue;
            }

            if ($row->ref_table === 'payments') {
                $payment = $paymentMap->get($row->ref_id);
                $row->reference_text = $payment
                    ? 'Payment / '.($payment->account?->name ?? 'Unknown Account')
                    : 'Payment / '.$row->ref_id;

                continue;
            }

            $row->reference_text = ucfirst($row->ref_table).' / '.$row->ref_id;
        }
    }

    private function ensureBillStatusColumns(): void
    {
        if (! Schema::hasColumn('sales', 'status')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->enum('status', ['active', 'cancelled'])->default('active')->after('total');
            });
        }

        if (! Schema::hasColumn('purchases', 'status')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->enum('status', ['active', 'cancelled'])->default('active')->after('total');
            });
        }

        DB::table('sales')->whereNull('status')->update(['status' => Sale::STATUS_ACTIVE]);
        DB::table('purchases')->whereNull('status')->update(['status' => Purchase::STATUS_ACTIVE]);
    }

    private function ensureLedgerSupportsOpeningBalance(): void
    {
        if ($this->ledgerSupportsOpeningBalance()) {
            return;
        }

        $temporaryTable = 'ledger_runtime_rebuild_'.str_replace('.', '', uniqid('', true));

        Schema::create($temporaryTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->nullable()->constrained('parties');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->decimal('dr_amount', 15, 2)->default(0);
            $table->decimal('cr_amount', 15, 2)->default(0);
            $table->enum('type', ['sale', 'purchase', 'payment', 'opening_balance']);
            $table->unsignedBigInteger('ref_id');
            $table->string('ref_table');
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        DB::table('ledger')
            ->orderBy('id')
            ->chunk(200, function (Collection $rows) use ($temporaryTable): void {
                DB::table($temporaryTable)->insert(
                    $rows->map(fn (object $row) => [
                        'id' => $row->id,
                        'party_id' => $row->party_id,
                        'account_id' => $row->account_id,
                        'dr_amount' => $row->dr_amount,
                        'cr_amount' => $row->cr_amount,
                        'type' => $row->type,
                        'ref_id' => $row->ref_id,
                        'ref_table' => $row->ref_table,
                        'created_at' => $row->created_at,
                        'tenant_id' => $row->tenant_id,
                    ])->all()
                );
            });

        Schema::drop('ledger');
        Schema::rename($temporaryTable, 'ledger');

        Schema::table('ledger', function (Blueprint $table) {
            $table->index('tenant_id');
            $table->index(['tenant_id', 'created_at'], 'ledger_tenant_created_idx');
            $table->index(['tenant_id', 'type', 'created_at'], 'ledger_tenant_type_created_idx');
            $table->index(['tenant_id', 'party_id', 'created_at'], 'ledger_tenant_party_created_idx');
            $table->index(['tenant_id', 'account_id', 'created_at'], 'ledger_tenant_account_created_idx');
            $table->index(['tenant_id', 'ref_table', 'ref_id'], 'ledger_tenant_ref_idx');
        });
    }

    private function ledgerSupportsOpeningBalance(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $record = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'ledger'");

            return str_contains((string) ($record->sql ?? ''), 'opening_balance');
        }

        if ($driver === 'mysql') {
            $record = DB::selectOne('SHOW CREATE TABLE ledger');
            $columns = array_values((array) $record);

            return str_contains((string) ($columns[1] ?? ''), 'opening_balance');
        }

        if ($driver === 'pgsql') {
            $constraints = DB::select("
                SELECT pg_get_constraintdef(oid) AS definition
                FROM pg_constraint
                WHERE conrelid = 'ledger'::regclass
                  AND contype = 'c'
            ");

            if ($constraints === []) {
                return true;
            }

            return collect($constraints)->contains(
                fn (object $constraint) => str_contains((string) ($constraint->definition ?? ''), 'opening_balance')
            );
        }

        return true;
    }

    private function backfillPartyOpeningBalanceLedgerRows(): void
    {
        DB::table('parties')
            ->select(['id', 'tenant_id', 'opening_balance', 'opening_balance_side', 'created_at'])
            ->where('opening_balance', '>', 0)
            ->orderBy('id')
            ->chunk(200, function (Collection $parties): void {
                $existing = DB::table('ledger')
                    ->where('type', 'opening_balance')
                    ->where('ref_table', 'parties')
                    ->whereIn('ref_id', $parties->pluck('id')->all())
                    ->pluck('ref_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $existingIds = array_flip($existing);

                $rows = $parties
                    ->reject(fn (object $party) => isset($existingIds[(int) $party->id]))
                    ->map(function (object $party): array {
                        $amount = (float) $party->opening_balance;
                        $isCredit = ($party->opening_balance_side ?? 'dr') === 'cr';

                        return [
                            'tenant_id' => $party->tenant_id,
                            'party_id' => $party->id,
                            'account_id' => null,
                            'dr_amount' => $isCredit ? 0 : $amount,
                            'cr_amount' => $isCredit ? $amount : 0,
                            'type' => 'opening_balance',
                            'ref_id' => $party->id,
                            'ref_table' => 'parties',
                            'created_at' => $party->created_at ?? now(),
                        ];
                    })
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('ledger')->insert($rows);
                }
            });
    }

    private function ensurePreciseRateColumns(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (
            $this->columnScale('items', 'rate') === 4
            && $this->columnScale('items', 'cost_price') === 4
            && $this->columnScale('bill_line_items', 'rate') === 4
            && $this->columnScale('item_ledgers', 'rate') === 4
        ) {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE items MODIFY rate DECIMAL(15,4) NOT NULL');
            DB::statement('ALTER TABLE items MODIFY cost_price DECIMAL(15,4) NOT NULL');
            DB::statement('ALTER TABLE bill_line_items MODIFY rate DECIMAL(15,4) NOT NULL');
            DB::statement('ALTER TABLE item_ledgers MODIFY rate DECIMAL(15,4) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE items ALTER COLUMN rate TYPE NUMERIC(15,4)');
            DB::statement('ALTER TABLE items ALTER COLUMN cost_price TYPE NUMERIC(15,4)');
            DB::statement('ALTER TABLE bill_line_items ALTER COLUMN rate TYPE NUMERIC(15,4)');
            DB::statement('ALTER TABLE item_ledgers ALTER COLUMN rate TYPE NUMERIC(15,4)');
        }
    }

    private function columnScale(string $table, string $column): ?int
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT numeric_scale FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );

            return isset($row->numeric_scale) ? (int) $row->numeric_scale : null;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT numeric_scale FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );

            return isset($row->numeric_scale) ? (int) $row->numeric_scale : null;
        }

        return null;
    }

    private function ensurePaymentSidebarLimitColumn(): void
    {
        if (! Schema::hasColumn('payroll_settings', 'payment_sidebar_limit')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('payment_sidebar_limit')->default(10)->after('overtime_money_per_day');
            });
        }

        DB::table('payroll_settings')
            ->whereNull('payment_sidebar_limit')
            ->update(['payment_sidebar_limit' => 10]);
    }

    private function ensurePaymentNotesColumn(): void
    {
        if (! Schema::hasColumn('payments', 'notes')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('notes', 255)->nullable()->after('purchase_id');
            });
        }
    }

    private function backfillItemOpeningStockRows(): void
    {
        DB::table('items')
            ->select(['id', 'tenant_id', 'qty', 'cost_price', 'created_at'])
            ->orderBy('id')
            ->chunk(200, function (Collection $items): void {
                $itemIds = $items->pluck('id')->all();

                $existingOpeningIds = DB::table('item_ledgers')
                    ->whereIn('item_id', $itemIds)
                    ->where('identifier', 'opening_stock')
                    ->pluck('item_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $movementBalances = DB::table('item_ledgers')
                    ->selectRaw("item_id, COALESCE(SUM(CASE WHEN type = 'in' THEN qty ELSE -qty END), 0) as movement_balance")
                    ->whereIn('item_id', $itemIds)
                    ->where('identifier', '!=', 'opening_stock')
                    ->groupBy('item_id')
                    ->pluck('movement_balance', 'item_id');

                $existingLookup = array_flip($existingOpeningIds);

                $rows = $items
                    ->reject(fn (object $item) => isset($existingLookup[(int) $item->id]))
                    ->map(function (object $item) use ($movementBalances): ?array {
                        $movementBalance = (float) ($movementBalances[$item->id] ?? 0);
                        $openingQty = round((float) $item->qty - $movementBalance, 4);

                        if ($openingQty <= 0) {
                            return null;
                        }

                        return [
                            'tenant_id' => $item->tenant_id,
                            'item_id' => $item->id,
                            'type' => 'in',
                            'qty' => $openingQty,
                            'rate' => round((float) $item->cost_price, 4),
                            'identifier' => 'opening_stock',
                            'foreign_key' => $item->id,
                            'created_at' => $item->created_at ?? now(),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('item_ledgers')->insert($rows);
                }
            });
    }

    private function itemSummary(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'No items';
        }

        return $items
            ->map(fn ($item) => sprintf(
                '%s @ %s * %s',
                $item->line_label,
                number_format((float) $item->rate, 2),
                number_format((float) $item->qty, 2)
            ))
            ->implode(', ');
    }
}
