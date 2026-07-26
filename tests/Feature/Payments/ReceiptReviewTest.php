<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Filament\Resources\PaymentReceipts\Pages\ListPaymentReceipts;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Deja un pedido con transferencia y su comprobante ya subido.
 *
 * @return array{0: PaymentReceipt, 1: Order}
 */
function pedidoConComprobante(): array
{
    Storage::fake('local');

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();

    test()->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->image('deposito.jpg'),
    ])->assertRedirect();

    return [PaymentReceipt::firstOrFail(), $order];
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('aprueba el pago al aceptar el comprobante', function () {
    [$receipt, $order] = pedidoConComprobante();

    $admin = User::factory()->withRole(AdminRole::Admin)->create();
    $this->actingAs($admin);

    Livewire::test(ListPaymentReceipts::class)
        ->callAction(
            TestAction::make('aceptar')->table($receipt),
            data: ['comment' => 'Deposito confirmado'],
        )
        ->assertHasNoActionErrors();

    $receipt->refresh();
    $order->refresh();

    expect($receipt->status)->toBe(PaymentReceipt::STATUS_ACCEPTED)
        ->and($receipt->reviewed_by)->toBe($admin->getKey())
        ->and($receipt->reviewed_at)->not->toBeNull()
        // La aprobacion pasa por la misma accion que liquida cualquier pago, asi
        // que el pedido queda igual que si lo hubiera confirmado una pasarela.
        ->and($order->payment_status)->toBe(PaymentStatus::Approved)
        ->and($order->status)->toBe('confirmed')
        ->and($order->currentPaymentAttempt()->paid_at)->not->toBeNull();
});

it('deja el pedido pendiente al rechazar el comprobante', function () {
    [$receipt, $order] = pedidoConComprobante();

    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    Livewire::test(ListPaymentReceipts::class)
        ->callAction(
            TestAction::make('rechazar')->table($receipt),
            data: ['comment' => 'El comprobante no corresponde a este pedido'],
        )
        ->assertHasNoActionErrors();

    $receipt->refresh();

    expect($receipt->status)->toBe(PaymentReceipt::STATUS_REJECTED)
        ->and($receipt->review_comment)->toBe('El comprobante no corresponde a este pedido')
        // El pedido sigue pendiente para que el cliente pueda subir otro, y su
        // inventario no se libera.
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->status)->toBe('pending_confirmation');
});

it('exige un motivo para rechazar', function () {
    [$receipt] = pedidoConComprobante();

    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    Livewire::test(ListPaymentReceipts::class)
        ->callAction(TestAction::make('rechazar')->table($receipt), data: ['comment' => ''])
        ->assertHasActionErrors(['comment']);

    expect($receipt->fresh()->isPending())->toBeTrue();
});

it('no permite revisar dos veces el mismo comprobante', function () {
    [$receipt, $order] = pedidoConComprobante();

    $admin = User::factory()->withRole(AdminRole::Admin)->create();
    $this->actingAs($admin);

    Livewire::test(ListPaymentReceipts::class)
        ->callAction(TestAction::make('aceptar')->table($receipt), data: ['comment' => 'Ok']);

    $revisado = $receipt->fresh();

    // Volver a revisarlo aprobaria dos veces el mismo pago. El listado ademas ya
    // no lo muestra, porque su filtro por omision son los pendientes.
    expect($admin->can('review', $revisado))->toBeFalse()
        ->and($revisado->isPending())->toBeFalse();

    // Y la aprobacion no se duplico: sigue habiendo un unico intento pagado.
    expect($order->paymentAttempts()->where('status', 'approved')->count())->toBe(1);
});

it('esconde la revision a quien no puede administrar pedidos', function () {
    [$receipt] = pedidoConComprobante();

    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo(AdminPermission::ManageOrders->value);

    $this->actingAs($admin->fresh());

    Livewire::test(ListPaymentReceipts::class)
        ->assertActionHidden(TestAction::make('aceptar')->table($receipt));
});

it('niega borrar un comprobante a quien no es superadministrador', function () {
    [$receipt] = pedidoConComprobante();

    expect(User::factory()->withRole(AdminRole::Admin)->create()->can('delete', $receipt))->toBeFalse();
});

it('impide borrar un comprobante incluso saltandose la Policy', function () {
    [$receipt] = pedidoConComprobante();

    // El superadministrador se salta cualquier Policy por el Gate::before, asi que
    // la invariante tiene que vivir en el modelo para ser una garantia.
    expect(fn () => $receipt->delete())->toThrow(RuntimeException::class)
        ->and(PaymentReceipt::whereKey($receipt->getKey())->exists())->toBeTrue();
});

it('renderiza el listado de comprobantes con datos', function () {
    pedidoConComprobante();

    $this->actingAs(User::factory()->withRole(AdminRole::SuperAdmin)->create());

    $this->get('/admin/payment-receipts')->assertOk();
});

it('sirve el comprobante a quien tiene permiso', function () {
    [$receipt] = pedidoConComprobante();

    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    $this->get(route('receipts.show', ['receipt' => $receipt->getKey()]))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

it('niega el comprobante a una cuenta sin permiso de ver pedidos', function () {
    [$receipt] = pedidoConComprobante();

    // Una cuenta autenticada pero sin rol administrativo no puede verlo.
    $this->actingAs(User::factory()->create());

    $this->get(route('receipts.show', ['receipt' => $receipt->getKey()]))->assertForbidden();
});
