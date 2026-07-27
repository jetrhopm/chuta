<?php

namespace App\Policies;

use App\Domain\Access\Enums\AdminPermission;
use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::ManagePromotions->value);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can(AdminPermission::ManagePromotions->value);
    }

    public function create(User $user): bool
    {
        return $user->can(AdminPermission::ManagePromotions->value);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can(AdminPermission::ManagePromotions->value);
    }

    /**
     * Una promocion ya consumida no se borra.
     *
     * Los pedidos guardan su propia fotografia del descuento, asi que borrarla no
     * los alteraria, pero si eliminaria el registro de usos que sostiene los
     * limites. Desactivarla es lo correcto: deja de aplicar y conserva el rastro.
     */
    public function delete(User $user, Promotion $promotion): bool
    {
        if ($promotion->usages()->exists()) {
            return false;
        }

        return $user->can(AdminPermission::ManagePromotions->value);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
