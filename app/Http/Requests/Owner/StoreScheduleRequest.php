<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScheduleRequest extends FormRequest
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
            'schedules' => ['required', 'array'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.open_time' => ['required', 'date_format:H:i'],
            'schedules.*.close_time' => ['required', 'date_format:H:i', 'after:schedules.*.open_time'],
            'schedules.*.slot_duration_minutes' => ['required', 'integer', Rule::in([30, 60, 90, 120])],
            'schedules.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedules.required' => 'Daftar jadwal wajib diisi.',
            'schedules.*.day_of_week.between' => 'Hari harus bernilai antara 0 (Minggu) sampai 6 (Sabtu).',
            'schedules.*.open_time.date_format' => 'Format jam buka harus JJ:MM (contoh: 08:00).',
            'schedules.*.close_time.date_format' => 'Format jam tutup harus JJ:MM (contoh: 22:00).',
            'schedules.*.close_time.after' => 'Jam tutup harus lebih malam daripada jam buka.',
            'schedules.*.slot_duration_minutes.in' => 'Durasi slot harus 30, 60, 90, atau 120 menit.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var mixed $rawSchedules */
            $rawSchedules = $this->input('schedules', []);
            if (! is_array($rawSchedules)) {
                return;
            }

            /** @var array<int, list<array{index: int, open: string, close: string}>> $byDay */
            $byDay = [];

            foreach ($rawSchedules as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $active = filter_var($item['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (! $active) {
                    continue;
                }

                $day = isset($item['day_of_week']) ? (int) $item['day_of_week'] : null;
                $open = isset($item['open_time']) && is_string($item['open_time']) ? $item['open_time'] : null;
                $close = isset($item['close_time']) && is_string($item['close_time']) ? $item['close_time'] : null;

                if ($day === null || $open === null || $close === null) {
                    continue;
                }

                $byDay[$day][] = [
                    'index' => (int) $index,
                    'open' => $open,
                    'close' => $close,
                ];
            }

            foreach ($byDay as $items) {
                $count = count($items);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $a = $items[$i];
                        $b = $items[$j];

                        if ($a['open'] < $b['close'] && $b['open'] < $a['close']) {
                            $validator->errors()->add(
                                "schedules.{$b['index']}.open_time",
                                'Jadwal operasional pada hari yang sama tidak boleh saling bertumpukan.'
                            );
                        }
                    }
                }
            }
        });
    }
}
