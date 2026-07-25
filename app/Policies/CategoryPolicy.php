<?php

namespace App\Policies;

use App\Domain\Access\Enums\AdminPermission;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::ViewProducts->value);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can(AdminPermission::ViewProducts->value);
    }

    public function create(User $user): bool
    {
        return $user->can(AdminPermission::ManageBrandsAndCategories->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(AdminPermission::ManageBrandsAndCategories->value);
    }

    /**
     * Una categoria con productos no se borra: la clave foranea de productos usa
     * restrictOnDelete, asi que el intento fallaria a nivel de base de datos.
     * Mejor negarlo aqui con un criterio explicito.
     */
    public function delete(User $user, Category $category): bool
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return false;
        }

        return $user->can(AdminPermission::ManageBrandsAndCategories->value);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
