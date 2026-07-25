<?php

namespace App\Policies;

use App\Domain\Access\Enums\AdminPermission;
use App\Models\InventoryMovement;
use App\Models\User;

/**
 * El historial de inventario es de solo lectura.
 *
 * Los movimientos se generan desde las acciones del dominio, que son las que
 * mantienen cuadradas las existencias. Una correccion se hace registrando un
 * ajuste en sentido contrario, no reescribiendo el pasado.
 *
 * Esta Policy oculta las acciones de escritura en el panel, pero no es la
 * garantia: el superadministrador se salta cualquier Policy por el Gate::before
 * de AuthServiceProvider. Lo que hace imposible reescribir el historial es el
 * propio modelo InventoryMovement, que lanza una excepcion al intentar
 * modificarlo o borrarlo.
 */
class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::ViewInventoryMovements->value);
    }

    public function view(User $user, InventoryMovement $movement): bool
    {
        return $user->can(AdminPermission::ViewInventoryMovements->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InventoryMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, InventoryMovement $movement): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
