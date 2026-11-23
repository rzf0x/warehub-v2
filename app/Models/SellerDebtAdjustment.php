<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class SellerDebtAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'period_id',
        'created_by',
        'user_id',
        'amount',
        'reason',
        'note',
        'date',
        'adjustment_date',
    ];

    protected $appends = [
        'note',
        'adjustment_date',
    ];

    protected static ?array $columnsCache = null;

    public static function getTableColumns(): array
    {
        if (static::$columnsCache === null) {
            static::$columnsCache = array_flip(Schema::getColumnListing('seller_debt_adjustments'));
        }

        return static::$columnsCache;
    }

    protected static function booted()
    {
        static::creating(function (SellerDebtAdjustment $model) {
            $cols = static::getTableColumns();
            $attrs = $model->getAttributes();

            $noteVal = $attrs['note'] ?? $attrs['reason'] ?? 'Penyesuaian Saldo';
            if (isset($cols['note'])) {
                $model->setAttribute('note', $noteVal);
            }
            if (isset($cols['reason'])) {
                $model->setAttribute('reason', $noteVal);
            }

            $dateVal = $attrs['adjustment_date'] ?? $attrs['date'] ?? now()->toDateString();
            if (isset($cols['adjustment_date'])) {
                $model->setAttribute('adjustment_date', $dateVal);
            }
            if (isset($cols['date'])) {
                $model->setAttribute('date', $dateVal);
            }

            $userId = $attrs['created_by'] ?? $attrs['user_id'] ?? auth()->id();
            if (isset($cols['created_by'])) {
                $model->setAttribute('created_by', $userId);
            }
            if (isset($cols['user_id'])) {
                $model->setAttribute('user_id', $userId);
            }

            foreach (array_keys($model->getAttributes()) as $key) {
                if (! isset($cols[$key])) {
                    unset($model->$key);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date:Y-m-d',
        ];
    }

    public function getNoteAttribute(): ?string
    {
        return $this->attributes['note'] ?? $this->attributes['reason'] ?? null;
    }

    public function getAdjustmentDateAttribute(): ?string
    {
        if (! empty($this->attributes['adjustment_date'])) {
            return (string) $this->attributes['adjustment_date'];
        }
        if (! empty($this->attributes['date'])) {
            return (string) $this->attributes['date'];
        }

        return $this->created_at ? $this->created_at->format('Y-m-d') : null;
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
