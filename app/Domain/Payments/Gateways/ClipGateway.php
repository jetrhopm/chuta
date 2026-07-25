<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Data\ConnectionTestResult;
use App\Domain\Payments\Data\PaymentRequestData;
use App\Domain\Payments\Data\PaymentResult;
use App\Domain\Payments\Data\PaymentStatusResult;
use App\Domain\Payments\Data\RefundRequestData;
use App\Domain\Payments\Data\RefundResult;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Settings\GatewaySettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checkout redireccionado de Clip.
 *
 * Documentacion oficial consultada el 25 de julio de 2026:
 * - https://developer.clip.mx/docs/api-de-checkout
 * - https://developer.clip.mx/reference/createnewpaymentlink
 * - https://developer.clip.mx/docs/autenticacion
 *
 * Lo que la documentacion establece y de donde salen las decisiones de aqui:
 * - Se crea un enlace de pago con POST a /v2/checkout.
 * - La autenticacion es Basic con el base64 de "api_key:secret_key".
 * - El importe se envia en unidades con dos decimales, no en centavos.
 * - Se puede indicar una URL de webhook y un objeto de redireccion.
 *
 * No se implementa el Checkout Transparente: exigiria certificacion PCI vigente
 * porque los datos de la tarjeta pasarian por este servidor.
 */
class ClipGateway implements PaymentGateway
{
    private const BASE_URL = 'https://api.payclip.com';

    private const TIMEOUT_SECONDS = 25;

    public function __construct(private readonly GatewaySettings $settings) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Clip;
    }

    public function isAvailable(): bool
    {
        return $this->settings->isEnabled($this->provider())
            && $this->settings->hasSecret($this->provider(), 'api_key')
            && $this->settings->hasSecret($this->provider(), 'secret_key');
    }

    public function isSandbox(): bool
    {
        return $this->settings->isSandbox($this->provider());
    }

    public function createPayment(PaymentRequestData $data): PaymentResult
    {
        $payload = [
            // En unidades y con dos decimales, como pide la documentacion.
            'amount' => (float) $data->amountAsDecimalString(),
            'currency' => strtoupper($data->currency),
            'purchase_description' => mb_substr($data->description, 0, 250),
            'redirection_url' => [
                'success' => $data->returnUrl,
                'error' => $data->cancelUrl,
                'default' => $data->returnUrl,
            ],
            'webhook_url' => $data->webhookUrl,
            'metadata' => [
                // El folio viaja de ida y vuelta para poder reconciliar el aviso
                // con el pedido sin depender del orden de llegada.
                'order_code' => $data->order->code,
                'idempotency_key' => $data->idempotencyKey,
            ],
        ];

        try {
            $response = $this->request()->post('/v2/checkout', $payload);
        } catch (ConnectionException) {
            return new PaymentResult(
                status: PaymentStatus::Rejected,
                failureReason: 'No pudimos comunicarnos con el proveedor de pago. Intenta nuevamente o elige otro metodo.',
                snapshot: ['error' => 'connection'],
            );
        }

        if (! $response->successful()) {
            $this->logFailure('createPayment', $response);

            return new PaymentResult(
                status: PaymentStatus::Rejected,
                failureReason: 'No pudimos generar el pago. Intenta nuevamente o elige otro metodo.',
                snapshot: $this->safeSnapshot($response),
            );
        }

        $body = $response->json();

        $checkoutUrl = $body['payment_request_url']
            ?? $body['payment_url']
            ?? $body['url']
            ?? null;

        $externalId = $body['payment_request_id'] ?? $body['id'] ?? null;

        // Sin identificador o sin direccion no hay forma de cobrar ni de volver a
        // consultar el estado, asi que se trata como fallo en lugar de mandar al
        // cliente a ninguna parte.
        if ($checkoutUrl === null || $externalId === null) {
            Log::warning('Clip respondio sin enlace de pago utilizable.', [
                'claves' => array_keys((array) $body),
            ]);

            return new PaymentResult(
                status: PaymentStatus::Rejected,
                failureReason: 'No pudimos generar el pago. Intenta nuevamente o elige otro metodo.',
                snapshot: $this->safeSnapshot($response),
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Pending,
            externalId: (string) $externalId,
            checkoutUrl: (string) $checkoutUrl,
            snapshot: $this->safeSnapshot($response),
        );
    }

    public function queryPayment(string $externalId): PaymentStatusResult
    {
        try {
            $response = $this->request()->get("/v2/checkout/{$externalId}");
        } catch (ConnectionException) {
            // Se devuelve "procesando" y no "rechazado": no saber el estado no es
            // lo mismo que saber que fallo, y marcarlo como rechazado cancelaria
            // pagos buenos.
            return new PaymentStatusResult(
                status: PaymentStatus::Processing,
                externalId: $externalId,
                failureReason: 'No pudimos consultar el estado del pago.',
            );
        }

        if (! $response->successful()) {
            $this->logFailure('queryPayment', $response);

            return new PaymentStatusResult(
                status: PaymentStatus::Processing,
                externalId: $externalId,
                failureReason: 'No pudimos consultar el estado del pago.',
                snapshot: $this->safeSnapshot($response),
            );
        }

        $body = (array) $response->json();
        $amount = $body['amount'] ?? null;

        return new PaymentStatusResult(
            status: $this->mapStatus((string) ($body['status'] ?? '')),
            externalId: (string) ($body['payment_request_id'] ?? $body['id'] ?? $externalId),
            // De unidades a centavos, redondeando para no arrastrar el error del
            // punto flotante al comparar importes.
            amountCents: $amount === null ? null : (int) round(((float) $amount) * 100),
            currency: isset($body['currency']) ? strtoupper((string) $body['currency']) : null,
            snapshot: $this->safeSnapshot($response),
        );
    }

    public function supportsRefunds(): bool
    {
        // Depende de la cuenta y del contrato, asi que se ofrece solo cuando el
        // administrador confirma que su cuenta lo permite.
        return (bool) $this->settings->get($this->provider(), 'refunds_enabled', false);
    }

    public function refund(RefundRequestData $data): RefundResult
    {
        if (! $this->supportsRefunds()) {
            return RefundResult::failed('Los reembolsos por API no estan habilitados para esta cuenta de Clip.');
        }

        try {
            $response = $this->request()
                ->withHeaders(['x-idempotency-key' => $data->idempotencyKey ?? ''])
                ->post('/v2/refunds', [
                    'payment_id' => $data->attempt->external_id,
                    'amount' => (float) $data->amountAsDecimalString(),
                    'reason' => $data->reason,
                ]);
        } catch (ConnectionException) {
            return RefundResult::failed('No pudimos comunicarnos con Clip para el reembolso.');
        }

        if (! $response->successful()) {
            $this->logFailure('refund', $response);

            return RefundResult::failed('Clip rechazo el reembolso. Revisa el detalle en su panel.');
        }

        return new RefundResult(
            successful: true,
            externalId: (string) ($response->json('id') ?? ''),
            refundedCents: $data->amountCents,
            snapshot: $this->safeSnapshot($response),
        );
    }

    public function testConnection(): ConnectionTestResult
    {
        if (! $this->settings->hasSecret($this->provider(), 'api_key')
            || ! $this->settings->hasSecret($this->provider(), 'secret_key')) {
            return ConnectionTestResult::failure('Falta capturar la API key o la clave secreta.');
        }

        try {
            // Una consulta de lectura sobre un identificador que no existe: sirve
            // para distinguir credenciales malas de credenciales buenas sin
            // generar ningun cobro.
            $response = $this->request()->get('/v2/checkout/prueba-de-conexion-'.bin2hex(random_bytes(6)));
        } catch (ConnectionException) {
            return ConnectionTestResult::failure('No pudimos conectar con Clip. Revisa la salida a internet del servidor.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return ConnectionTestResult::failure('Clip rechazo las credenciales. Revisa la API key y la clave secreta.');
        }

        // Un 404 es la respuesta esperada: la peticion se autentico y el
        // identificador simplemente no existe.
        if ($response->status() === 404 || $response->successful()) {
            return ConnectionTestResult::ok(
                $this->isSandbox()
                    ? 'Conexion correcta con Clip en modo pruebas.'
                    : 'Conexion correcta con Clip en modo produccion.',
                ['http_status' => $response->status()],
            );
        }

        return ConnectionTestResult::failure(
            "Clip respondio de forma inesperada (codigo {$response->status()}).",
            ['http_status' => $response->status()],
        );
    }

    public function verifyWebhook(string $rawBody, array $payload, array $headers): bool
    {
        $secret = $this->settings->get($this->provider(), 'webhook_secret');

        // Sin secreto configurado no se puede verificar nada, y aceptar el aviso
        // a ciegas permitiria a cualquiera marcar pedidos como pagados.
        if (! is_string($secret) || $secret === '') {
            Log::warning('Llego un webhook de Clip pero no hay secreto configurado.');

            return false;
        }

        $signature = $this->header($headers, 'x-clip-signature')
            ?? $this->header($headers, 'clip-signature');

        if ($signature === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        // Comparacion en tiempo constante: con == se podria adivinar la firma
        // midiendo cuanto tarda en responder.
        return hash_equals($expected, $signature);
    }

    public function externalIdFromWebhook(array $payload): ?string
    {
        $id = $payload['payment_request_id']
            ?? $payload['id']
            ?? $payload['data']['payment_request_id']
            ?? $payload['data']['id']
            ?? null;

        return $id === null ? null : (string) $id;
    }

    private function request()
    {
        $apiKey = (string) $this->settings->get($this->provider(), 'api_key', '');
        $secretKey = (string) $this->settings->get($this->provider(), 'secret_key', '');

        return Http::baseUrl(self::BASE_URL)
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->asJson()
            // Basic con el base64 de "api_key:secret_key", como indica la
            // documentacion de autenticacion.
            ->withHeaders([
                'Authorization' => 'Basic '.base64_encode($apiKey.':'.$secretKey),
            ]);
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match (strtolower($status)) {
            'paid', 'approved', 'completed', 'succeeded' => PaymentStatus::Approved,
            'pending', 'created', 'processing' => PaymentStatus::Pending,
            'rejected', 'declined', 'failed' => PaymentStatus::Rejected,
            'cancelled', 'canceled' => PaymentStatus::Cancelled,
            'expired' => PaymentStatus::Expired,
            'refunded' => PaymentStatus::Refunded,
            'chargeback' => PaymentStatus::Chargeback,
            // Un estado que no se reconoce se trata como "sigue en curso": es lo
            // unico que no cierra el pago por error.
            default => PaymentStatus::Processing,
        };
    }

    /**
     * Fotografia de la respuesta sin secretos ni datos de tarjeta.
     *
     * @return array<string, mixed>
     */
    private function safeSnapshot(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            return ['http_status' => $response->status()];
        }

        // Lista blanca en lugar de lista negra: si el proveedor agrega un campo
        // sensible en el futuro, no se guarda por descuido.
        $permitidas = ['id', 'payment_request_id', 'status', 'amount', 'currency', 'created_at', 'expires_at', 'message', 'error'];

        return array_merge(
            ['http_status' => $response->status()],
            array_intersect_key($body, array_flip($permitidas)),
        );
    }

    private function logFailure(string $operation, Response $response): void
    {
        // Se registra el codigo y nada del cuerpo: la respuesta puede traer datos
        // del pagador, y los registros no son el lugar para eso.
        Log::warning('Clip respondio con error.', [
            'operacion' => $operation,
            'http_status' => $response->status(),
        ]);
    }

    /**
     * @param  array<string, array<int, string|null>>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $values) {
            if (strtolower($key) === strtolower($name)) {
                return is_array($values) ? ($values[0] ?? null) : $values;
            }
        }

        return null;
    }
}
