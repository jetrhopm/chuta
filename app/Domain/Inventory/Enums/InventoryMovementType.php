<?php

namespace App\Domain\Inventory\Enums;

enum InventoryMovementType: string
{
    case InitialLoad = 'initial_load';
    case Entry = 'entry';
    case Sale = 'sale';
    case Cancellation = 'cancellation';
    case CustomerReturn = 'customer_return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::InitialLoad => 'Carga inicial',
            self::Entry => 'Entrada',
            self::Sale => 'Venta',
            self::Cancellation => 'Cancelacion',
            self::CustomerReturn => 'Devolucion',
            self::Adjustment => 'Ajuste',
        };
    }

    /**
     * Si el tipo suma o resta existencias.
     *
     * El ajuste queda fuera a proposito: es el unico que puede ir en cualquiera
     * de los dos sentidos, y quien lo registra decide el signo.
     */
    public function isIncoming(): ?bool
    {
        return match ($this) {
            self::InitialLoad, self::Entry, self::Cancellation, self::CustomerReturn => true,
            self::Sale => false,
            self::Adjustment => null,
        };
    }
}
