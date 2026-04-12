<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ItemLedger extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'rate' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Item ledger entries cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Item ledger entries cannot be deleted.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
