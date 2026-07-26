<?php

namespace App\Policies;

use App\Domain\Access\Enums\AdminPermission;
use App\Models\PaymentReceipt;
use App\Models\User;

/**
 * Revision de comprobantes de transferencia.
 *
 * Se apoya en el permiso de administrar pedidos y no en el de reembolsos: revisar
 * un deposito es parte de la operacion diaria de la tienda, no una excepcion
 * comercial. Aun asi, aceptar un comprobante aprueba un pago, asi que la accion
 * queda registrada con quien la hizo.
 */
class PaymentReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::ViewOrders->value);
    }

    public function view(User $user, PaymentReceipt $receipt): bool
    {
        return $user->can(AdminPermission::ViewOrders->value);
    }

    /**
     * Los comprobantes los sube el cliente, no el panel.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function review(User $user, PaymentReceipt $receipt): bool
    {
        // Solo los que siguen pendientes: volver a revisar uno ya resuelto
        // aprobaria dos veces el mismo pago.
        return $receipt->isPending() && $user->can(AdminPermission::ManageOrders->value);
    }

    public function update(User $user, PaymentReceipt $receipt): bool
    {
        return $this->review($user, $receipt);
    }

    /**
     * Un comprobante es la evidencia de un pago: no se borra.
     */
    public function delete(User $user, PaymentReceipt $receipt): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
