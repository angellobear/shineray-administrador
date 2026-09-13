<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'shipping_address' => ['sometimes', 'required', 'array'],
            'shipping_address.first_name' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.last_name' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.phone' => ['required_with:shipping_address', 'string', 'max:30'],
            'shipping_address.address_1' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.city' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.province' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.metadata' => ['sometimes', 'array'],
            'shipping_address.metadata.dni' => ['required_with:shipping_address', 'string', 'max:20'],
            'shipping_address.metadata.dni_type' => ['sometimes', 'integer'],
            'shipping_address.metadata.city_id' => ['sometimes', 'nullable'],
            'shipping_address.metadata.city_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
