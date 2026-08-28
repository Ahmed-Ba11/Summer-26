<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitmentPayment extends Model
{
    protected $fillable = [
        'commitment_id',
        'amount',
        'paid_at',
        // تاريخ استحقاق الظهور المسدَّد — هوية الدفعة الحقيقية.
        // `period_key` يبقى مشتقّاً منه للتجميع والعرض.
        'due_date',
        'period_key',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'date',
            'due_date' => 'date',
        ];
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }
}
