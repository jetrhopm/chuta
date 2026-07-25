<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Gateways\BankTransferGateway;
use App\Domain\Payments\Gateways\ClipGateway;
use InvalidArgumentException;

/**
 * Punto unico para obtener el adaptador de un metodo de pago.
 *
 * Un proveedor mal configurado o caido no debe arrastrar a los demas: el
 * checkout pregunta aqui que metodos estan disponibles y ofrece solo esos, de
 * modo que si Clip falla la transferencia sigue funcionando.
 */
class PaymentGatewayRegistry
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private const GATEWAYS = [
        PaymentProvider::Clip->value => ClipGateway::class,
        PaymentProvider::BankTransfer->value => BankTransferGateway::class,
    ];

    public function get(PaymentProvider $provider): PaymentGateway
    {
        $class = self::GATEWAYS[$provider->value] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("El metodo de pago {$provider->value} todavia no tiene adaptador.");
        }

        return app($class);
    }

    public function tryGet(PaymentProvider $provider): ?PaymentGateway
    {
        return array_key_exists($provider->value, self::GATEWAYS)
            ? $this->get($provider)
            : null;
    }

    /**
     * Proveedores con adaptador, tengan o no credenciales.
     *
     * @return array<int, PaymentGateway>
     */
    public function all(): array
    {
        return array_map(
            fn (string $value): PaymentGateway => $this->get(PaymentProvider::from($value)),
            array_keys(self::GATEWAYS),
        );
    }

    /**
     * Metodos que se pueden ofrecer al cliente ahora mismo.
     *
     * @return array<int, PaymentGateway>
     */
    public function available(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (PaymentGateway $gateway): bool => $gateway->isAvailable(),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function availableProviderValues(): array
    {
        return array_map(
            fn (PaymentGateway $gateway): string => $gateway->provider()->value,
            $this->available(),
        );
    }

    public function isAvailable(PaymentProvider $provider): bool
    {
        return $this->tryGet($provider)?->isAvailable() ?? false;
    }

    /**
     * Proveedores que todavia no tienen adaptador.
     *
     * Se expone para que el panel pueda decirlo con claridad en lugar de
     * mostrarlos como si estuvieran listos.
     *
     * @return array<int, PaymentProvider>
     */
    public function pending(): array
    {
        return array_values(array_filter(
            PaymentProvider::cases(),
            fn (PaymentProvider $provider): bool => ! array_key_exists($provider->value, self::GATEWAYS),
        ));
    }
}
