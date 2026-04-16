<?php

namespace App\Models;

use App\Helpers\DateHelper;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $party): void {
            if (filled($party->opening_balance_date)) {
                return;
            }

            $sourceDate = $party->created_at ?? now();
            $party->opening_balance_date = DateHelper::adToBsInt($sourceDate);
        });
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }
}
