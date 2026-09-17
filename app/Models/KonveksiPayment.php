<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KonveksiPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'konveksi_id',
        'amount',
        'payment_date',
        'proof_image',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function konveksi(): BelongsTo
    {
        return $this->belongsTo(Konveksi::class);
    }
}
