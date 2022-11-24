<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{

    public function run()
    {
        DB::table('users')->delete();
        DB::statement("ALTER TABLE `users` AUTO_INCREMENT = 1");

        $data = [
            [
                'name'                     => 'Mr. Sakib',
                'email'                    => 'admin@gmail.com',
                'password'                 => Hash::make('12345Ab!'),
            ]
        ];
        User::insert($data);
    }
}
