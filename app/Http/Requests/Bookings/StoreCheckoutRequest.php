<?php

namespace App\Http\Requests\Bookings;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*' => ['required', 'string'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*.id' => ['required', 'integer', 'exists:space_add_ons,id'],
            'add_ons.*.quantity' => ['required', 'integer', 'min:0'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'space_id.required' => 'Lapangan atau ruang harus dipilih.',
            'space_id.exists' => 'Lapangan atau ruang tidak valid.',
            'date.required' => 'Tanggal pemesanan wajib diisi.',
            'date.date_format' => 'Format tanggal harus Y-m-d.',
            'slots.required' => 'Pilih minimal satu slot waktu.',
            'slots.min' => 'Pilih minimal satu slot waktu.',
            'customer_name.required' => 'Nama lengkap pemesan wajib diisi.',
            'customer_phone.required' => 'Nomor WhatsApp atau telepon aktif wajib diisi.',
            'customer_phone.regex' => 'Format nomor telepon tidak valid. Gunakan nomor Indonesia (contoh: 08123456789).',
            'customer_email.required' => 'Alamat email wajib diisi.',
            'customer_email.email' => 'Format alamat email tidak valid.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
