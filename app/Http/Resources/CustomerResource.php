<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'dni' => $this->dni,
            'is_b2b' => $this->is_b2b,
            'has_account' => $this->hasAccount(),
            'b2b' => $this->whenLoaded('b2bClient', fn () => $this->b2bClient === null ? null : [
                'id_client' => $this->b2bClient->id_client,
                'type_client' => $this->b2bClient->type_client,
                'policy_type' => $this->b2bClient->policyType(),
                'active' => $this->b2bClient->active,
                'address' => $this->b2bClient->address,
            ]),
        ];
    }
}
