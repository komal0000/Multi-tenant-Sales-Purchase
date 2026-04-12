<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'leave_fine_per_day' => 'decimal:2',
        'overtime_money_per_day' => 'decimal:2',
    ];
}
