<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'category_id',
    'reference',
    'total_units',
    'available_units',
    'is_active',
])]
class TechnicalAid extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(TechnicalAidCategory::class);
    }
}