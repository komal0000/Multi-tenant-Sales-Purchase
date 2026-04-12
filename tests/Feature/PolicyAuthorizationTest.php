<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    /**
     * @dataProvider policyModelProvider
     */
    public function test_view_and_update_require_same_tenant(string $modelClass): void
    {
        $resource = new $modelClass();
        $resource->tenant_id = 10;

        $sameTenantUser = new User(['tenant_id' => 10, 'role' => 1]);
        $otherTenantUser = new User(['tenant_id' => 11, 'role' => 1]);
        $noTenantUser = new User(['tenant_id' => null, 'role' => 1]);

        $this->assertTrue(Gate::forUser($sameTenantUser)->allows('view', $resource));
        $this->assertTrue(Gate::forUser($sameTenantUser)->allows('update', $resource));

        $this->assertFalse(Gate::forUser($otherTenantUser)->allows('view', $resource));
        $this->assertFalse(Gate::forUser($otherTenantUser)->allows('update', $resource));

        $this->assertFalse(Gate::forUser($noTenantUser)->allows('view', $resource));
        $this->assertFalse(Gate::forUser($noTenantUser)->allows('update', $resource));
    }

    /**
     * @dataProvider policyModelProvider
     */
    public function test_delete_requires_admin_and_same_tenant(string $modelClass): void
    {
        $resource = new $modelClass();
        $resource->tenant_id = 10;

        $sameTenantAdmin = new User(['tenant_id' => 10, 'role' => 0]);
        $sameTenantNonAdmin = new User(['tenant_id' => 10, 'role' => 1]);
        $otherTenantAdmin = new User(['tenant_id' => 11, 'role' => 0]);

        $this->assertTrue(Gate::forUser($sameTenantAdmin)->allows('delete', $resource));
        $this->assertFalse(Gate::forUser($sameTenantNonAdmin)->allows('delete', $resource));
        $this->assertFalse(Gate::forUser($otherTenantAdmin)->allows('delete', $resource));
    }

    /**
     * @dataProvider policyModelProvider
     */
    public function test_view_any_and_create_require_tenant_on_user(string $modelClass): void
    {
        $userWithTenant = new User(['tenant_id' => 10, 'role' => 1]);
        $userWithoutTenant = new User(['tenant_id' => null, 'role' => 1]);

        $this->assertTrue(Gate::forUser($userWithTenant)->allows('viewAny', $modelClass));
        $this->assertTrue(Gate::forUser($userWithTenant)->allows('create', $modelClass));

        $this->assertFalse(Gate::forUser($userWithoutTenant)->allows('viewAny', $modelClass));
        $this->assertFalse(Gate::forUser($userWithoutTenant)->allows('create', $modelClass));
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function policyModelProvider(): array
    {
        return [
            'account' => [Account::class],
            'employee' => [Employee::class],
            'employee-salary' => [EmployeeSalary::class],
            'party' => [Party::class],
            'payment' => [Payment::class],
            'purchase' => [Purchase::class],
            'sale' => [Sale::class],
        ];
    }
}
