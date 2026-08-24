<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Permission names are read by people in the admin screens, so they stay
     * readable: area.action.
     */
    public const PERMISSIONS = [
        'requests.create',
        'requests.approve',
        'requests.dispatch',
        'requests.receive',
        'requests.cancel',
        'stock.view',
        'stock.count',
        'stock.adjust',
        'waste.record',
        'local_purchase.request',
        'local_purchase.approve',
        'purchase.manage',
        'reports.view',
        'settings.manage',
        'users.manage',
        'branches.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            RoleName::SuperAdmin->value => self::PERMISSIONS,

            RoleName::MainAdmin->value => [
                'requests.approve', 'requests.dispatch', 'requests.cancel',
                'stock.view', 'stock.count', 'stock.adjust',
                'waste.record', 'local_purchase.approve',
                'purchase.manage', 'reports.view', 'settings.manage',
            ],

            RoleName::BranchManager->value => [
                'requests.create', 'requests.receive', 'requests.cancel',
                'stock.view', 'stock.count', 'waste.record',
                'local_purchase.request', 'reports.view',
            ],

            RoleName::BranchStaff->value => [
                'requests.create', 'requests.receive',
                'stock.view', 'waste.record', 'local_purchase.request',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
    }
}
