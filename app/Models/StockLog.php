<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'product_variant_id',
        'warehouse_id',
        'user_id',
        'type',
        'quantity_before',
        'quantity_after',
        'qty_before',
        'qty_change',
        'qty_after',
        'change_amount',
        'note',
        'reference_type',
        'reference_id',
    ];

    protected static function booted()
    {
        static::creating(function ($log) {
            // Auto sync qty fields
            if ($log->qty_before !== null && $log->quantity_before === null) {
                $log->quantity_before = (int)$log->qty_before;
            } elseif ($log->quantity_before !== null && $log->qty_before === null) {
                $log->qty_before = (int)$log->quantity_before;
            }

            if ($log->qty_change !== null && $log->change_amount === null) {
                $log->change_amount = (int)$log->qty_change;
            } elseif ($log->change_amount !== null && $log->qty_change === null) {
                $log->qty_change = (int)$log->change_amount;
            }

            if ($log->qty_after !== null && $log->quantity_after === null) {
                $log->quantity_after = (int)$log->qty_after;
            } elseif ($log->quantity_after !== null && $log->qty_after === null) {
                $log->qty_after = (int)$log->quantity_after;
            }

            // Auto set user_id
            if (!$log->user_id && auth()->check()) {
                $log->user_id = auth()->id();
            }

            // Auto set default type
            if (!$log->type) {
                if ($log->qty_change > 0) {
                    $log->type = 'MANUAL_ADD';
                } elseif ($log->qty_change < 0) {
                    $log->type = 'STOCK_OUT';
                } else {
                    $log->type = 'ADJUSTMENT';
                }
            }

            // Auto set stock_id if missing
            if (!$log->stock_id && $log->warehouse_id && $log->product_variant_id) {
                $stock = Stock::firstOrCreate(
                    ['warehouse_id' => $log->warehouse_id, 'product_variant_id' => $log->product_variant_id],
                    ['qty' => 0, 'allocated_qty' => 0]
                );
                $log->stock_id = $stock->id;
            }
        });
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }
}
