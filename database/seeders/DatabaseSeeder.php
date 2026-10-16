<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Admin', 'admin@example.com', Role::Admin],
            ['Agent One', 'agent1@example.com', Role::Agent],
            ['Agent Two', 'agent2@example.com', Role::Agent],
            ['Customer One', 'customer1@example.com', Role::Customer],
            ['Customer Two', 'customer2@example.com', Role::Customer],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'role' => $role, 'email_verified_at' => now()],
            );
        }

        foreach (['Billing', 'Technical', 'Account', 'General'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        $this->call(SlaPolicySeeder::class);
    }
}