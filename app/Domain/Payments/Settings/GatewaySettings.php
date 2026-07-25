<?php

namespace App\Domain\Payments\Settings;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Settings\SettingsRepository;

/**
 * Credenciales y opciones de un proveedor de pago.
 *
 * Las credenciales se guardan cifradas y se leen solo cuando hace falta llamar
 * al proveedor. Para la interfaz se entregan enmascaradas: el panel debe poder
 * mostrar que hay una llave configurada sin devolverla completa al navegador.
 */
class GatewaySettings
{
    /**
     * Claves que se cifran al guardarse.
     */
    private const SECRETS = [
        'api_key',
        'secret_key',
        'access_token',
        'client_secret',
        'webhook_secret',
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function all(PaymentProvider $provider): array
    {
        return $this->settings->all($provider->settingsGroup());
    }

    public function get(PaymentProvider $provider, string $key, mixed $default = null): mixed
    {
        $value = $this->settings->get($provider->settingsGroup(), $key, $default);

        return $value === '' ? $default : $value;
    }

    public function isEnabled(PaymentProvider $provider): bool
    {
        return (bool) $this->get($provider, 'enabled', false);
    }

    public function isSandbox(PaymentProvider $provider): bool
    {
        // Por omision se asume ambiente de pruebas. Es la opcion prudente: para
        // cobrar de verdad hay que decirlo expresamente.
        return (bool) $this->get($provider, 'sandbox', true);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(PaymentProvider $provider, array $values): void
    {
        $group = $provider->settingsGroup();

        foreach ($values as $key => $value) {
            $esSecreto = in_array($key, self::SECRETS, strict: true);

            // Un secreto vacio significa "no lo cambies": el panel muestra los
            // secretos enmascarados, asi que guardar el valor de la pantalla
            // borraria la credencial buena.
            if ($esSecreto && ($value === null || $value === '')) {
                continue;
            }

            $this->settings->set($group, $key, $value, encrypted: $esSecreto);
        }
    }

    /**
     * Borra las credenciales guardadas y desactiva el proveedor.
     *
     * Se desactiva primero a proposito: dejarlo activo sin credenciales lo
     * ofreceria en el checkout para fallar despues. No pretende revocar nada en
     * el proveedor, solo olvidar lo que hay guardado aqui.
     */
    public function forget(PaymentProvider $provider): void
    {
        $group = $provider->settingsGroup();

        $this->settings->set($group, 'enabled', false);

        foreach (self::SECRETS as $key) {
            $this->settings->set($group, $key, null);
        }
    }

    /**
     * Valores para pintar en el panel, con los secretos enmascarados.
     *
     * @return array<string, mixed>
     */
    public function forDisplay(PaymentProvider $provider): array
    {
        $values = $this->all($provider);

        foreach (self::SECRETS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $values[$key] = $this->mask((string) $values[$key]);
        }

        return $values;
    }

    public function hasSecret(PaymentProvider $provider, string $key): bool
    {
        $value = $this->get($provider, $key);

        return is_string($value) && $value !== '';
    }

    /**
     * Deja ver solo los ultimos caracteres, lo justo para reconocer la llave.
     */
    private function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $visible = mb_substr($value, -4);

        return str_repeat('*', 12).$visible;
    }
}
