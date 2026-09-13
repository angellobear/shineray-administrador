<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Orders\Services\CheckoutService;
use App\Domain\Payments\Exceptions\PaymentFailedException;
use App\Enums\Integration;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Webhook de DeUna (reemplaza `store/deuna-webhook`). Ya no se loguea como
 * admin ni se llama a la propia API: se ubica el pago por `idTransaction` y
 * se confirma vía `CheckoutService`, que vuelve a verificar el estado en
 * `/payment/info` antes de aprobar. Responde 200 siempre que la firma sea
 * válida para que DeUna no reintente indefinidamente.
 */
class DeunaWebhookController extends Controller
{
    public function __invoke(Request $request, CheckoutService $checkout): JsonResponse
    {
        $status = strtoupper((string) $request->input('status', ''));
        $transactionId = (string) $request->input('idTransaction', '');
        $internalReference = (string) $request->input('internalTransactionReference', '');

        IntegrationLog::record(Integration::Deuna, 'webhook_received', [
            'status' => $status,
            'transaction_id' => $transactionId,
            'internal_reference' => $internalReference,
        ], level: 'info');

        if (! in_array($status, ['APPROVED', 'SUCCESS'], true)) {
            return response()->json(['message' => 'Ignored']);
        }

        $payment = Payment::query()
            ->whereIn('gateway', [PaymentGateway::Deuna->value, PaymentGateway::DeunaB2b->value])
            ->where('gateway_reference', $transactionId)
            ->latest('id')
            ->first();

        if ($payment === null) {
            IntegrationLog::record(Integration::Deuna, 'webhook_unmatched', ['transaction_id' => $transactionId, 'internal_reference' => $internalReference]);

            return response()->json(['message' => 'Unknown transaction']);
        }

        try {
            $checkout->complete($payment, ['transactionId' => $transactionId]);
        } catch (PaymentFailedException $exception) {
            return response()->json(['message' => 'Not approved', 'code' => $exception->result->responseCode]);
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::Deuna, 'webhook_error', ['message' => $exception->getMessage()], $payment);

            return response()->json(['message' => 'Cart Already Paid']);
        }

        return response()->json(['message' => 'Success']);
    }
}
