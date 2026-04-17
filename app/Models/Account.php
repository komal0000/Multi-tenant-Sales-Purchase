<?php

namespace App\Models;

use App\Helpers\DateHelper;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            if (filled($account->opening_balance_date)) {
                return;
            }

            $sourceDate = $account->created_at ?? now();
            $account->opening_balance_date = DateHelper::adToBsInt($sourceDate);
        });
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }
}
