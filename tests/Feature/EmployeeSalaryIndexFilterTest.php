<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Party;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_filter_options_only_include_party_linked_employees(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $partyA = Party::query()->create(['name' => 'Employee Party A', 'phone' => '9800000001']);
        $partyB = Party::query()->create(['name' => 'Employee Party B', 'phone' => '9800000002']);
        Party::query()->create(['name' => 'Non Employee Party', 'phone' => '9800000003']);

        $employeeA = Employee::query()->create([
            'party_id' => $partyA->id,
            'salary' => 1000,
        ]);

        $employeeB = Employee::query()->create([
            'party_id' => $partyB->id,
            'salary' => 1200,
        ]);

        $response = $this->actingAs($user)->get(route('employee-salaries.index'));

        $response
            ->assertOk()
            ->assertViewHas('employees', function ($employees) use ($employeeA, $employeeB): bool {
                $ids = $employees->pluck('id')->all();

                sort($ids);
                $expected = [$employeeA->id, $employeeB->id];
                sort($expected);

                return $ids === $expected;
            });
    }

    public function test_employee_id_filter_returns_only_selected_employee_salary_records(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $partyA = Party::query()->create(['name' => 'Salary Party A', 'phone' => '9800000011']);
        $partyB = Party::query()->create(['name' => 'Salary Party B', 'phone' => '9800000012']);

        $employeeA = Employee::query()->create([
            'party_id' => $partyA->id,
            'salary' => 1500,
        ]);

        $employeeB = Employee::query()->create([
            'party_id' => $partyB->id,
            'salary' => 1700,
        ]);

        EmployeeSalary::query()->create([
            'employee_id' => $employeeA->id,
            'employee_name' => $partyA->name,
            'employee_code' => $partyA->phone,
            'party_id' => $partyA->id,
            'salary_date' => '2026-04-01',
            'salary_month' => '2082-12',
            'basic_salary' => 1500,
            'allowance' => 100,
            'deduction' => 50,
            'leave_days' => 1,
            'overtime_days' => 1,
            'net_salary' => 1550,
        ]);

        EmployeeSalary::query()->create([
            'employee_id' => $employeeB->id,
            'employee_name' => $partyB->name,
            'employee_code' => $partyB->phone,
            'party_id' => $partyB->id,
            'salary_date' => '2026-04-02',
            'salary_month' => '2082-12',
            'basic_salary' => 1700,
            'allowance' => 120,
            'deduction' => 20,
            'leave_days' => 0,
            'overtime_days' => 1,
            'net_salary' => 1800,
        ]);

        $response = $this->actingAs($user)->get(route('employee-salaries.index', [
            'employee_id' => $employeeA->id,
        ]));

        $response
            ->assertOk()
            ->assertViewHas('salaries', function ($salaries) use ($employeeA): bool {
                return $salaries->total() === 1
                    && (int) $salaries->first()->employee_id === (int) $employeeA->id;
            });
    }

    public function test_employee_id_filter_rejects_other_tenant_employee(): void
    {
        $defaultTenantId = (int) Tenant::query()->where('code', 'default')->value('id');

        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'code' => 'other-tenant',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
        ]);

        /** @var User $otherTenantUser */
        $otherTenantUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $party = Party::query()->create([
            'name' => 'Tenant A Employee Party',
            'phone' => '9800000099',
            'tenant_id' => $defaultTenantId,
        ]);

        $employee = Employee::query()->create([
            'party_id' => $party->id,
            'salary' => 1200,
            'tenant_id' => $defaultTenantId,
        ]);

        $this->actingAs($otherTenantUser)
            ->getJson(route('employee-salaries.index', ['employee_id' => $employee->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id']);
    }
}
