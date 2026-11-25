<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property int $price_delta_cents
 */
class Modifier extends Model
{
    protected $fillable = ['product_id', 'name', 'price_delta_cents'];

    protected function casts(): array
    {
        return ['price_delta_cents' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
