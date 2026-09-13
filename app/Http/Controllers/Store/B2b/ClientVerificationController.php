<?php

namespace App\Http\Controllers\Store\B2b;

use App\Domain\B2b\Services\ClientB2bVerificationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientVerificationController extends Controller
{
    public function __invoke(Request $request, ClientB2bVerificationService $service): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'identification' => ['required', 'string', 'max:30'],
            'dni' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $customer = $service->verify($validated);

        return response()->json([
            'message' => 'Cliente verificado. Enviamos un correo para establecer la contraseña.',
            'customer' => new CustomerResource($customer->load('b2bClient')),
        ]);
    }
}
