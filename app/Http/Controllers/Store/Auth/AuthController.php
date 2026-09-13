<?php

namespace App\Http\Controllers\Store\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Auth\RegisterCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Autenticación de clientes del storefront con tokens de Sanctum
 * (reemplaza el JWT/cookie de Medusa). El panel de administración usa
 * Fortify con el guard `web`; esto es solo para `customers`.
 */
class AuthController extends Controller
{
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->safe()->except(['password_confirmation']) + ['is_b2b' => false]);

        return response()->json([
            'customer' => new CustomerResource($customer->refresh()),
            'token' => $customer->createToken((string) $request->input('device_name', 'storefront'))->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        $customer = Customer::query()->where('email', mb_strtolower($validated['email']))->first();

        if (! $customer instanceof Customer || ! $customer->hasAccount() || ! Hash::check($validated['password'], (string) $customer->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return response()->json([
            'customer' => new CustomerResource($customer->load('b2bClient')),
            'token' => $customer->createToken($validated['device_name'] ?? 'storefront')->plainTextToken,
        ]);
    }

    public function me(Request $request): CustomerResource
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return new CustomerResource($customer->load('b2bClient'));
    }

    public function logout(Request $request): JsonResponse
    {
        // Solo revoca el token bearer usado en esta petición; una sesión SPA no tiene fila que borrar.
        $bearer = $request->bearerToken();

        if ($bearer !== null) {
            PersonalAccessToken::findToken($bearer)?->delete();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
