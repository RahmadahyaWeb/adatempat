<?php

namespace App\Http\Requests\Owner;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueRequest extends FormRequest
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
            'business_id' => [
                'required',
                'integer',
                Rule::exists('businesses', 'id')->where(function ($query) {
                    if ($this->user()?->role !== UserRole::Superadmin) {
                        $query->where('user_id', $this->user()?->id);
                    }
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'timezone' => ['required', 'string', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
            'contact_phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpeg,png,webp,jpg', 'max:3072'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_id.required' => 'Bisnis wajib dipilih.',
            'business_id.exists' => 'Bisnis yang dipilih tidak valid atau bukan milik kamu.',
            'name.required' => 'Nama venue wajib diisi.',
            'city.required' => 'Kota wajib diisi.',
            'address.required' => 'Alamat lengkap wajib diisi.',
            'timezone.required' => 'Zona waktu wajib dipilih.',
            'timezone.in' => 'Zona waktu harus WIB (Asia/Jakarta), WITA (Asia/Makassar), atau WIT (Asia/Jayapura).',
            'contact_phone.regex' => 'Format nomor telepon tidak valid. Gunakan format nomor Indonesia (contoh: 08123456789 atau +628123456789).',
            'cover_photo.image' => 'File cover harus berupa gambar.',
            'cover_photo.mimes' => 'Format gambar harus JPEG, PNG, atau WEBP.',
            'cover_photo.max' => 'Ukuran gambar maksimal 3 MB.',
        ];
    }
}
