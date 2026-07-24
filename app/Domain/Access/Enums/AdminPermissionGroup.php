<?php

namespace App\Domain\Access\Enums;

/**
 * Agrupacion de permisos para presentarlos ordenados en el panel.
 *
 * No interviene en la autorizacion: solo da estructura a la pantalla de
 * administradores para que asignar permisos no sea una lista plana.
 */
enum AdminPermissionGroup: string
{
    case Catalog = 'catalog';
    case Inventory = 'inventory';
    case Orders = 'orders';
    case Customers = 'customers';
    case Marketing = 'marketing';
    case Content = 'content';
    case Analytics = 'analytics';
    case Integrations = 'integrations';
    case Administration = 'administration';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => 'Catalogo',
            self::Inventory => 'Inventario',
            self::Orders => 'Pedidos',
            self::Customers => 'Clientes',
            self::Marketing => 'Promociones y marketing',
            self::Content => 'Contenido y temas',
            self::Analytics => 'Reportes',
            self::Integrations => 'Integraciones',
            self::Administration => 'Administracion',
        };
    }

    /**
     * @return array<int, AdminPermission>
     */
    public function permissions(): array
    {
        return array_values(array_filter(
            AdminPermission::cases(),
            fn (AdminPermission $permission): bool => $permission->group() === $this,
        ));
    }
}
