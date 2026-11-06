<?php

namespace App\Http\Requests\Owner;

use App\Enums\AddOnPricingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'pricing_type' => ['required', new Enum(AddOnPricingType::class)],
            'stock_quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama add-on wajib diisi.',
            'price.required' => 'Harga add-on wajib diisi.',
            'price.min' => 'Harga add-on tidak boleh negatif.',
            'pricing_type.required' => 'Tipe perhitungan harga wajib dipilih.',
            'stock_quantity.required' => 'Jumlah stok wajib diisi.',
            'stock_quantity.min' => 'Jumlah stok minimal 1.',
        ];
    }
}
