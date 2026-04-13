<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ExpenseCategory extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    private static ?string $resolvedParentColumn = null;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, self::parentColumn());
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, self::parentColumn())
            ->orderBy('name');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function scopeRoots(Builder $query): Builder
    {
        $column = $query->getModel()->qualifyColumn(self::parentColumn());

        return $query->where(function (Builder $builder) use ($column): void {
            $builder
                ->whereNull($column)
                ->orWhere($column, 0);
        });
    }

    public static function parentColumn(): string
    {
        if (self::$resolvedParentColumn !== null) {
            return self::$resolvedParentColumn;
        }

        $table = (new self())->getTable();

        if (Schema::hasColumn($table, 'parent_category_id')) {
            self::$resolvedParentColumn = 'parent_category_id';

            return self::$resolvedParentColumn;
        }

        self::$resolvedParentColumn = 'parent_id';

        return self::$resolvedParentColumn;
    }

    public function getParentCategoryIdAttribute(): ?int
    {
        $column = self::parentColumn();
        $value = $this->attributes[$column] ?? null;

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }
}
