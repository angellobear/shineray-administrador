<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación CSV de órdenes (reemplaza `custom-order-export-strategy.ts`). */
class OrderExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:pending,paid,fulfilled,canceled,refunded'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'b2b' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $query = OrderController::filtered($request, $filters)->with(['shippingAddress', 'shipments'])->latest('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['order_number', 'created_at', 'status', 'email', 'customer', 'gateway', 'is_b2b', 'subtotal', 'discount', 'shipping', 'tax', 'total', 'city', 'province', 'guide_number', 'erp_invoice']);

            $query->chunkById(500, function ($orders) use ($out): void {
                foreach ($orders as $order) {
                    /** @var Order $order */
                    fputcsv($out, [
                        $order->order_number,
                        $order->created_at?->toDateTimeString(),
                        $order->status->value,
                        $order->email,
                        $order->shippingAddress?->fullName(),
                        $order->gateway()?->value,
                        $order->isB2b() ? 'si' : 'no',
                        number_format($order->subtotal / 100, 2, '.', ''),
                        number_format($order->discount_total / 100, 2, '.', ''),
                        number_format($order->shipping_total / 100, 2, '.', ''),
                        number_format($order->tax_total / 100, 2, '.', ''),
                        number_format($order->total / 100, 2, '.', ''),
                        $order->shippingAddress?->city,
                        $order->shippingAddress?->province,
                        $order->shipments->whereNotNull('guide_number')->first()?->guide_number,
                        $order->metadata['erp_invoice_number'] ?? null,
                    ]);
                }
            });

            fclose($out);
        }, 'ordenes-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
