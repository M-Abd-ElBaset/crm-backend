<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'industry', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['status' => CustomerStatus::class];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
