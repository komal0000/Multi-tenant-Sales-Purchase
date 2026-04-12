<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class Ledger extends Model
{
    use BelongsToTenant;

    protected $table = 'ledger';

    protected $guarded = [];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'dr_amount' => 'decimal:2',
            'cr_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ledger $ledger): void {
            $hasParty = filled($ledger->party_id);
            $hasAccount = filled($ledger->account_id);
            $hasDr = (float) $ledger->dr_amount > 0;
            $hasCr = (float) $ledger->cr_amount > 0;

            if ($hasParty === $hasAccount) {
                throw new InvalidArgumentException('Ledger row must belong to exactly one entity.');
            }

            if ($hasDr === $hasCr) {
                throw new InvalidArgumentException('Ledger row must contain exactly one side.');
            }
        });

        static::updating(function (): void {
            throw new LogicException('Ledger entries cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Ledger entries cannot be deleted.');
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
