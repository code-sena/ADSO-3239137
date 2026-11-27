<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['full_name', 'identification_number'])]
class Beneficiary extends Model
{
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
