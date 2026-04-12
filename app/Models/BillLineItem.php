<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLineItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'rate' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BillLineItem $item): void {
            $qty = filled($item->qty) ? (float) $item->qty : 1.0;
            $item->total = round($qty * (float) $item->rate, 2);
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function getLineLabelAttribute(): string
    {
        return match ($this->line_type) {
            'item' => $this->item?->name ?? $this->description ?? 'Unknown Item',
            'expense' => $this->expenseCategory?->name ?? $this->description ?? 'Expense',
            default => $this->description ?? 'General Line',
        };
    }
}
