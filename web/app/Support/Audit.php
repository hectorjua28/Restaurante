<?php

namespace App\Support;

use App\Models\AuditLog;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

final class Audit
{
    /** Campos que nunca se guardan en la bitácora. */
    private const HIDDEN = ['password', 'remember_token'];

    private const IGNORED = ['created_at', 'updated_at'];

    /** @param array<string, mixed> $data */
    public static function log(string $action, ?Model $subject = null, array $data = [], ?int $userId = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Cambios de un modelo recién guardado: ['campo' => ['antes' => x, 'después' => y]].
     * Llamar después de save(); usa getChanges() y getPrevious().
     *
     * @return array<string, array{antes: mixed, después: mixed}>
     */
    public static function changes(Model $model): array
    {
        $diff = [];
        foreach ($model->getChanges() as $key => $new) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }
            $old = $model->getPrevious()[$key] ?? null;
            $hidden = in_array($key, self::HIDDEN, true);
            $diff[$key] = [
                'antes' => $hidden ? '***' : self::scalar($old),
                'después' => $hidden ? '***' : self::scalar($new),
            ];
        }

        return $diff;
    }

    /**
     * Atributos de un modelo recién creado (sin campos ocultos).
     *
     * @return array<string, mixed>
     */
    public static function attributes(Model $model): array
    {
        return collect($model->getAttributes())
            ->except([...self::HIDDEN, ...self::IGNORED])
            ->map(fn ($v) => self::scalar($v))
            ->all();
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
