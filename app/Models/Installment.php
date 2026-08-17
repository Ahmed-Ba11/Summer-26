<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Installment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'reason',
        'icon',
        'monthly_amount',
        'total_amount',
        'paid_months',
        'total_months',
        'start_date',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'integer',
            'total_amount' => 'integer',
            'paid_months' => 'integer',
            'total_months' => 'integer',
            'is_completed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
