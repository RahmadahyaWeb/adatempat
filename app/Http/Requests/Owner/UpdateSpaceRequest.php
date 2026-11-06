<?php

namespace App\Http\Requests\Owner;

use App\Enums\CancellationPolicy;
use App\Enums\SpaceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateSpaceRequest extends FormRequest
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
            'category' => ['required', new Enum(SpaceCategory::class)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'instant_book' => ['required', 'boolean'],
            'cancellation_policy' => ['required', new Enum(CancellationPolicy::class)],
            'cancellation_cutoff_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'refund_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama space wajib diisi.',
            'category.required' => 'Kategori space wajib dipilih.',
            'base_price.required' => 'Harga dasar wajib diisi.',
            'base_price.min' => 'Harga dasar tidak boleh bernilai negatif.',
            'instant_book.required' => 'Opsi instant booking wajib ditentukan.',
            'cancellation_policy.required' => 'Kebijakan pembatalan wajib dipilih.',
            'cancellation_cutoff_hours.required' => 'Batas jam pembatalan wajib diisi.',
            'refund_percentage.required' => 'Persentase pengembalian wajib diisi.',
            'refund_percentage.min' => 'Persentase pengembalian minimal 0%.',
            'refund_percentage.max' => 'Persentase pengembalian maksimal 100%.',
            'is_active.required' => 'Status aktif space wajib ditentukan.',
        ];
    }
}
