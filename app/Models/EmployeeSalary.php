<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'salary_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction' => 'decimal:2',
        'leave_days' => 'decimal:2',
        'overtime_days' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'expense_saved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function expensePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'expense_payment_id');
    }
}
