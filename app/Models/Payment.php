<?php

namespace App\Models;

use App\Helpers\DateHelper;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Payment extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (filled($payment->date)) {
                return;
            }

            $sourceDate = $payment->created_at ?? now();
            $payment->date = DateHelper::adToBsInt($sourceDate);
        });

        static::saving(function (Payment $payment): void {
            if (filled($payment->sale_id) && filled($payment->purchase_id)) {
                throw new InvalidArgumentException('Payment cannot link to both a sale and a purchase.');
            }
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
