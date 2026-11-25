<?php

namespace App\Actions\Admin;

use App\Models\Product;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Crea o edita un platillo junto con sus modificadores. Registra la edición en la bitácora.
 * Cambiar el precio no afecta las órdenes ya enviadas (cada renglón guarda su precio).
 */
class SaveProduct
{
    /**
     * @param  array{category_id:int, name:string, description:?string, price_cents:int, station:string, available:bool}  $data
     * @param  list<array{id:?int, name:string, price_delta_cents:int}>  $modifiers
     */
    public function handle(?Product $product, array $data, array $modifiers, User $by): Product
    {
        return DB::transaction(function () use ($product, $data, $modifiers, $by) {
            $isNew = $product === null;
            $product ??= new Product;
            $product->fill($data)->save();

            $modifierLog = $this->syncModifiers($product, $modifiers);

            if ($isNew) {
                Audit::log('product.created', $product, Audit::attributes($product) + ['modificadores' => $modifierLog], $by->id);
            } else {
                $changes = Audit::changes($product);
                if ($changes !== [] || $modifierLog !== []) {
                    Audit::log('product.updated', $product, ['cambios' => $changes, 'modificadores' => $modifierLog], $by->id);
                }
            }

            return $product->load('modifiers', 'category');
        });
    }

    /**
     * @param  list<array{id:?int, name:string, price_delta_cents:int}>  $modifiers
     * @return list<string>
     */
    private function syncModifiers(Product $product, array $modifiers): array
    {
        $log = [];
        $keep = [];
        $existing = $product->modifiers()->get()->keyBy('id');

        foreach ($modifiers as $row) {
            $id = $row['id'] ?? null;
            $model = $id !== null ? $existing->get($id) : null;
            if ($model) {
                $model->fill(['name' => $row['name'], 'price_delta_cents' => $row['price_delta_cents']]);
                if ($model->isDirty()) {
                    $log[] = "editado: {$model->name}";
                }
                $model->save();
            } else {
                $model = $product->modifiers()->create(['name' => $row['name'], 'price_delta_cents' => $row['price_delta_cents']]);
                $log[] = "agregado: {$model->name}";
            }
            $keep[] = $model->id;
        }

        foreach ($existing->except($keep) as $removed) {
            $log[] = "eliminado: {$removed->name}";
            $removed->delete();
        }

        return $log;
    }
}
