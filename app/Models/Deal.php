<?php

namespace App\Models;

use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'title', 'value', 'stage',
        'expected_close_date', 'description', 'lost_reason',
    ];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('stage', DealStage::openValues());
    }
}
