<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $table_id
 * @property int $user_id
 * @property PaymentMethod $method
 * @property int $amount_cents
 * @property int $tip_cents
 * @property int|null $cash_closing_id
 * @property Carbon $created_at
 */
class Payment extends Model
{
    protected $fillable = ['table_id', 'user_id', 'method', 'amount_cents', 'tip_cents', 'cash_closing_id', 'created_at'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount_cents' => 'integer',
            'tip_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Order, $this> */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class);
    }

    /** @return BelongsTo<CashClosing, $this> */
    public function cashClosing(): BelongsTo
    {
        return $this->belongsTo(CashClosing::class);
    }
}
