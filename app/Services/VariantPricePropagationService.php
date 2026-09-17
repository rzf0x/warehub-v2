<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class VariantPricePropagationService
{
    /**
     * Propagate variant price changes and update related cost logs / records if needed.
     *
     * @param ProductVariant $variant
     * @param float $oldPrice
     * @param float $oldSellingPrice
     * @return void
     */
    public function propagatePriceChange(ProductVariant $variant, float $oldPrice, float $oldSellingPrice): void
    {
        if ($variant->price != $oldPrice || $variant->selling_price != $oldSellingPrice) {
            Log::info("Variant Price Changed: SKU {$variant->sku} - HPP: {$oldPrice} -> {$variant->price}, Selling: {$oldSellingPrice} -> {$variant->selling_price}");

            // Additional price propagation business logic (e.g. recalculating bundle costs or logging price audit) can be triggered here.
        }
    }
}
