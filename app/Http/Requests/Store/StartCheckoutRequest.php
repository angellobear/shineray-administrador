<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', Rule::in((array) config('shineray.checkout.storefront_gateways'))],
            'context' => ['sometimes', 'array'],
            'context.transportista_id' => ['sometimes', 'nullable', 'string', 'max:30'],
            'context.transportista_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'context.installments' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'context.cod_client' => ['sometimes', 'nullable', 'string', 'max:30'],
            'context.ruc' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
