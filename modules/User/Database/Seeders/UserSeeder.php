<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\User\Models\User;
use Modules\User\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear Super Admin
        $user = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'active' => true,
            ]
        );

        // Asignar rol admin
        $role = Role::where('name', 'admin')->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        // Crear Admin Polar solicitado
        $polarAdmin = User::firstOrCreate(
            ['email' => 'AdminPolar@mail.com'],
            [
                'name' => 'Admin Polar',
                'password' => Hash::make('Polar123'),
                'email_verified_at' => now(),
                'active' => true,
            ]
        );

        if ($role) {
            $polarAdmin->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
