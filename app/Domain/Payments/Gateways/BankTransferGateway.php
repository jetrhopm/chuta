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

/**
 * Transferencia bancaria, revisada a mano.
 *
 * No hay API ni webhook: el pedido queda pendiente, el cliente sube un
 * comprobante y una persona del panel lo aprueba o lo rechaza. Cumple el mismo
 * contrato que los demas para que el checkout no tenga que distinguir casos.
 */
class BankTransferGateway implements PaymentGateway
{
    public function __construct(private readonly GatewaySettings $settings) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::BankTransfer;
    }

    public function isAvailable(): bool
    {
        // Sin datos bancarios el metodo no sirve de nada, y ofrecerlo dejaria al
        // cliente sin saber a donde transferir.
        return $this->settings->isEnabled($this->provider())
            && $this->clabe() !== null
            && $this->bank() !== null;
    }

    public function isSandbox(): bool
    {
        // No hay ambiente de pruebas: el dinero se mueve en el banco, no aqui.
        return false;
    }

    public function createPayment(PaymentRequestData $data): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::Pending,
            // El folio del pedido es la referencia: es lo que permite reconocer
            // el deposito en el estado de cuenta.
            externalId: $data->order->code,
            instructions: $this->instructions($data),
            snapshot: ['method' => 'bank_transfer'],
        );
    }

    public function queryPayment(string $externalId): PaymentStatusResult
    {
        // No hay a quien preguntar. El estado lo decide la revision del
        // comprobante, asi que aqui solo se informa que sigue pendiente.
        return new PaymentStatusResult(
            status: PaymentStatus::Pending,
            externalId: $externalId,
        );
    }

    public function supportsRefunds(): bool
    {
        // La devolucion se hace en el banco, no desde el sistema. Decir que si
        // aqui haria creer que el boton del panel devuelve el dinero.
        return false;
    }

    public function refund(RefundRequestData $data): RefundResult
    {
        return RefundResult::failed(
            'La transferencia se reembolsa desde el banco. Registra el reembolso en el pedido cuando lo hayas hecho.',
        );
    }

    public function testConnection(): ConnectionTestResult
    {
        $faltantes = [];

        if ($this->bank() === null) {
            $faltantes[] = 'banco';
        }

        if ($this->settings->get($this->provider(), 'account_holder') === null) {
            $faltantes[] = 'beneficiario';
        }

        if ($this->clabe() === null) {
            $faltantes[] = 'CLABE';
        }

        if ($faltantes !== []) {
            return ConnectionTestResult::failure('Falta capturar: '.implode(', ', $faltantes).'.');
        }

        $clabe = (string) $this->clabe();

        if (strlen($clabe) !== 18) {
            return ConnectionTestResult::failure('La CLABE debe tener 18 digitos.');
        }

        return ConnectionTestResult::ok('Los datos bancarios estan completos y se mostraran al cliente.');
    }

    public function verifyWebhook(string $rawBody, array $payload, array $headers): bool
    {
        // Este metodo no recibe avisos de nadie. Aceptar cualquier cosa aqui
        // permitiria marcar pedidos como pagados con una peticion inventada.
        return false;
    }

    public function externalIdFromWebhook(array $payload): ?string
    {
        return null;
    }

    private function instructions(PaymentRequestData $data): string
    {
        $lineas = [
            'Realiza una transferencia por '.$this->formatAmount($data->amountCents).' a:',
            'Banco: '.$this->bank(),
            'Beneficiario: '.$this->settings->get($this->provider(), 'account_holder'),
            'CLABE: '.$this->clabe(),
        ];

        if ($cuenta = $this->settings->get($this->provider(), 'account_number')) {
            $lineas[] = 'Cuenta: '.$cuenta;
        }

        $lineas[] = 'Referencia: '.$data->order->code;
        $lineas[] = 'Cuando termines, sube tu comprobante para que confirmemos el pedido.';

        if ($horas = (int) $this->settings->get($this->provider(), 'expires_in_hours', 0)) {
            $lineas[] = "Tienes {$horas} horas para completar el pago.";
        }

        if ($extra = $this->settings->get($this->provider(), 'instructions')) {
            $lineas[] = (string) $extra;
        }

        return implode("\n", $lineas);
    }

    private function bank(): ?string
    {
        $value = $this->settings->get($this->provider(), 'bank');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function clabe(): ?string
    {
        $value = $this->settings->get($this->provider(), 'clabe');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return preg_replace('/\D/', '', $value) ?: null;
    }

    private function formatAmount(int $cents): string
    {
        return '$'.number_format($cents / 100, 2).' MXN';
    }
}
