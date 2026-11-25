<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;
use App\Support\Audit;

/**
 * Marca un platillo como agotado o disponible. Solo afecta comandas nuevas.
 */
class SetProductAvailability
{
    public function handle(Product $product, bool $available, User $by): Product
    {
        if ($product->available === $available) {
            return $product;
        }

        $product->update(['available' => $available]);
        Audit::log($available ? 'product.available' : 'product.sold_out', $product, ['name' => $product->name], $by->id);

        return $product;
    }
}
