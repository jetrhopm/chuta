<?php

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Vuelca la configuracion administrable de correo sobre el servicio de envio.
 *
 * El archivo de entorno solo aporta el respaldo de arranque: una vez capturado el
 * SMTP en el panel, esto es lo que decide por donde salen los correos, sin tener
 * que tocar el servidor.
 */
class ConfigureMailer
{
    public function __construct(private readonly MailSettingsRepository $repository) {}

    /**
     * @return bool Si se aplico una configuracion administrable utilizable.
     */
    public function apply(): bool
    {
        try {
            $settings = $this->repository->get();
        } catch (Throwable) {
            // Puede ocurrir antes de que existan las tablas, por ejemplo durante
            // las migraciones. Se deja la configuracion del entorno y se sigue.
            return false;
        }

        if (! $settings->isUsable()) {
            return false;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', $settings->toMailerConfig());
        Config::set('mail.from', [
            'address' => $settings->fromAddress,
            'name' => $settings->fromName,
        ]);

        // El servicio cachea los transportes ya construidos: sin olvidarlos,
        // seguiria enviando con la configuracion anterior.
        Mail::purge('smtp');

        return true;
    }
}
