<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Data\ConnectionTestResult;
use App\Domain\Payments\Data\PaymentRequestData;
use App\Domain\Payments\Data\PaymentResult;
use App\Domain\Payments\Data\PaymentStatusResult;
use App\Domain\Payments\Data\RefundRequestData;
use App\Domain\Payments\Data\RefundResult;
use App\Domain\Payments\Enums\PaymentProvider;

/**
 * Contrato comun de los metodos de pago.
 *
 * No todos los proveedores pueden hacer lo mismo, y el contrato no finge que si:
 * `supportsRefunds()` permite preguntar antes de ofrecer un reembolso, en lugar
 * de intentarlo y fallar. La transferencia bancaria, por ejemplo, no reembolsa
 * por API porque la devolucion la hace una persona en el banco.
 */
interface PaymentGateway
{
    public function provider(): PaymentProvider;

    /**
     * Si el metodo esta activo y con credenciales suficientes para operar.
     *
     * Un proveedor mal configurado se queda fuera del checkout sin tumbar los
     * demas.
     */
    public function isAvailable(): bool;

    public function isSandbox(): bool;

    public function createPayment(PaymentRequestData $data): PaymentResult;

    /**
     * Consulta el estado real al proveedor.
     *
     * Es la fuente de verdad del pago. El retorno del navegador no lo es: nunca
     * se marca un pedido como pagado por un parametro de la URL de vuelta.
     */
    public function queryPayment(string $externalId): PaymentStatusResult;

    public function supportsRefunds(): bool;

    public function refund(RefundRequestData $data): RefundResult;

    /**
     * Comprueba las credenciales con una operacion de solo lectura.
     *
     * Nunca debe generar un cobro real.
     */
    public function testConnection(): ConnectionTestResult;

    /**
     * Valida que un webhook viene de verdad del proveedor.
     *
     * Un webhook sin verificar es una puerta abierta para que cualquiera marque
     * pedidos como pagados.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, string|null>>  $headers
     */
    public function verifyWebhook(string $rawBody, array $payload, array $headers): bool;

    /**
     * Extrae del webhook el identificador externo del pago.
     *
     * @param  array<string, mixed>  $payload
     */
    public function externalIdFromWebhook(array $payload): ?string;
}
