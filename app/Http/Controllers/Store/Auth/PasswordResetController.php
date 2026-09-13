<?php

namespace App\Http\Controllers\Store\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/** Olvido/restablecimiento de contraseña de clientes con el broker `customers`. */
class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        // Misma respuesta exista o no el email: no revela cuentas.
        Password::broker('customers')->sendResetLink(['email' => mb_strtolower($validated['email'])]);

        return response()->json(['message' => 'Si el correo existe, enviamos un enlace para restablecer la contraseña.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('customers')->reset(
            ['email' => mb_strtolower($validated['email']), 'password' => $validated['password'], 'token' => $validated['token']],
            function (Customer $customer, string $password): void {
                $customer->forceFill(['password' => $password])->save();
                $customer->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Contraseña actualizada.']);
    }
}
