<?php

namespace App\Http\Requests\Superadmin;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

class UnpublishVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && (bool) $this->user()?->can('unpublish', $venue);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pencabutan publikasi wajib diisi.',
            'reason.min' => 'Alasan pencabutan publikasi wajib minimal 5 karakter.',
            'reason.max' => 'Alasan pencabutan publikasi maksimal 1000 karakter.',
        ];
    }
}
