<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class TechnicalAidCategory extends Model
{
    /**
     * Get the technical aids associated with this category.
     */
    public function technicalAids(): HasMany
    {
        return $this->hasMany(TechnicalAid::class, 'category_id');
    }

    /**
     * Get the model attribute casting definitions.
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }
}