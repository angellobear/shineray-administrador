<?php

namespace App\Domain\B2b\Services;

use App\Domain\B2b\Exceptions\B2bException;
use App\Models\B2bClient;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Alta de un cliente B2B en el storefront (reemplaza `client-b2b-verify`).
 * Ya no hay password compartido: la cuenta se crea sin contraseña y el
 * cliente la fija con el enlace de invitación (broker `customers`).
 */
final class ClientB2bVerificationService
{
    /**
     * @param  array{email: string, name: string, last_name: string, phone?: string|null, identification: string, dni?: string|null}  $data
     */
    public function verify(array $data): Customer
    {
        $client = B2bClient::query()->where('id_client', $data['identification'])->first();

        if (! $client instanceof B2bClient) {
            throw B2bException::clientNotFound();
        }

        $email = mb_strtolower(trim($data['email']));

        $customer = DB::transaction(function () use ($client, $data, $email): Customer {
            $customer = Customer::query()->where('email', $email)->first();

            if ($customer instanceof Customer && $customer->password !== null) {
                throw B2bException::accountAlreadyExists();
            }

            $customer ??= new Customer(['email' => $email]);
            $customer->fill([
                'first_name' => $data['name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'dni' => $data['dni'] ?? null,
                'is_b2b' => true,
                'metadata' => array_merge($customer->metadata ?? [], [
                    'ruc' => $client->id_client,
                    'dni' => $data['dni'] ?? null,
                    'b2b' => true,
                    'cod_client' => $client->type_client,
                    'address' => $client->address,
                ]),
            ]);
            $customer->password = null;
            $customer->save();

            if ($client->customer_id !== $customer->id) {
                $client->customer()->associate($customer)->save();
            }

            return $customer;
        });

        Password::broker('customers')->sendResetLink(['email' => $customer->email]);

        return $customer;
    }
}
