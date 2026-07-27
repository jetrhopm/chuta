<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Data\MailSettings;
use App\Domain\Settings\SettingsRepository;

class MailSettingsRepository
{
    /**
     * La unica clave que se cifra.
     */
    private const SECRET = 'password';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function get(): MailSettings
    {
        return MailSettings::fromArray($this->settings->all(MailSettings::GROUP));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        $password = $values[self::SECRET] ?? null;
        unset($values[self::SECRET]);

        $this->settings->setMany(MailSettings::GROUP, $values);

        // Una contrasena vacia significa "no la cambies": el panel la muestra
        // enmascarada, asi que guardar lo que aparece en pantalla borraria la
        // credencial buena.
        if (is_string($password) && $password !== '') {
            $this->settings->set(MailSettings::GROUP, self::SECRET, $password, encrypted: true);
        }
    }

    /**
     * Valores para el panel, con la contrasena enmascarada.
     *
     * @return array<string, mixed>
     */
    public function forDisplay(): array
    {
        $values = $this->settings->all(MailSettings::GROUP);

        if (($values[self::SECRET] ?? '') !== '') {
            $values[self::SECRET] = str_repeat('*', 12);
        }

        return $values;
    }

    public function forget(): void
    {
        $this->settings->set(MailSettings::GROUP, 'enabled', false);
        $this->settings->set(MailSettings::GROUP, self::SECRET, null);
    }
}
