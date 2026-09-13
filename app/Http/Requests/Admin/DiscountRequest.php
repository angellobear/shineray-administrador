<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscountRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('discounts', 'code')->ignore($this->route('discount'))],
            'type' => ['required', 'in:percentage,fixed,free_shipping'],
            'value' => ['required_unless:type,free_shipping', 'nullable', 'integer', 'min:0', Rule::when($this->input('type') === 'percentage', ['max:100'])],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
