<?php

namespace App\Policies;

use App\Domain\Access\Enums\AdminPermission;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::ViewOrders->value);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can(AdminPermission::ViewOrders->value);
    }

    /**
     * Los pedidos los crea la tienda, no el panel. Se deja cerrado para que no
     * aparezcan pedidos sin el recalculo de precios ni el descuento de
     * inventario que hace el checkout.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can(AdminPermission::ManageOrders->value);
    }

    public function updateFulfillment(User $user, Order $order): bool
    {
        return $user->can(AdminPermission::ManageFulfillment->value);
    }

    /**
     * Cancelar repone inventario, asi que exige el permiso de cancelaciones y no
     * el de edicion normal.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->can(AdminPermission::ProcessRefunds->value);
    }

    /**
     * Un pedido es un registro contable: no se borra.
     */
    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(AdminPermission::ExportData->value);
    }
}
