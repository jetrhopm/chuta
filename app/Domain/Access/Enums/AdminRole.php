<?php

namespace App\Domain\Access\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadministrador',
            self::Admin => 'Administrador',
        };
    }

    /**
     * Permisos con los que arranca el rol.
     *
     * Son un punto de partida, no una definicion rigida: el superadministrador
     * puede ajustarlos despues desde el panel y el seeder respeta esos cambios.
     *
     * El superadministrador no lleva permisos asignados porque no los necesita;
     * una comprobacion previa en la capa de autorizacion le concede todo.
     *
     * @return array<int, AdminPermission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [],

            // El administrador opera la tienda: catalogo, inventario, pedidos,
            // promociones y contenido. Quedan fuera las credenciales de las
            // integraciones y la gestion de otros administradores, que son las
            // acciones con las que se podria escalar privilegios o exponer
            // secretos.
            self::Admin => [
                AdminPermission::ViewProducts,
                AdminPermission::CreateProducts,
                AdminPermission::UpdateProducts,
                AdminPermission::ArchiveProducts,
                AdminPermission::ManageProductPricing,
                AdminPermission::ManageBrandsAndCategories,
                AdminPermission::ViewInventoryMovements,
                AdminPermission::AdjustInventory,
                AdminPermission::ViewOrders,
                AdminPermission::ManageOrders,
                AdminPermission::ManageFulfillment,
                AdminPermission::ViewCustomers,
                AdminPermission::ManagePromotions,
                AdminPermission::ManageContent,
                AdminPermission::ManageThemes,
                AdminPermission::ViewReports,
                AdminPermission::ExportData,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function defaultPermissionValues(): array
    {
        return array_map(
            static fn (AdminPermission $permission): string => $permission->value,
            $this->defaultPermissions(),
        );
    }
}
