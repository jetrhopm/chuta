<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Procesa un aviso de un proveedor de pago.
 *
 * Tres reglas gobiernan este flujo:
 *
 * 1. Se verifica la firma antes de mirar el contenido. Un webhook sin verificar
 *    seria una puerta abierta para que cualquiera marque pedidos como pagados.
 * 2. El contenido del aviso no se cree: solo se usa para saber de que pago habla.
 *    El estado se pregunta al proveedor, que es la fuente de verdad.
 * 3. El mismo aviso puede llegar varias veces, asi que se procesa una sola vez.
 */
class ProcessPaymentWebhook
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly SettlePayment $settlePayment,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string|null>>  $headers
     * @return bool Si el aviso se acepto para procesar.
     */
    public function handle(PaymentProvider $provider, string $rawBody, array $payload, array $headers): bool
    {
        $gateway = $this->registry->tryGet($provider);

        if ($gateway === null) {
            return false;
        }

        $firmaValida = $gateway->verifyWebhook($rawBody, $payload, $headers);
        $externalId = $gateway->externalIdFromWebhook($payload);

        // Se guarda incluso con firma invalida: un aviso falso es justo lo que
        // interesa poder revisar despues.
        $event = $this->recordEvent($provider, $payload, $externalId, $firmaValida);

        if ($event === null) {
            // Ya se habia recibido este mismo aviso.
            return true;
        }

        if (! $firmaValida) {
            Log::warning('Se descarto un webhook de pago con firma invalida.', [
                'provider' => $provider->value,
                'external_id' => $externalId,
            ]);

            return false;
        }

        if ($externalId === null) {
            Log::warning('Llego un webhook de pago sin identificador utilizable.', [
                'provider' => $provider->value,
            ]);

            return false;
        }

        $attempt = PaymentAttempt::query()
            ->where('provider', $provider->value)
            ->where('external_id', $externalId)
            ->latest('id')
            ->first();

        if ($attempt === null) {
            Log::warning('Llego un webhook de un pago que no existe aqui.', [
                'provider' => $provider->value,
                'external_id' => $externalId,
            ]);

            return false;
        }

        // El estado sale de preguntarle al proveedor, no del cuerpo del aviso: el
        // aviso solo dice de que pago se trata.
        $estado = $gateway->queryPayment($externalId);

        $this->settlePayment->handle($attempt, $estado);

        $event->forceFill([
            'payment_attempt_id' => $attempt->getKey(),
            'processed_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Registra el aviso, o devuelve null si ya se habia recibido.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        PaymentProvider $provider,
        array $payload,
        ?string $externalId,
        bool $signatureValid,
    ): ?PaymentEvent {
        $eventId = $this->eventId($payload, $externalId);

        try {
            return PaymentEvent::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $this->eventType($payload),
                'external_id' => $externalId,
                'signature_valid' => $signatureValid,
                'payload' => $this->safePayload($payload),
            ]);
        } catch (QueryException $exception) {
            // La clave unica de (proveedor, evento) es lo que hace idempotente el
            // webhook: si el aviso repetido choca contra ella, ya se proceso.
            if ($this->isDuplicate($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload, ?string $externalId): string
    {
        $id = $payload['event_id'] ?? $payload['id'] ?? $payload['data']['id'] ?? null;

        if (is_string($id) || is_int($id)) {
            return (string) $id;
        }

        // Sin identificador propio se deriva uno del contenido, para que un aviso
        // idéntico repetido siga contando como el mismo.
        return substr(hash('sha256', json_encode($payload).($externalId ?? '')), 0, 48);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventType(array $payload): ?string
    {
        $type = $payload['type'] ?? $payload['event'] ?? $payload['event_type'] ?? null;

        return is_string($type) ? mb_substr($type, 0, 120) : null;
    }

    /**
     * Quita del aviso lo que no debe quedar guardado.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        $prohibidas = ['card', 'card_number', 'cvv', 'cvc', 'pan', 'security_code', 'token', 'access_token'];

        foreach ($prohibidas as $clave) {
            unset($payload[$clave]);

            if (isset($payload['data']) && is_array($payload['data'])) {
                unset($payload['data'][$clave]);
            }
        }

        return $payload;
    }

    private function isDuplicate(QueryException $exception): bool
    {
        // 23000 es la violacion de restriccion de integridad en SQLSTATE.
        return $exception->getCode() === '23000';
    }
}
