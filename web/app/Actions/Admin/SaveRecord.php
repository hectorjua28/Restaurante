<?php

namespace App\Actions\Admin;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y baja de catálogos sencillos (categorías y mesas) con registro en la bitácora.
 * El nombre de la acción en la bitácora se arma con el prefijo: "category.created", "table.deleted", etc.
 */
class SaveRecord
{
    /**
     * @template T of Model
     *
     * @param  T  $model
     * @param  array<string, mixed>  $data
     * @return T
     */
    public function save(Model $model, array $data, string $prefix, User $by): Model
    {
        return DB::transaction(function () use ($model, $data, $prefix, $by) {
            $isNew = ! $model->exists;
            $model->fill($data)->save();

            if ($isNew) {
                Audit::log("{$prefix}.created", $model, Audit::attributes($model), $by->id);
            } elseif (($changes = Audit::changes($model)) !== []) {
                Audit::log("{$prefix}.updated", $model, ['cambios' => $changes], $by->id);
            }

            return $model;
        });
    }

    /**
     * Borra el registro. Si otros datos dependen de él (llave foránea), explica por qué no se puede.
     */
    public function delete(Model $model, string $prefix, User $by, string $inUseMessage): void
    {
        try {
            DB::transaction(function () use ($model, $prefix, $by) {
                $snapshot = Audit::attributes($model);
                $model->delete();
                Audit::log("{$prefix}.deleted", $model, $snapshot, $by->id);
            });
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', '23503'], true) || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                throw new BusinessRuleException($inUseMessage, 'in_use');
            }
            throw $e;
        }
    }
}
