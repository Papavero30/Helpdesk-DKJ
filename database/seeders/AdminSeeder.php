<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ithelp.local'],
            [
                'name' => 'Administrator IT',
                'password' => bcrypt('admin123'),
            ]
        );
    }
}
