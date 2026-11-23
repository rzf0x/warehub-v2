<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'user_id',
        'created_by',
        'date',
        'opname_date',
        'note',
        'notes',
    ];

    protected static ?array $columnsCache = null;

    public static function getTableColumns(): array
    {
        if (static::$columnsCache === null) {
            static::$columnsCache = array_flip(Schema::getColumnListing('stock_opnames'));
        }

        return static::$columnsCache;
    }

    protected static function booted()
    {
        static::creating(function (StockOpname $model) {
            $cols = static::getTableColumns();
            $attrs = $model->getAttributes();

            // Map user_id / created_by
            $userId = $attrs['user_id'] ?? $attrs['created_by'] ?? auth()->id();
            if (isset($cols['user_id'])) {
                $model->setAttribute('user_id', $userId);
            } elseif (isset($cols['created_by'])) {
                $model->setAttribute('created_by', $userId);
            }

            // Map date / opname_date
            $dateVal = $attrs['date'] ?? $attrs['opname_date'] ?? now()->toDateString();
            if (isset($cols['date'])) {
                $model->setAttribute('date', $dateVal);
            } elseif (isset($cols['opname_date'])) {
                $model->setAttribute('opname_date', $dateVal);
            }

            // Map note / notes
            $noteVal = $attrs['note'] ?? $attrs['notes'] ?? null;
            if (isset($cols['note'])) {
                $model->setAttribute('note', $noteVal);
            } elseif (isset($cols['notes'])) {
                $model->setAttribute('notes', $noteVal);
            }

            // Strip any attribute that does not exist in DB table
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
            'date' => 'date:Y-m-d',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        $cols = static::getTableColumns();
        $fk = isset($cols['user_id']) ? 'user_id' : (isset($cols['created_by']) ? 'created_by' : 'user_id');

        return $this->belongsTo(User::class, $fk);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
