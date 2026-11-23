<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'product_variant_id',
        'system_qty',
        'physical_qty',
        'difference',
        'qty_before',
        'qty_after',
        'qty_diff',
    ];

    protected static ?array $columnsCache = null;

    public static function getTableColumns(): array
    {
        if (static::$columnsCache === null) {
            try {
                static::$columnsCache = array_flip(Schema::getColumnListing('stock_opname_items'));
            } catch (\Throwable) {
                static::$columnsCache = array_flip(['id', 'stock_opname_id', 'product_variant_id', 'system_qty', 'physical_qty', 'difference', 'created_at', 'updated_at']);
            }
        }

        return static::$columnsCache;
    }

    public static function normalizeRowForInsert(array $row): array
    {
        $cols = static::getTableColumns();
        $sysQty = (int) ($row['system_qty'] ?? $row['qty_before'] ?? 0);
        $physQty = (int) ($row['physical_qty'] ?? $row['qty_after'] ?? 0);
        $diff = (int) ($row['difference'] ?? $row['qty_diff'] ?? ($physQty - $sysQty));

        $candidates = [
            'stock_opname_id' => $row['stock_opname_id'] ?? null,
            'product_variant_id' => $row['product_variant_id'] ?? null,
            'system_qty' => $sysQty,
            'qty_before' => $sysQty,
            'system_stock' => $sysQty,
            'physical_qty' => $physQty,
            'qty_after' => $physQty,
            'actual_stock' => $physQty,
            'physical_stock' => $physQty,
            'difference' => $diff,
            'qty_diff' => $diff,
            'diff' => $diff,
            'created_at' => $row['created_at'] ?? now(),
            'updated_at' => $row['updated_at'] ?? now(),
        ];

        $clean = [];
        foreach ($candidates as $k => $v) {
            if (isset($cols[$k])) {
                $clean[$k] = $v;
            }
        }

        return $clean;
    }

    protected static function booted()
    {
        static::creating(function (StockOpnameItem $model) {
            $clean = static::normalizeRowForInsert($model->getAttributes());
            $model->setRawAttributes($clean);
        });
    }

    public function getSystemQtyAttribute(): int
    {
        return (int) ($this->attributes['system_qty'] ?? $this->attributes['qty_before'] ?? $this->attributes['system_stock'] ?? 0);
    }

    public function getPhysicalQtyAttribute(): int
    {
        return (int) ($this->attributes['physical_qty'] ?? $this->attributes['qty_after'] ?? $this->attributes['actual_stock'] ?? $this->attributes['physical_stock'] ?? 0);
    }

    public function getDifferenceAttribute(): int
    {
        if (isset($this->attributes['difference'])) {
            return (int) $this->attributes['difference'];
        }
        if (isset($this->attributes['qty_diff'])) {
            return (int) $this->attributes['qty_diff'];
        }

        return $this->physical_qty - $this->system_qty;
    }

    protected $appends = ['system_qty', 'physical_qty', 'difference'];

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
