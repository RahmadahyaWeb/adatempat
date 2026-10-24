<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) env('SUPER_ADMIN_NAME', ''));
        $email = strtolower(trim((string) env('SUPER_ADMIN_EMAIL', '')));
        $password = (string) env('SUPER_ADMIN_PASSWORD', '');

        if ($name === '') {
            throw new RuntimeException(
                'SUPER_ADMIN_NAME wajib diisi di file .env.'
            );
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'SUPER_ADMIN_EMAIL belum diisi atau format email tidak valid.'
            );
        }

        if (strlen($password) < 12) {
            throw new RuntimeException(
                'SUPER_ADMIN_PASSWORD wajib berisi minimal 12 karakter.'
            );
        }

        $existingUser = User::query()
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            if (! $existingUser->isSuperAdmin()) {
                throw new RuntimeException(
                    'Email Super Admin sudah digunakan akun dengan role berbeda. '
                        . 'Seeder dihentikan tanpa mengubah akun tersebut.'
                );
            }

            $this->command?->info(
                'Akun Super Admin sudah tersedia. Tidak ada perubahan pada akun.'
            );

            return;
        }

        $user = new User();

        $user->forceFill([
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
        ])->save();

        $this->command?->info(
            'Akun Super Admin berhasil dibuat: ' . $email
        );
    }
}
