<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\Jobs\SendOrderConfirmationJob;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:pending,paid,fulfilled,canceled,refunded'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'b2b' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $orders = $this->filtered($request, $filters)
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $order): array => $this->summary($order));

        return Inertia::render('admin/orders/index', ['orders' => $orders, 'filters' => $filters]);
    }

    public function show(Order $order): Response
    {
        $order->load(['items', 'shippingAddress', 'payments', 'shipments', 'discounts', 'customer', 'integrationLogs' => fn ($q) => $q->latest('id')->limit(20)]);

        return Inertia::render('admin/orders/show', [
            'order' => $this->summary($order) + [
                'subtotal' => $order->subtotal,
                'discount_total' => $order->discount_total,
                'shipping_total' => $order->shipping_total,
                'tax_total' => $order->tax_total,
                'metadata' => $order->metadata ?? [],
                'paid_at' => $order->paid_at?->toIso8601String(),
                'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                'canceled_at' => $order->canceled_at?->toIso8601String(),
                'allowed_transitions' => array_map(fn (OrderStatus $s): string => $s->value, $order->status->allowedTransitions()),
                'items' => $order->items->map(fn ($item): array => ['id' => $item->id, 'sku' => $item->sku, 'title' => $item->title, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'line_total' => $item->lineTotal()]),
                'shipping_address' => $order->shippingAddress === null ? null : $order->shippingAddress->only(['first_name', 'last_name', 'phone', 'address_1', 'city', 'province', 'metadata']),
                'payments' => $order->payments->map(fn ($payment): array => ['id' => $payment->id, 'gateway' => $payment->gateway->value, 'status' => $payment->status->value, 'amount' => $payment->amount, 'gateway_reference' => $payment->gateway_reference, 'response_code' => $payment->response_code, 'authorized_at' => $payment->authorized_at?->toIso8601String()]),
                'shipments' => $order->shipments->map(fn ($shipment): array => ['id' => $shipment->id, 'provider' => $shipment->provider->value, 'status' => $shipment->status->value, 'guide_number' => $shipment->guide_number, 'weight_kg' => $shipment->weight_kg]),
                'discounts' => $order->discounts->map(fn ($discount): array => ['code' => $discount->code, 'type' => $discount->type->value, 'value' => $discount->value]),
                'logs' => $order->integrationLogs->map(fn ($log): array => ['id' => $log->id, 'integration' => $log->integration->value, 'event' => $log->event, 'level' => $log->level, 'payload' => $log->payload, 'created_at' => $log->created_at?->toIso8601String()]),
            ],
        ]);
    }

    public function transition(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:fulfilled,canceled,refunded']]);

        try {
            $order->transitionTo(OrderStatus::from($validated['status']))->save();
        } catch (LogicException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estado de la orden actualizado.']);

        return to_route('admin.orders.show', $order);
    }

    public function resendConfirmation(Order $order): RedirectResponse
    {
        $metadata = $order->metadata ?? [];
        unset($metadata['confirmation_sent_at']);
        $order->update(['metadata' => $metadata]);

        SendOrderConfirmationJob::dispatch($order);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Confirmación reenviada.']);

        return to_route('admin.orders.show', $order);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    public static function filtered(Request $request, array $filters): Builder
    {
        return Order::query()
            ->with('customer')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($sub) => $sub->where('order_number', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when(isset($filters['b2b']), fn ($query) => $query->where('metadata->is_b2b', $request->boolean('b2b')));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'email' => $order->email,
            'customer_name' => $order->customer?->fullName(),
            'status' => $order->status->value,
            'gateway' => $order->gateway()?->value,
            'is_b2b' => $order->isB2b(),
            'total' => $order->total,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
