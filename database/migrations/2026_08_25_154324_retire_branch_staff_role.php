<?php

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Three roles instead of four.
 *
 * Branch staff and branch manager did almost the same job - the difference was
 * cancelling a request and reading a report - and every restaurant using this
 * was picking one of them at random. So the role goes.
 *
 * Anyone holding it becomes a branch manager rather than being left with no
 * role at all: their name is on ledger rows and old requests, and a person
 * with no role cannot sign in to explain them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $staff = Role::where('name', 'branch_staff')->where('guard_name', 'web')->first();

        if (! $staff) {
            return;
        }

        $manager = Role::firstOrCreate(
            ['name' => RoleName::BranchManager->value, 'guard_name' => 'web'],
        );

        // Move people across, skipping anyone who is already a manager so
        // nobody ends up holding the role twice.
        DB::table('model_has_roles')
            ->where('role_id', $staff->id)
            ->whereNotExists(function ($query) use ($manager) {
                $query->select(DB::raw(1))
                    ->from('model_has_roles as held')
                    ->whereColumn('held.model_id', 'model_has_roles.model_id')
                    ->whereColumn('held.model_type', 'model_has_roles.model_type')
                    ->where('held.role_id', $manager->id);
            })
            ->update(['role_id' => $manager->id]);

        // Whatever is left is a duplicate pairing, safe to drop.
        DB::table('model_has_roles')->where('role_id', $staff->id)->delete();

        $staff->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The role comes back with what it used to hold, but the people who were
     * moved cannot be told apart from managers any more, so they stay put.
     */
    public function down(): void
    {
        $staff = Role::firstOrCreate(['name' => 'branch_staff', 'guard_name' => 'web']);

        /*
         * Only the permissions this database actually has. A rollback runs on
         * every kind of database, including a freshly migrated one with
         * nothing seeded into it yet, and a migration that throws there takes
         * the whole rollback down with it.
         */
        $wanted = [
            'requests.create', 'requests.receive',
            'stock.view', 'waste.record', 'local_purchase.request',
        ];

        $staff->syncPermissions(
            Permission::whereIn('name', $wanted)->where('guard_name', 'web')->get(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
