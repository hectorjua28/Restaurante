<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Corte de caja: cubre todos los pagos desde el corte anterior.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $period_start
 * @property Carbon $period_end
 * @property int $cash_cents
 * @property int $card_cents
 * @property int $transfer_cents
 * @property int $tips_cents
 * @property int $expected_cash_cents
 * @property int $counted_cash_cents
 * @property int $difference_cents
 * @property string|null $notes
 */
class CashClosing extends Model
{
    protected $fillable = [
        'user_id', 'period_start', 'period_end', 'cash_cents', 'card_cents', 'transfer_cents',
        'tips_cents', 'expected_cash_cents', 'counted_cash_cents', 'difference_cents', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'cash_cents' => 'integer',
            'card_cents' => 'integer',
            'transfer_cents' => 'integer',
            'tips_cents' => 'integer',
            'expected_cash_cents' => 'integer',
            'counted_cash_cents' => 'integer',
            'difference_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
