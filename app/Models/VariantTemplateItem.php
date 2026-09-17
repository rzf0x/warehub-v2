<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_template_id',
        'size',
        'color',
    ];

    public function variantTemplate(): BelongsTo
    {
        return $this->belongsTo(VariantTemplate::class);
    }
}
