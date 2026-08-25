<?php

use App\Enums\RoleName;

beforeEach(function () {
    seedRoles();
});

it('shows the sign in screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('signs in with an email address', function () {
    $user = userWithRole(RoleName::BranchManager, subBranch(), [
        'email' => 'staff@demo.test',
        'password' => 'password',
    ]);

    $this->post('/login', ['login' => 'staff@demo.test', 'password' => 'password'])
        ->assertRedirect('/home');

    $this->assertAuthenticatedAs($user);
});

it('signs in with a phone number, because branch staff often have no email', function () {
    $user = userWithRole(RoleName::BranchManager, subBranch(), [
        'phone' => '9876543210',
        'password' => 'password',
    ]);

    $this->post('/login', ['login' => '9876543210', 'password' => 'password'])
        ->assertRedirect('/home');

    $this->assertAuthenticatedAs($user);
});

it('ignores spaces and dashes in a typed phone number', function () {
    userWithRole(RoleName::BranchManager, subBranch(), [
        'phone' => '9876543210',
        'password' => 'password',
    ]);

    $this->post('/login', ['login' => '98765-432 10', 'password' => 'password'])
        ->assertRedirect('/home');

    $this->assertAuthenticated();
});

it('refuses a switched-off account', function () {
    userWithRole(RoleName::BranchManager, subBranch(), [
        'email' => 'gone@demo.test',
        'password' => 'password',
        'is_active' => false,
    ]);

    $this->post('/login', ['login' => 'gone@demo.test', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('explains a wrong password in plain words', function () {
    userWithRole(RoleName::BranchManager, subBranch(), [
        'email' => 'staff@demo.test',
        'password' => 'password',
    ]);

    $this->post('/login', ['login' => 'staff@demo.test', 'password' => 'wrong-one'])
        ->assertSessionHasErrors([
            'login' => 'That phone number, email or password does not match. Try again.',
        ]);

    $this->assertGuest();
});

it('sends branch people to the branch app and admins to the admin app', function () {
    $staff = userWithRole(RoleName::BranchManager, subBranch());
    $this->actingAs($staff)->get('/home')->assertRedirect('/b');

    $admin = userWithRole(RoleName::MainAdmin, mainBranch());
    $this->actingAs($admin)->get('/home')->assertRedirect('/admin');
});

it('records when someone last signed in', function () {
    $user = userWithRole(RoleName::BranchManager, subBranch(), [
        'email' => 'staff@demo.test',
        'password' => 'password',
    ]);

    expect($user->last_login_at)->toBeNull();

    $this->post('/login', ['login' => 'staff@demo.test', 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('signs out', function () {
    $user = userWithRole(RoleName::BranchManager, subBranch());

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('has no sign-up page', function () {
    $this->get('/register')->assertNotFound();
});
