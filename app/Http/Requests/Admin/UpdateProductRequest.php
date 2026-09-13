<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,published'],
            'is_b2b' => ['sometimes', 'boolean'],
            'is_promo' => ['sometimes', 'boolean'],
            'old_price' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
