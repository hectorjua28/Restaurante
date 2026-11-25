<?php

namespace App\Actions\Admin;

use App\Models\Product;
use App\Models\User;
use App\Support\Audit;

/**
 * Baja lógica (soft delete): el platillo deja de aparecer en el menú, pero las órdenes
 * y los reportes históricos lo conservan.
 */
class DeleteProduct
{
    public function handle(Product $product, User $by): void
    {
        $product->delete();
        Audit::log('product.deleted', $product, ['name' => $product->name], $by->id);
    }
}
