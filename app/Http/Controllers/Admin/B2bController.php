<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\B2bClient;
use App\Models\B2bPolicy;
use App\Models\B2bTransportista;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class B2bController extends Controller
{
    public function clients(Request $request): Response
    {
        $filters = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:100']]);

        $clients = B2bClient::query()
            ->with('customer')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($sub) => $sub
                ->where('id_client', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")))
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (B2bClient $client): array => [
                'id' => $client->id,
                'id_client' => $client->id_client,
                'type_client' => $client->type_client,
                'policy_type' => $client->policyType(),
                'name' => trim($client->first_name.' '.$client->last_name),
                'email' => $client->email,
                'phone_number' => $client->phone_number,
                'active' => $client->active,
                'has_customer' => $client->customer_id !== null,
                'customer_has_account' => $client->customer?->hasAccount() ?? false,
                'erp_synced_at' => $client->erp_synced_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/b2b/clients', ['clients' => $clients, 'filters' => $filters]);
    }

    public function policies(): Response
    {
        return Inertia::render('admin/b2b/policies', [
            'policies' => B2bPolicy::query()->orderBy('client_type')->orderBy('installments')->get()->map(fn (B2bPolicy $policy): array => [
                'id' => $policy->id,
                'client_type' => $policy->client_type,
                'installments' => $policy->installments,
                'credit_factor' => $policy->credit_factor,
                'is_active' => $policy->is_active,
                'erp_synced_at' => $policy->erp_synced_at?->toIso8601String(),
            ]),
        ]);
    }

    public function transportistas(): Response
    {
        return Inertia::render('admin/b2b/transportistas', [
            'transportistas' => B2bTransportista::query()->orderBy('business_name')->get()->map(fn (B2bTransportista $t): array => [
                'id' => $t->id,
                'ruc' => $t->ruc,
                'business_name' => $t->business_name,
                'erp_synced_at' => $t->erp_synced_at?->toIso8601String(),
            ]),
        ]);
    }
}
