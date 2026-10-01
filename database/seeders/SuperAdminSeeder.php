<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the application's superadmin account.
     */
    public function run(): void
    {
        $username = env('SUPERADMIN_USERNAME');
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');
        $fullName = env('SUPERADMIN_NAME');

        if (! $username || ! $password || ! $fullName) {
            throw new RuntimeException(
                'SUPERADMIN_USERNAME, SUPERADMIN_PASSWORD, dan SUPERADMIN_NAME wajib diatur di file .env.'
            );
        }

        $user = User::where('username', $username)->first();

        if ($user) {
            $user->update([
                'email' => $email ?: $user->email,
                'full_name' => $fullName,
                'role_code' => 'superadmin',
                'is_active' => true,
            ]);

            $this->command->info(
                "Superadmin [{$username}] sudah ada dan telah disinkronkan."
            );

            return;
        }

        User::create([
            'id' => (string) str()->uuid(),
            'username' => $username,
            'email' => $email ?: null,
            'password_hash' => Hash::make($password),
            'full_name' => $fullName,
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);

        $this->command->info(
            "Superadmin [{$username}] berhasil dibuat."
        );
    }
}