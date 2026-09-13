<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'b2b' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $customers = Customer::query()
            ->withCount('orders')
            ->with('b2bClient')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($sub) => $sub
                ->where('email', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('dni', 'like', "%{$q}%")))
            ->when(isset($filters['b2b']), fn ($query) => $query->where('is_b2b', $request->boolean('b2b')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Customer $customer): array => [
                'id' => $customer->id,
                'email' => $customer->email,
                'name' => $customer->fullName(),
                'phone' => $customer->phone,
                'dni' => $customer->dni,
                'is_b2b' => $customer->is_b2b,
                'has_account' => $customer->hasAccount(),
                'orders_count' => $customer->orders_count,
                'ruc' => $customer->b2bClient?->id_client,
                'created_at' => $customer->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/customers/index', ['customers' => $customers, 'filters' => $filters]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load(['b2bClient', 'orders' => fn ($q) => $q->latest('id')->limit(20), 'addresses']);

        return Inertia::render('admin/customers/show', [
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->email,
                'name' => $customer->fullName(),
                'phone' => $customer->phone,
                'dni' => $customer->dni,
                'is_b2b' => $customer->is_b2b,
                'has_account' => $customer->hasAccount(),
                'metadata' => $customer->metadata ?? [],
                'created_at' => $customer->created_at?->toIso8601String(),
                'b2b' => $customer->b2bClient === null ? null : [
                    'id_client' => $customer->b2bClient->id_client,
                    'type_client' => $customer->b2bClient->type_client,
                    'policy_type' => $customer->b2bClient->policyType(),
                    'active' => $customer->b2bClient->active,
                    'address' => $customer->b2bClient->address,
                ],
                'orders' => $customer->orders->map(fn ($order): array => ['order_number' => $order->order_number, 'status' => $order->status->value, 'total' => $order->total, 'created_at' => $order->created_at?->toIso8601String()]),
                'addresses' => $customer->addresses->map(fn ($address): array => $address->only(['first_name', 'last_name', 'phone', 'address_1', 'city', 'province'])),
            ],
        ]);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->tokens()->delete();
        $customer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente eliminado.']);

        return to_route('admin.customers.index');
    }
}
