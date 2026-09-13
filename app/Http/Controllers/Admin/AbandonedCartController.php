<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cart\Services\AbandonedCartService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Reemplaza `admin/abandoned-cart` (listado + reenvío manual). */
class AbandonedCartController extends Controller
{
    public function index(AbandonedCartService $service): Response
    {
        $carts = $service->candidatesQuery(fromAdmin: true)
            ->paginate(25)
            ->through(fn (Cart $cart): array => [
                'id' => $cart->id,
                'public_id' => $cart->public_id,
                'email' => $cart->email,
                'name' => $cart->shippingAddress?->fullName(),
                'phone' => $cart->shippingAddress?->phone,
                'item_count' => $cart->itemCount(),
                'items' => $cart->items->map(fn (CartItem $item): array => ['title' => $item->variant->product->title ?? $item->variant->sku, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price])->values()->all(),
                'subtotal' => $cart->subtotal,
                'abandoned_count' => $cart->abandoned_count,
                'abandoned_lastdate' => $cart->abandoned_lastdate?->toIso8601String(),
                'abandoned_completed_at' => $cart->abandoned_completed_at?->toIso8601String(),
                'last_activity_at' => $cart->last_activity_at?->toIso8601String(),
                'created_at' => $cart->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/abandoned-carts/index', [
            'carts' => $carts,
            'intervals' => $service->intervals(),
            'enabled' => (bool) config('shineray.abandoned_cart.enabled'),
        ]);
    }

    public function send(Cart $cart, AbandonedCartService $service): RedirectResponse
    {
        if (blank($cart->email)) {
            return back()->withErrors(['cart' => 'El carrito no tiene email.']);
        }

        $service->sendAbandonedCartEmail($cart);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email de recuperación enviado.']);

        return to_route('admin.abandoned-carts.index');
    }
}
