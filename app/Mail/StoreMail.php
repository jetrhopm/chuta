<?php

namespace App\Mail;

use App\Domain\Notifications\ConfigureMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Base de todos los correos de la tienda.
 *
 * Se envian siempre en cola: si el servidor de correo esta caido o tarda, el
 * pedido ya quedo guardado y el correo se reintenta despues en lugar de hacer
 * esperar al cliente o, peor, hacer fallar su compra.
 *
 * La configuracion administrable se aplica justo antes de armar el mensaje,
 * porque el trabajo se ejecuta en otro proceso que no vio la pantalla del panel.
 */
abstract class StoreMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Reintentos con espera creciente: un servidor de correo saturado suele
     * recuperarse en minutos, y insistir de inmediato solo lo empeora.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public int $tries = 4;

    public function __construct()
    {
        app(ConfigureMailer::class)->apply();
    }
}
