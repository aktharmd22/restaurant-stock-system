<?php

use App\Enums\RoleName;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    $this->main = mainBranch();
    $this->park = subBranch();
    $this->owner = userWithRole(RoleName::SuperAdmin, $this->main);
});

/*
 * A phone number or an email, whichever the person actually has. A kitchen
 * hand has a phone and no email; someone in the office is often the other way
 * round. The sign-in screen has always accepted both - it was the People form
 * that insisted on a phone, so an email-only account could not be made at all
 * without going into the database by hand.
 */

it('adds someone with a phone and no email', function () {
    $this->actingAs($this->owner)
        ->post('/admin/settings/users', [
            'name' => 'Kitchen Hand',
            'phone' => '9876500001',
            'email' => '',
            'branch_id' => $this->park->id,
            'role' => RoleName::BranchManager->value,
            'password' => 'first-password',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/settings/users');

    $person = User::where('phone', '9876500001')->first();

    expect($person)->not->toBeNull()
        ->and($person->email)->toBeNull();
});

it('adds someone with an email and no phone', function () {
    $this->actingAs($this->owner)
        ->post('/admin/settings/users', [
            'name' => 'Office Admin',
            'phone' => '',
            'email' => 'office@example.test',
            'branch_id' => $this->main->id,
            'role' => RoleName::MainAdmin->value,
            'password' => 'first-password',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/settings/users');

    $person = User::where('email', 'office@example.test')->first();

    expect($person)->not->toBeNull()
        ->and($person->phone)->toBeNull();
});

it('asks for one of them when both are blank', function () {
    $this->actingAs($this->owner)
        ->post('/admin/settings/users', [
            'name' => 'Nobody',
            'phone' => '',
            'email' => '',
            'branch_id' => $this->park->id,
            'role' => RoleName::BranchManager->value,
            'password' => 'first-password',
        ])
        ->assertSessionHasErrors([
            'phone' => 'Give them a phone number or an email. They sign in with one of them.',
        ]);

    expect(User::where('name', 'Nobody')->exists())->toBeFalse();
});

it('lets two people both have no email without colliding', function () {
    foreach (['9876500002', '9876500003'] as $phone) {
        $this->actingAs($this->owner)->post('/admin/settings/users', [
            'name' => "Person {$phone}",
            'phone' => $phone,
            'email' => '',
            'branch_id' => $this->park->id,
            'role' => RoleName::BranchManager->value,
            'password' => 'first-password',
            'is_active' => true,
        ])->assertRedirect('/admin/settings/users');
    }

    expect(User::whereNull('email')->count())->toBe(2);
});

it('signs in with the email the admin set', function () {
    $this->actingAs($this->owner)->post('/admin/settings/users', [
        'name' => 'Office Admin',
        'phone' => '',
        'email' => 'office@example.test',
        'branch_id' => $this->main->id,
        'role' => RoleName::MainAdmin->value,
        'password' => 'Welcome321',
        'is_active' => true,
    ]);

    auth()->logout();

    $this->post('/login', ['login' => 'office@example.test', 'password' => 'Welcome321'])
        ->assertRedirect();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->email)->toBe('office@example.test');
});

it('signs in with the phone number the admin set', function () {
    $this->actingAs($this->owner)->post('/admin/settings/users', [
        'name' => 'Kitchen Hand',
        'phone' => '9876500004',
        'email' => '',
        'branch_id' => $this->park->id,
        'role' => RoleName::BranchManager->value,
        'password' => 'Welcome321',
        'is_active' => true,
    ]);

    auth()->logout();

    $this->post('/login', ['login' => '9876500004', 'password' => 'Welcome321'])
        ->assertRedirect();

    expect(auth()->check())->toBeTrue();
});

it('still refuses two people sharing an email', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($this->owner)
        ->post('/admin/settings/users', [
            'name' => 'Copycat',
            'phone' => '9876500005',
            'email' => 'taken@example.test',
            'branch_id' => $this->park->id,
            'role' => RoleName::BranchManager->value,
            'password' => 'first-password',
        ])
        ->assertSessionHasErrors(['email' => 'Someone else already uses that email.']);
});
