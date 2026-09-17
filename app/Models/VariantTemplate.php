<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_name',
        'name',
    ];

    protected $appends = ['name'];

    public function getNameAttribute(): ?string
    {
        return $this->attributes['template_name'] ?? $this->attributes['name'] ?? null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(VariantTemplateItem::class);
    }
}
