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

        /*
         * Three roles. Two run the business and one runs a kitchen.
         *
         * Owner and Admin hold the same permissions on purpose: both are
         * meant to handle everything, including the main store. The titles
         * still differ because who somebody is matters when you are reading
         * a history page months later.
         */
        $roles = [
            RoleName::SuperAdmin->value => self::PERMISSIONS,
            RoleName::MainAdmin->value => self::PERMISSIONS,

            RoleName::BranchManager->value => [
                'requests.create', 'requests.receive', 'requests.cancel',
                'stock.view', 'stock.count', 'waste.record',
                'local_purchase.request', 'reports.view',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
    }
}
