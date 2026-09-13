<?php

namespace App\Http\Controllers\Store\B2b;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Enums\Integration;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\B2bTransportista;
use App\Models\Customer;
use App\Models\IntegrationLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

/**
 * Consultas B2B del cliente autenticado (reemplazan `get-address`,
 * `get-cuota-b2b`, `get-transportistas-b2b`, `valor-deuda-credito` y
 * `recommended-products-b2b`, que hoy son públicas con CORS abierto).
 */
class B2bAccountController extends Controller
{
    public function address(Request $request): JsonResponse
    {
        return response()->json(['address' => $this->client($request)->b2bClient?->address]);
    }

    public function installments(Request $request): JsonResponse
    {
        $client = $this->client($request)->b2bClient;

        return response()->json([
            'policy_type' => $client?->policyType(),
            'policies' => $client === null ? [] : $client->activePolicies()->map(fn ($policy): array => [
                'installments' => $policy->installments,
                'credit_factor' => $policy->credit_factor,
            ])->values(),
        ]);
    }

    public function transportistas(): JsonResponse
    {
        return response()->json([
            'transportistas' => B2bTransportista::query()->orderBy('business_name')->get(['ruc', 'business_name']),
        ]);
    }

    public function debt(Request $request, ErpClientContract $erp): JsonResponse
    {
        $ruc = $this->client($request)->b2bClient?->id_client;

        if ($ruc === null) {
            return response()->json(['debt' => null]);
        }

        try {
            return response()->json(['debt' => $erp->fetchCreditDebt($ruc)]);
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::ShinerayErp, 'credit_debt_fail', ['ruc' => $ruc, 'message' => $exception->getMessage()]);

            return response()->json(['message' => 'No se pudo consultar la deuda en este momento.'], 503);
        }
    }

    public function recommendedProducts(Request $request, ErpClientContract $erp): AnonymousResourceCollection
    {
        $ruc = $this->client($request)->b2bClient?->id_client;
        $skus = [];

        if ($ruc !== null) {
            try {
                $skus = $erp->fetchRecommendedSkusB2b($ruc);
            } catch (Throwable $exception) {
                IntegrationLog::record(Integration::ShinerayErp, 'recommended_b2b_fail', ['ruc' => $ruc, 'message' => $exception->getMessage()]);
            }
        }

        $products = $skus === []
            ? Product::query()->whereRaw('1 = 0')->get()
            : Product::query()->published()->with('variant')->whereHas('variants', fn ($q) => $q->whereIn('sku', $skus))->orderBy('title')->get();

        return ProductResource::collection($products);
    }

    private function client(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer->loadMissing('b2bClient');
    }
}
