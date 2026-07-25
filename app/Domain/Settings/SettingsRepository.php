<?php

namespace App\Domain\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Acceso a la configuracion administrable.
 *
 * Se lee en casi cada peticion, asi que cada grupo se cachea completo y la
 * cache se invalida al escribir. Las credenciales se guardan cifradas y nunca
 * salen de aqui en claro salvo que se pidan expresamente.
 */
class SettingsRepository
{
    private const CACHE_PREFIX = 'settings:';

    /**
     * @return array<string, mixed>
     */
    public function all(string $group): array
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$group, function () use ($group): array {
            return Setting::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key => $this->decode($setting),
                ])
                ->all();
        });
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->all($group)[$key] ?? $default;
    }

    public function set(string $group, string $key, mixed $value, bool $encrypted = false): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $encrypted ? Crypt::encryptString((string) $value) : $value,
                'is_encrypted' => $encrypted,
            ],
        );

        $this->forget($group);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(string $group, array $values, bool $encrypted = false): void
    {
        // En una transaccion para que un fallo a medias no deje el grupo con
        // unos valores nuevos y otros viejos.
        DB::transaction(function () use ($group, $values, $encrypted): void {
            foreach ($values as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    [
                        'value' => $encrypted ? Crypt::encryptString((string) $value) : $value,
                        'is_encrypted' => $encrypted,
                    ],
                );
            }
        });

        $this->forget($group);
    }

    /**
     * Escribe solo los valores que aun no existen.
     *
     * Es lo que usan los seeders: sembrar de nuevo no debe pisar lo que el
     * administrador haya ajustado desde el panel.
     *
     * @param  array<string, mixed>  $values
     */
    public function seedMissing(string $group, array $values): void
    {
        $existing = Setting::query()
            ->where('group', $group)
            ->pluck('key')
            ->all();

        $missing = array_diff_key($values, array_flip($existing));

        if ($missing !== []) {
            $this->setMany($group, $missing);
        }
    }

    public function forget(string $group): void
    {
        Cache::forget(self::CACHE_PREFIX.$group);
    }

    private function decode(Setting $setting): mixed
    {
        if (! $setting->is_encrypted) {
            return $setting->value;
        }

        try {
            return Crypt::decryptString((string) $setting->value);
        } catch (DecryptException) {
            // Pasa cuando cambia APP_KEY. Devolver null deja la integracion
            // desconfigurada en lugar de tumbar la tienda entera, y queda
            // constancia para que el administrador la vuelva a capturar.
            Log::warning('No se pudo descifrar una configuracion.', [
                'group' => $setting->group,
                'key' => $setting->key,
            ]);

            return null;
        }
    }
}
