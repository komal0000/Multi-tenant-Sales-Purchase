<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'rate' => 'decimal:4',
            'cost_price' => 'decimal:4',
        ];
    }

    public function itemLedgers(): HasMany
    {
        return $this->hasMany(ItemLedger::class);
    }
}
