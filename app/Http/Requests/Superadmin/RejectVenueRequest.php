<?php

namespace App\Http\Requests\Superadmin;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

class RejectVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $venue instanceof Venue && (bool) $this->user()?->can('reject', $venue);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Penjelasan alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Penjelasan alasan penolakan wajib minimal 5 karakter.',
            'rejection_reason.max' => 'Penjelasan alasan penolakan maksimal 1000 karakter.',
        ];
    }
}
