<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditTrailService
{
    // Model bawaan Laravel yang selalu di-exclude, tidak perlu dicatat & mencegah infinite loop
    protected static array $alwaysExcluded = [
        AuditTrail::class,
        \Illuminate\Notifications\DatabaseNotification::class,
    ];

    public static function record(string $event, Model $model): void
    {
        if (! config('audit_trail.enabled', true)) {
            return;
        }

        if (in_array(get_class($model), self::excludedModels(), true)) {
            return;
        }

        try {
            [$old, $new] = self::resolveValues($event, $model);

            AuditTrail::create([
                'user_id'        => Auth::id(),
                'user_name'      => Auth::check() ? (Auth::user()->name ?? null) : null,
                'event'          => $event,
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->getKey(),
                'old_values'     => $old,
                'new_values'     => $new,
                'url'            => app()->runningInConsole() ? null : request()->fullUrl(),
                'ip_address'     => app()->runningInConsole() ? null : request()->ip(),
                'user_agent'     => app()->runningInConsole() ? null : substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Jangan sampai pencatatan audit trail membuat proses utama gagal
            report($e);
        }
    }

    protected static function excludedModels(): array
    {
        return array_merge(self::$alwaysExcluded, config('audit_trail.excluded_models', []));
    }

    protected static function resolveValues(string $event, Model $model): array
    {
        $hidden = array_merge($model->getHidden(), config('audit_trail.hidden_fields', []));

        return match ($event) {
            'created' => [null, self::filterHidden($model->getAttributes(), $hidden)],
            'updated' => [
                self::filterHidden($model->getOriginal(), $hidden, array_keys($model->getChanges())),
                self::filterHidden($model->getChanges(), $hidden),
            ],
            'deleted' => [self::filterHidden($model->getOriginal(), $hidden), null],
            default => [null, null],
        };
    }

    protected static function filterHidden(array $attributes, array $hidden, ?array $onlyKeys = null): array
    {
        if ($onlyKeys !== null) {
            $attributes = array_intersect_key($attributes, array_flip($onlyKeys));
        }

        foreach ($hidden as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }
}
