<?php

use App\Helpers\DateHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addNullableUnsignedIntColumn('sales', 'date', 'status');
        $this->addNullableUnsignedIntColumn('purchases', 'date', 'status');
        $this->addNullableUnsignedIntColumn('payments', 'date', 'notes');
        $this->addNullableUnsignedIntColumn('ledger', 'date', 'ref_table');
        $this->addNullableUnsignedIntColumn('parties', 'opening_balance_date', 'opening_balance_side');

        $this->backfillBusinessDates('sales');
        $this->backfillBusinessDates('purchases');
        $this->backfillBusinessDates('payments');
        $this->backfillBusinessDates('ledger');
        $this->backfillPartyOpeningBalanceDates();

        $this->addIndexIfMissing('sales', ['tenant_id', 'date'], 'sales_tenant_date_idx');
        $this->addIndexIfMissing('sales', ['tenant_id', 'party_id', 'date'], 'sales_tenant_party_date_idx');

        $this->addIndexIfMissing('purchases', ['tenant_id', 'date'], 'purchases_tenant_date_idx');
        $this->addIndexIfMissing('purchases', ['tenant_id', 'party_id', 'date'], 'purchases_tenant_party_date_idx');

        $this->addIndexIfMissing('payments', ['tenant_id', 'date'], 'payments_tenant_date_idx');
        $this->addIndexIfMissing('payments', ['tenant_id', 'party_id', 'date'], 'payments_tenant_party_date_idx');
        $this->addIndexIfMissing('payments', ['tenant_id', 'account_id', 'date'], 'payments_tenant_account_date_idx');

        $this->addIndexIfMissing('ledger', ['tenant_id', 'date'], 'ledger_tenant_date_idx');
        $this->addIndexIfMissing('ledger', ['tenant_id', 'type', 'date'], 'ledger_tenant_type_date_idx');
        $this->addIndexIfMissing('ledger', ['tenant_id', 'party_id', 'date'], 'ledger_tenant_party_date_idx');
        $this->addIndexIfMissing('ledger', ['tenant_id', 'account_id', 'date'], 'ledger_tenant_account_date_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('ledger', 'ledger_tenant_date_idx');
        $this->dropIndexIfExists('ledger', 'ledger_tenant_type_date_idx');
        $this->dropIndexIfExists('ledger', 'ledger_tenant_party_date_idx');
        $this->dropIndexIfExists('ledger', 'ledger_tenant_account_date_idx');
        $this->dropColumnIfExists('ledger', 'date');

        $this->dropIndexIfExists('payments', 'payments_tenant_date_idx');
        $this->dropIndexIfExists('payments', 'payments_tenant_party_date_idx');
        $this->dropIndexIfExists('payments', 'payments_tenant_account_date_idx');
        $this->dropColumnIfExists('payments', 'date');

        $this->dropIndexIfExists('purchases', 'purchases_tenant_date_idx');
        $this->dropIndexIfExists('purchases', 'purchases_tenant_party_date_idx');
        $this->dropColumnIfExists('purchases', 'date');

        $this->dropIndexIfExists('sales', 'sales_tenant_date_idx');
        $this->dropIndexIfExists('sales', 'sales_tenant_party_date_idx');
        $this->dropColumnIfExists('sales', 'date');

        $this->dropColumnIfExists('parties', 'opening_balance_date');
    }

    private function backfillBusinessDates(string $table): void
    {
        DB::table($table)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $createdAt = $row->created_at ? (string) $row->created_at : now()->toDateString();

                    DB::table($table)
                        ->where('id', $row->id)
                        ->whereNull('date')
                        ->update([
                            'date' => DateHelper::adToBsInt($createdAt),
                        ]);
                }
            });
    }

    private function backfillPartyOpeningBalanceDates(): void
    {
        DB::table('parties')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $openingDate = DB::table('ledger')
                        ->where('type', 'opening_balance')
                        ->where('ref_table', 'parties')
                        ->where('ref_id', $row->id)
                        ->where('party_id', $row->id)
                        ->value('date');

                    $resolved = $openingDate
                        ? (int) $openingDate
                        : DateHelper::adToBsInt($row->created_at ? (string) $row->created_at : now()->toDateString());

                    DB::table('parties')
                        ->where('id', $row->id)
                        ->whereNull('opening_balance_date')
                        ->update([
                            'opening_balance_date' => $resolved,
                        ]);
                }
            });
    }

    private function addNullableUnsignedIntColumn(string $tableName, string $columnName, string $after): void
    {
        if (Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $after): void {
            $table->unsignedInteger($columnName)->nullable()->after($after);
        });
    }

    private function dropColumnIfExists(string $tableName, string $columnName): void
    {
        if (! Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
            $table->dropColumn($columnName);
        });
    }

    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }
};
