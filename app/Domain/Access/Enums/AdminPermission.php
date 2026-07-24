<?php

namespace App\Domain\Access\Enums;

/**
 * Catalogo de permisos administrativos.
 *
 * Es la unica fuente de verdad: el seeder sincroniza la tabla de permisos a
 * partir de estos casos, y las Policies preguntan por ellos. Agregar un permiso
 * aqui y volver a sembrar basta para que quede disponible en el panel.
 *
 * La autorizacion siempre se resuelve en el servidor. Ocultar un boton en la
 * interfaz no sustituye la comprobacion real.
 */
enum AdminPermission: string
{
    // Catalogo
    case ViewProducts = 'products.view';
    case CreateProducts = 'products.create';
    case UpdateProducts = 'products.update';
    case ArchiveProducts = 'products.archive';
    case DeleteProducts = 'products.delete';
    case ManageProductPricing = 'products.pricing.manage';
    case ManageBrandsAndCategories = 'catalog.taxonomy.manage';

    // Inventario
    case ViewInventoryMovements = 'inventory.movements.view';
    case AdjustInventory = 'inventory.adjust';

    // Pedidos
    case ViewOrders = 'orders.view';
    case ManageOrders = 'orders.manage';
    case ManageFulfillment = 'orders.fulfillment.manage';
    case ProcessRefunds = 'orders.refunds.process';

    // Clientes
    case ViewCustomers = 'customers.view';

    // Comercial
    case ManagePromotions = 'promotions.manage';

    // Contenido
    case ManageContent = 'content.manage';
    case ManageThemes = 'themes.manage';

    // Analitica
    case ViewReports = 'reports.view';
    case ExportData = 'data.export';

    // Integraciones
    case ManageMailSettings = 'integrations.mail.manage';
    case ManagePaymentProviders = 'integrations.payments.manage';
    case ManageMetaAds = 'integrations.meta.manage';

    // Borrar credenciales guardadas es mas delicado que configurarlas, asi que
    // vive en un permiso aparte.
    case DeleteIntegrationCredentials = 'integrations.credentials.delete';

    // Administracion
    case ManageAdministrators = 'admins.manage';
    case ViewAuditLog = 'audit.view';

    public function group(): AdminPermissionGroup
    {
        return match ($this) {
            self::ViewProducts,
            self::CreateProducts,
            self::UpdateProducts,
            self::ArchiveProducts,
            self::DeleteProducts,
            self::ManageProductPricing,
            self::ManageBrandsAndCategories => AdminPermissionGroup::Catalog,

            self::ViewInventoryMovements,
            self::AdjustInventory => AdminPermissionGroup::Inventory,

            self::ViewOrders,
            self::ManageOrders,
            self::ManageFulfillment,
            self::ProcessRefunds => AdminPermissionGroup::Orders,

            self::ViewCustomers => AdminPermissionGroup::Customers,

            self::ManagePromotions => AdminPermissionGroup::Marketing,

            self::ManageContent,
            self::ManageThemes => AdminPermissionGroup::Content,

            self::ViewReports,
            self::ExportData => AdminPermissionGroup::Analytics,

            self::ManageMailSettings,
            self::ManagePaymentProviders,
            self::ManageMetaAds,
            self::DeleteIntegrationCredentials => AdminPermissionGroup::Integrations,

            self::ManageAdministrators,
            self::ViewAuditLog => AdminPermissionGroup::Administration,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewProducts => 'Ver productos',
            self::CreateProducts => 'Crear productos',
            self::UpdateProducts => 'Editar productos',
            self::ArchiveProducts => 'Archivar productos',
            self::DeleteProducts => 'Eliminar productos',
            self::ManageProductPricing => 'Modificar costos y precios',
            self::ManageBrandsAndCategories => 'Gestionar marcas y categorias',
            self::ViewInventoryMovements => 'Ver movimientos de inventario',
            self::AdjustInventory => 'Ajustar inventario',
            self::ViewOrders => 'Ver pedidos',
            self::ManageOrders => 'Administrar pedidos',
            self::ManageFulfillment => 'Cambiar estados de preparacion y envio',
            self::ProcessRefunds => 'Procesar cancelaciones y reembolsos',
            self::ViewCustomers => 'Ver clientes',
            self::ManagePromotions => 'Gestionar promociones y cupones',
            self::ManageContent => 'Gestionar carruseles, blog y paginas',
            self::ManageThemes => 'Gestionar temas',
            self::ViewReports => 'Consultar reportes',
            self::ExportData => 'Exportar informacion',
            self::ManageMailSettings => 'Gestionar SMTP',
            self::ManagePaymentProviders => 'Gestionar proveedores de pago',
            self::ManageMetaAds => 'Gestionar Meta Ads',
            self::DeleteIntegrationCredentials => 'Borrar credenciales de integraciones',
            self::ManageAdministrators => 'Gestionar administradores y permisos',
            self::ViewAuditLog => 'Ver auditoria y registros',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
