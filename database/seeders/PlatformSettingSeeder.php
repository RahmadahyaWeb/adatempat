<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'setting_key' => 'platform_legal_name',
                'setting_value_json' => 'PT Adatempat Digital Indonesia (Konfigurasi Awal)',
                'description' => 'Initial placeholder legal name used on invoices and legal documents. Verify before production.',
            ],
            [
                'setting_key' => 'platform_official_email',
                'setting_value_json' => 'support@adatempat.com',
                'description' => 'Official support and privacy contact email.',
            ],
            [
                'setting_key' => 'platform_official_address',
                'setting_value_json' => 'Indonesia (Diperbarui melalui Pengaturan Platform)',
                'description' => 'Initial placeholder address shown on invoices and legal documents.',
            ],
            [
                'setting_key' => 'booking_hold_duration_minutes',
                'setting_value_json' => 15,
                'description' => 'Minutes a booking slot is held while payment is pending.',
            ],
            [
                'setting_key' => 'customer_cancel_cutoff_minutes',
                'setting_value_json' => 60,
                'description' => 'Minimum minutes before booking start when customer cancellation is allowed.',
            ],
            [
                'setting_key' => 'completion_window_hours',
                'setting_value_json' => 24,
                'description' => 'Hours after venue use before a booking can be marked completed automatically.',
            ],
            [
                'setting_key' => 'dispute_owner_response_sla_hours',
                'setting_value_json' => 48,
                'description' => 'Target response time in hours for owners in a dispute.',
            ],
            [
                'setting_key' => 'default_refund_policy_percent',
                'setting_value_json' => 100,
                'description' => 'Default refund percentage when a venue has no specific refund policy.',
            ],
            [
                'setting_key' => 'default_qris_mdr_percent',
                'setting_value_json' => 0.7,
                'description' => 'Initial estimated QRIS gateway fee percentage; validate against the selected provider.',
            ],
            [
                'setting_key' => 'max_concurrent_national_ad_slots',
                'setting_value_json' => 3,
                'description' => 'Maximum number of national ad slots that may overlap on a given date.',
            ],
            [
                'setting_key' => 'monthly_ad_slot_price_idr',
                'setting_value_json' => 350000,
                'description' => 'Initial price in IDR for one national ad slot for a 30-day period.',
            ],
            [
                'setting_key' => 'ad_carousel_rotation_ms',
                'setting_value_json' => 5000,
                'description' => 'Automatic ad carousel rotation interval in milliseconds.',
            ],
            [
                'setting_key' => 'review_edit_window_days',
                'setting_value_json' => 7,
                'description' => 'Number of days after publication during which a customer may edit a review once.',
            ],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::firstOrCreate(
                ['setting_key' => $setting['setting_key']],
                $setting,
            );
        }
    }
}
