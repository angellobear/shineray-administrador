<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DeUna manda la clave compartida en `Authorization` o `x-api-key`
 * (hoy `MEDUSA_ACCESS_API_KEY`). Comparación en tiempo constante.
 */
class VerifyDeunaWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.deuna.webhook_secret');
        $provided = (string) ($request->header('x-api-key') ?? $request->header('Authorization') ?? '');
        $provided = preg_replace('/^Bearer\s+/i', '', $provided) ?? '';

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
