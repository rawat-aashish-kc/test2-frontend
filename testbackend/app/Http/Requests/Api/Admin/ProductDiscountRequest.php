<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'min_quantity' => [$required, 'integer', 'min:1'],
            'discount_percent' => [$required, 'numeric', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
