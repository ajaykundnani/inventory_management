<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'low_stock_threshold',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockStatus(): StockStatus
    {
        return match (true) {
            $this->stock_quantity <= 0 => StockStatus::OutOfStock,
            $this->stock_quantity <= $this->low_stock_threshold => StockStatus::LowStock,
            default => StockStatus::InStock,
        };
    }

    /**
     * Case-insensitive match on name or SKU, with LIKE wildcards in the term escaped.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$like]);
        });
    }

    /**
     * Mirrors stockStatus() at the SQL level so filters and dashboard counts agree.
     */
    public function scopeWhereStockStatus(Builder $query, StockStatus $status): Builder
    {
        return match ($status) {
            StockStatus::OutOfStock => $query->where('stock_quantity', '<=', 0),
            StockStatus::LowStock => $query->where('stock_quantity', '>', 0)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
            StockStatus::InStock => $query->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
        };
    }
}
