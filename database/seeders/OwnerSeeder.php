<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $accounts = [
            ['Owner SOJ',   'owner@soj.test',   'owner',   true],
            ['Partner SOJ', 'partner@soj.test', 'partner', false],
            ['Staff SOJ',   'staff@soj.test',   'staff',   false],
        ];

        foreach ($accounts as [$name, $email, $role, $finance]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'role' => $role,
                    'status' => 'active',
                    'financial_visibility' => $finance,
                ]
            );
            if ($user->wasRecentlyCreated) {
                $user->syncRoles([$role]);
            }
        }
    }
}