<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = env('ADMIN_INITIAL_PASSWORD');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('ADMIN_INITIAL_PASSWORD must be set in .env before seeding.');
        }

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'ITMS Administrator',
                'email' => 'admin@itms.local',
                'password' => $password,
                'is_active' => true,
            ],
        );

        foreach (['ITMS', 'Accounting Department', 'Human Capital Department'] as $name) {
            Department::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
