<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Employee;
use App\Models\Party;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\PartyCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Account::query()->create([
            'name' => 'Cash',
            'type' => 'cash',
        ]);

        Account::query()->create([
            'name' => 'Bank',
            'type' => 'bank',
        ]);

        collect([
            ['name' => 'Ram Traders', 'phone' => '9800000002', 'salary' => 30000],
            ['name' => 'Sita Suppliers', 'phone' => '9800000003', 'salary' => 32000],
            ['name' => 'Hari Shop', 'phone' => '9800000004', 'salary' => 28000],
        ])->each(function (array $row) {
            $party = Party::query()->create([
                'name' => $row['name'],
                'phone' => $row['phone'],
            ]);

            Employee::query()->create([
                'party_id' => $party->id,
                'salary' => $row['salary'],
            ]);
        });

        PayrollSetting::query()->firstOrCreate([], [
            'leave_fine_per_day' => 500,
            'overtime_money_per_day' => 700,
        ]);

        User::query()->firstOrCreate([
            'phone' => 9800000001,
        ], [
            'name' => 'Normal User',
            'role' => 1,
            'email' => 'user@ledger.local',
            'password' => Hash::make('password'),
        ]);

        app(PartyCacheService::class)->refreshAll();

    }
}
