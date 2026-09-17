<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_in_id',
        'product_variant_id',
        'konveksi_id',
        'qty',
        'price',
        'granular_quantities',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'granular_quantities' => 'array',
        ];
    }

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo(StockIn::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function konveksi(): BelongsTo
    {
        return $this->belongsTo(Konveksi::class);
    }
}
