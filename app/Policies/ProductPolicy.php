<?php

namespace App\Policies;

use App\Domain\Access\Enums\AdminPermission;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::ViewProducts->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(AdminPermission::ViewProducts->value);
    }

    public function create(User $user): bool
    {
        return $user->can(AdminPermission::CreateProducts->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(AdminPermission::UpdateProducts->value);
    }

    /**
     * Archivar es la via normal para retirar un producto del catalogo.
     */
    public function archive(User $user, Product $product): bool
    {
        return $user->can(AdminPermission::ArchiveProducts->value);
    }

    /**
     * Borrar es distinto de archivar y va aparte: un producto con historial de
     * inventario o con pedidos no deberia desaparecer.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->can(AdminPermission::DeleteProducts->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(AdminPermission::DeleteProducts->value);
    }

    public function managePricing(User $user, Product $product): bool
    {
        return $user->can(AdminPermission::ManageProductPricing->value);
    }

    public function adjustInventory(User $user, Product $product): bool
    {
        return $user->can(AdminPermission::AdjustInventory->value);
    }
}
