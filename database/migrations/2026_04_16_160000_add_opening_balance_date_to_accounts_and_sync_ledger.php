<?php

use App\Helpers\DateHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounts', 'opening_balance_date')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->unsignedInteger('opening_balance_date')->nullable()->after('opening_balance_side');
            });
        }

        DB::table('accounts')
            ->whereNull('opening_balance_date')
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $openingDate = DB::table('ledger')
                        ->where('type', 'opening_balance')
                        ->where('ref_table', 'accounts')
                        ->where('ref_id', $row->id)
                        ->where('account_id', $row->id)
                        ->value('date');

                    DB::table('accounts')
                        ->where('id', $row->id)
                        ->whereNull('opening_balance_date')
                        ->update([
                            'opening_balance_date' => $openingDate
                                ? (int) $openingDate
                                : $this->resolveBsDateInt($row->created_at ?? null),
                        ]);
                }
            });

        $this->backfillAccountOpeningLedgers();
    }

    public function down(): void
    {
        DB::table('ledger')
            ->where('type', 'opening_balance')
            ->where('ref_table', 'accounts')
            ->delete();

        if (Schema::hasColumn('accounts', 'opening_balance_date')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('opening_balance_date');
            });
        }
    }

    private function backfillAccountOpeningLedgers(): void
    {
        if (! Schema::hasTable('ledger')) {
            return;
        }

        if (! Schema::hasColumn('ledger', 'date') || ! Schema::hasColumn('ledger', 'account_id')) {
            return;
        }

        DB::table('accounts')
            ->select(['id', 'tenant_id', 'opening_balance', 'opening_balance_side', 'opening_balance_date', 'created_at'])
            ->where('opening_balance', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function (Collection $accounts): void {
                $existing = DB::table('ledger')
                    ->where('type', 'opening_balance')
                    ->where('ref_table', 'accounts')
                    ->whereIn('ref_id', $accounts->pluck('id')->all())
                    ->pluck('ref_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $existingIds = array_flip($existing);

                $rows = $accounts
                    ->reject(fn (object $account) => isset($existingIds[(int) $account->id]))
                    ->map(function (object $account): array {
                        $amount = (float) ($account->opening_balance ?? 0);
                        $isCredit = ($account->opening_balance_side ?? 'dr') === 'cr';

                        return [
                            'tenant_id' => $account->tenant_id,
                            'party_id' => null,
                            'account_id' => $account->id,
                            'dr_amount' => $isCredit ? 0 : $amount,
                            'cr_amount' => $isCredit ? $amount : 0,
                            'type' => 'opening_balance',
                            'ref_id' => $account->id,
                            'ref_table' => 'accounts',
                            'date' => $account->opening_balance_date
                                ? (int) $account->opening_balance_date
                                : $this->resolveBsDateInt($account->created_at ?? null),
                            'created_at' => $account->created_at ?? now(),
                        ];
                    })
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('ledger')->insert($rows);
                }
            });
    }

    private function resolveBsDateInt(mixed $sourceDate): int
    {
        if ($sourceDate instanceof DateTimeInterface) {
            return DateHelper::adToBsInt($sourceDate);
        }

        if (is_string($sourceDate) && trim($sourceDate) !== '') {
            return DateHelper::adToBsInt($sourceDate);
        }

        return DateHelper::adToBsInt(now());
    }
};
