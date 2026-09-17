<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'seller_name',
        'phone',
        'address',
        'stock_thresholds',
    ];

    protected $appends = ['name'];

    public function getNameAttribute(): ?string
    {
        return $this->attributes['seller_name'] ?? null;
    }

    protected function casts(): array
    {
        return [
            'stock_thresholds' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function pengadaans(): BelongsToMany
    {
        return $this->belongsToMany(Pengadaan::class, 'pengadaan_seller');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function manualGlobalProducts(): HasMany
    {
        return $this->hasMany(ManualGlobalProduct::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function sellerDebtAdjustments(): HasMany
    {
        return $this->hasMany(SellerDebtAdjustment::class);
    }

    public function proofTransfers(): HasMany
    {
        return $this->hasMany(ProofTransfer::class);
    }
}
