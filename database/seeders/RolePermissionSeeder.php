<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'master.view', 'master.manage', 'package.manage',
            'trip.view', 'trip.create', 'trip.update', 'trip.cancel', 'trip.reconcile',
            'pricing.negotiate', 'payment.manage',
            'report.view', 'report.export',
            'rules.manage', 'audit.view', 'user.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Owner: semua izin
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web'])
            ->syncPermissions($permissions);

        // Operational Partner: alur operasional pada trip yang ditugaskan
        Role::firstOrCreate(['name' => 'partner', 'guard_name' => 'web'])
            ->syncPermissions(['master.view', 'trip.view', 'trip.update']);

        // Staff: estimasi, itinerary, quotation
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web'])
            ->syncPermissions(['master.view', 'trip.view', 'trip.create', 'trip.update']);
    }
}