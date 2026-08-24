<?php

use App\Enums\RoleName;

beforeEach(function () {
    seedRoles();
});

it('sends a signed-out visitor to sign in', function () {
    $this->get('/b')->assertRedirect('/login');
    $this->get('/admin')->assertRedirect('/login');
});

it('refuses a branch user who types an admin address', function () {
    $staff = userWithRole(RoleName::BranchStaff, subBranch());

    $this->actingAs($staff)->get('/admin')->assertForbidden();
    $this->actingAs($staff)->get('/admin/requests')->assertForbidden();
    $this->actingAs($staff)->get('/admin/settings/business')->assertForbidden();
});

it('refuses a branch manager who types an admin address', function () {
    $manager = userWithRole(RoleName::BranchManager, subBranch());

    $this->actingAs($manager)->get('/admin')->assertForbidden();
});

it('lets the main admin into the admin app', function () {
    $admin = userWithRole(RoleName::MainAdmin, mainBranch());

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('lets the owner into the admin app', function () {
    $owner = userWithRole(RoleName::SuperAdmin, mainBranch());

    $this->actingAs($owner)->get('/admin')->assertOk();
});

it('opens the branch app for branch people', function () {
    $staff = userWithRole(RoleName::BranchStaff, subBranch());

    $this->actingAs($staff)->get('/b')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Branch/Home'));
});

it('sends an admin with no branch back to the admin app', function () {
    $admin = userWithRole(RoleName::MainAdmin, null);

    $this->actingAs($admin)->get('/b')->assertRedirect('/admin');
});
