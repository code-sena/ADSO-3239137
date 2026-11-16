<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class TechnicalAidCategory extends Model
{
    public function technicalAids(): HasMany
    {
        return $this->hasMany(TechnicalAid::class, 'category_id');
    }
}