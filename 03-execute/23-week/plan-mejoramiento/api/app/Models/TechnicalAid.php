<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidV4;
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
    use UsesUuidV4;

    /**
     * Get the category associated with this technical aid.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TechnicalAidCategory::class);
    }

    /**
     * Get the model attribute casting definitions.
     */
    protected function casts(): array
    {
    return [
            'total_units' => 'integer',
            'available_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
