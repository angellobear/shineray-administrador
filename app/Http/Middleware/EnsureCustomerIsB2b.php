<?php

namespace App\Http\Middleware;

use App\Domain\B2b\Exceptions\B2bException;
use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsB2b
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user();

        if (! $customer instanceof Customer || ! $customer->is_b2b) {
            throw B2bException::notB2b();
        }

        return $next($request);
    }
}
