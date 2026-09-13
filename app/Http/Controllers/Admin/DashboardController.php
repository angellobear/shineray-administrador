<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $paid = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled]);

        return Inertia::render('dashboard', [
            'stats' => [
                'orders_today' => (clone $paid)->whereDate('paid_at', today())->count(),
                'revenue_today' => (int) (clone $paid)->whereDate('paid_at', today())->sum('total'),
                'orders_month' => (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->count(),
                'revenue_month' => (int) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('total'),
                'pending_orders' => Order::query()->where('status', OrderStatus::Pending)->count(),
                'published_products' => Product::query()->published()->count(),
                'active_carts' => Cart::query()->active()->whereHas('items')->count(),
                'integration_errors_24h' => IntegrationLog::query()->where('level', 'error')->where('created_at', '>=', now()->subDay())->count(),
            ],
            'recentOrders' => Order::query()->with('customer')->latest('id')->limit(8)->get()->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'email' => $order->email,
                'status' => $order->status->value,
                'total' => $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ]),
            'lastSyncRuns' => ErpSyncRun::query()->latest('started_at')->limit(5)->get()->map(fn (ErpSyncRun $run): array => [
                'id' => $run->id,
                'type' => $run->type->value,
                'status' => $run->status->value,
                'started_at' => $run->started_at->toIso8601String(),
                'fetched_count' => $run->fetched_count,
            ]),
        ]);
    }
}
