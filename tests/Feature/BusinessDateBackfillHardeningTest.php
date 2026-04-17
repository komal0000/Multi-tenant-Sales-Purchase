<?php

namespace Tests\Feature;

use App\Helpers\DateHelper;
use App\Models\Tenant;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessDateBackfillHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_backfill_accepts_timestamped_created_at_values(): void
    {
        $tenant = Tenant::query()->firstOrFail();

        $partyId = DB::table('parties')->insertGetId([
            'name' => 'Backfill Party',
            'phone' => '9800000001',
            'opening_balance' => 100,
            'opening_balance_side' => 'dr',
            'opening_balance_date' => null,
            'tenant_id' => $tenant->id,
            'created_at' => '2026-04-14 09:15:30',
            'updated_at' => '2026-04-14 09:15:30',
        ]);

        $accountId = DB::table('accounts')->insertGetId([
            'name' => 'Backfill Cash',
            'type' => 'cash',
            'opening_balance' => 50,
            'opening_balance_side' => 'dr',
            'opening_balance_date' => null,
            'tenant_id' => $tenant->id,
            'created_at' => '2026-04-13 08:00:00',
            'updated_at' => '2026-04-13 08:00:00',
        ]);

        $paymentId = DB::table('payments')->insertGetId([
            'party_id' => $partyId,
            'amount' => 1200,
            'type' => 'received',
            'account_id' => $accountId,
            'sale_id' => null,
            'purchase_id' => null,
            'notes' => 'Backfill payment',
            'date' => null,
            'tenant_id' => $tenant->id,
            'created_at' => '2026-04-16 13:24:08',
            'updated_at' => '2026-04-16 13:24:08',
            'deleted_at' => null,
        ]);

        $paymentLedgerId = DB::table('ledger')->insertGetId([
            'party_id' => $partyId,
            'account_id' => null,
            'dr_amount' => 0,
            'cr_amount' => 1200,
            'type' => 'payment',
            'ref_id' => $paymentId,
            'ref_table' => 'payments',
            'date' => null,
            'tenant_id' => $tenant->id,
            'created_at' => '2026-04-16 13:24:08',
        ]);

        app(LedgerService::class)->ensureCompatibilitySchema();

        DB::table('parties')->where('id', $partyId)->update(['opening_balance_date' => null]);
        DB::table('accounts')->where('id', $accountId)->update(['opening_balance_date' => null]);
        DB::table('ledger')
            ->where('type', 'opening_balance')
            ->where(function ($query) use ($partyId, $accountId): void {
                $query
                    ->where(function ($nested) use ($partyId): void {
                        $nested->where('ref_table', 'parties')->where('ref_id', $partyId);
                    })
                    ->orWhere(function ($nested) use ($accountId): void {
                        $nested->where('ref_table', 'accounts')->where('ref_id', $accountId);
                    });
            })
            ->delete();

        DB::table('ledger')->insert([
            [
                'party_id' => $partyId,
                'account_id' => null,
                'dr_amount' => 100,
                'cr_amount' => 0,
                'type' => 'opening_balance',
                'ref_id' => $partyId,
                'ref_table' => 'parties',
                'date' => null,
                'tenant_id' => $tenant->id,
                'created_at' => '2026-04-15 11:10:09',
            ],
            [
                'party_id' => null,
                'account_id' => $accountId,
                'dr_amount' => 50,
                'cr_amount' => 0,
                'type' => 'opening_balance',
                'ref_id' => $accountId,
                'ref_table' => 'accounts',
                'date' => null,
                'tenant_id' => $tenant->id,
                'created_at' => '2026-04-12 10:00:00',
            ],
        ]);

        app(LedgerService::class)->ensureCompatibilitySchema();

        $this->assertSame(
            DateHelper::adToBsInt('2026-04-16 13:24:08'),
            (int) DB::table('payments')->where('id', $paymentId)->value('date')
        );

        $this->assertSame(
            DateHelper::adToBsInt('2026-04-16 13:24:08'),
            (int) DB::table('ledger')->where('id', $paymentLedgerId)->value('date')
        );

        $this->assertSame(
            DateHelper::adToBsInt('2026-04-15 11:10:09'),
            (int) DB::table('parties')->where('id', $partyId)->value('opening_balance_date')
        );

        $this->assertSame(
            DateHelper::adToBsInt('2026-04-12 10:00:00'),
            (int) DB::table('accounts')->where('id', $accountId)->value('opening_balance_date')
        );
    }

    public function test_runtime_backfill_falls_back_to_current_date_when_created_at_is_null(): void
    {
        CarbonImmutable::setTestNow('2026-04-18 07:30:00');

        try {
            $tenant = Tenant::query()->firstOrFail();

            $partyId = DB::table('parties')->insertGetId([
                'name' => 'Null Created Party',
                'phone' => null,
                'opening_balance' => 0,
                'opening_balance_side' => 'dr',
                'opening_balance_date' => null,
                'tenant_id' => $tenant->id,
                'created_at' => null,
                'updated_at' => null,
            ]);

            $accountId = DB::table('accounts')->insertGetId([
                'name' => 'Null Created Cash',
                'type' => 'cash',
                'opening_balance' => 0,
                'opening_balance_side' => 'dr',
                'opening_balance_date' => null,
                'tenant_id' => $tenant->id,
                'created_at' => null,
                'updated_at' => null,
            ]);

            $paymentId = DB::table('payments')->insertGetId([
                'party_id' => $partyId,
                'amount' => 500,
                'type' => 'given',
                'account_id' => $accountId,
                'sale_id' => null,
                'purchase_id' => null,
                'notes' => null,
                'date' => null,
                'tenant_id' => $tenant->id,
                'created_at' => null,
                'updated_at' => null,
                'deleted_at' => null,
            ]);

            app(LedgerService::class)->ensureCompatibilitySchema();

            $this->assertSame(
                DateHelper::adToBsInt(now()),
                (int) DB::table('payments')->where('id', $paymentId)->value('date')
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
