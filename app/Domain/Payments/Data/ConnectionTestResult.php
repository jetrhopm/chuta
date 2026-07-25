<?php

namespace App\Domain\Payments\Data;

/**
 * Resultado de probar la conexion con un proveedor.
 *
 * La prueba usa siempre una consulta de lectura y nunca genera un cobro real. El
 * mensaje esta pensado para que un administrador entienda que arreglar sin tener
 * que leer un registro tecnico.
 */
readonly class ConnectionTestResult
{
    public function __construct(
        public bool $successful,
        public string $message,
        /**
         * Detalle util sin secretos: nunca lleva llaves ni tokens.
         *
         * @var array<string, mixed>
         */
        public array $details = [],
    ) {}

    public static function ok(string $message, array $details = []): self
    {
        return new self(true, $message, $details);
    }

    public static function failure(string $message, array $details = []): self
    {
        return new self(false, $message, $details);
    }
}
