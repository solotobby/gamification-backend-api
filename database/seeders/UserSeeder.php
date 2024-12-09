<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@gmail.com', 'password' => bcrypt('solomon001'),  'role' => 'admin'],
            ['name' => 'Oluwatobi', 'email' => 'solotob@gmail.com', 'password' => bcrypt('solomon001'),  'role' => 'admin'],
            ['name' => 'Victor Admin', 'email' => 'samuel@gmail.com', 'password' => bcrypt('solomon001'),  'role' => 'admin']
        ];

        foreach ($users as $user) {
            User::updateOrCreate($user);
        }
    }
}
