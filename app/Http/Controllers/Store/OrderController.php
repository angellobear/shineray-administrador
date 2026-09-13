<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Una orden se consulta con el email del comprador o como cliente
     * autenticado dueño de la orden; cualquier otro caso responde 404.
     */
    public function show(Request $request, Order $order): OrderResource
    {
        $customer = $request->user('customer');
        $ownsIt = $customer instanceof Customer
            ? $order->customer_id === $customer->id
            : mb_strtolower((string) $request->query('email')) === mb_strtolower($order->email);

        abort_unless($ownsIt, 404);

        return new OrderResource($order->load(['items', 'shippingAddress', 'shipments']));
    }
}
