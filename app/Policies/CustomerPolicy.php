<?php

namespace App\Policies;

use App\Domain\Access\Enums\AdminPermission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AdminPermission::ViewCustomers->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(AdminPermission::ViewCustomers->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Customer $customer): bool
    {
        return false;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}
