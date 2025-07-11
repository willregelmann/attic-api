<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'will@regelmann.net'],
            [
                'name' => 'Will Regelmann',
                'google_id' => '102644639098766923825',
                'email_verified_at' => now(),
            ]
        );

        $token = $user->createToken('test-token');
        
        echo "User ID: {$user->id}\n";
        echo "Token: {$token->plainTextToken}\n";
    }
}