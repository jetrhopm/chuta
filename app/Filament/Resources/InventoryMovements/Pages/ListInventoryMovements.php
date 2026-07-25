<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    /**
     * Sin accion de creacion: el historial no se escribe a mano.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
