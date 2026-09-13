<?php

namespace App\Http\Controllers\Store;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Enums\Integration;
use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Consulta de stock en vivo contra el ERP (reemplaza `shineray/stock`).
 * Misma forma de respuesta que hoy: `{complete_purchase, stock}`; ante un
 * fallo del ERP responde `false/0` y deja registro.
 */
class StockController extends Controller
{
    public function __invoke(Request $request, ErpClientContract $erp): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50'],
        ]);

        try {
            $stock = $erp->checkStock($validated['sku']);
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::ShinerayErp, 'stock_check_fail', [
                'sku' => $validated['sku'],
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['complete_purchase' => false, 'stock' => 0]);
        }

        return response()->json([
            'complete_purchase' => $stock->completePurchase,
            'stock' => $stock->quantity,
        ]);
    }
}
