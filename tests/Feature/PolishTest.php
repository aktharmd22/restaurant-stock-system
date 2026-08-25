<?php

use App\Enums\RoleName;
use App\Models\StockRequest;
use App\Support\Settings;

beforeEach(function () {
    seedRoles();

    $this->main = mainBranch();
    $this->park = subBranch('PARK');
    $this->onion = kgItem('Onion');
    giveStock($this->main, $this->onion, 100);

    $this->staff = userWithRole(RoleName::BranchStaff, $this->park, ['phone' => '9000000201']);
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
});

/*
|--------------------------------------------------------------------------
| Installing it on a phone
|--------------------------------------------------------------------------
*/

it('names the home screen icon after the restaurant', function () {
    app(Settings::class)->set('business_name', 'Spice Route Kitchens');

    $response = $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('content-type', 'application/manifest+json');

    $manifest = $response->json();

    expect($manifest['name'])->toBe('Spice Route Kitchens')
        ->and($manifest['start_url'])->toBe('/home')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(2);
});

it('has an offline page that needs no javascript', function () {
    $response = $this->get('/offline')->assertOk();

    expect($response->getContent())
        ->toContain('No internet')
        ->toContain('send by itself')
        // The whole point is that it works when the build assets did not load.
        ->not->toContain('/build/');
});

it('ships the icons the manifest points at', function () {
    expect(file_exists(public_path('icons/icon-192.png')))->toBeTrue()
        ->and(file_exists(public_path('icons/icon-512.png')))->toBeTrue()
        ->and(file_exists(public_path('sw.js')))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Bad connections
|--------------------------------------------------------------------------
*/

/**
 * This is what makes the offline retry queue safe. Without it, a phone that
 * lost its signal mid-send would re-send on reconnect and the branch would get
 * double the stock.
 */
it('does not create a second request when a queued send is retried', function () {
    $payload = [
        'lines' => [['item_id' => $this->onion->id, 'qty' => 10]],
        'client_token' => 'phone-token-abc123',
    ];

    $this->actingAs($this->staff)->post('/b/ask', $payload)->assertRedirect();
    $this->actingAs($this->staff)->post('/b/ask', $payload)->assertRedirect();

    expect(StockRequest::withoutBranchScope()->count())->toBe(1);
});

it('still treats two genuinely separate requests as separate', function () {
    $this->actingAs($this->staff)->post('/b/ask', [
        'lines' => [['item_id' => $this->onion->id, 'qty' => 10]],
        'client_token' => 'token-one',
    ]);

    $this->actingAs($this->staff)->post('/b/ask', [
        'lines' => [['item_id' => $this->onion->id, 'qty' => 4]],
        'client_token' => 'token-two',
    ]);

    expect(StockRequest::withoutBranchScope()->count())->toBe(2);
});

it('accepts a send with no token at all, for anyone with a working connection', function () {
    $this->actingAs($this->staff)
        ->post('/b/ask', ['lines' => [['item_id' => $this->onion->id, 'qty' => 3]]])
        ->assertRedirect();

    expect(StockRequest::withoutBranchScope()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Your own details
|--------------------------------------------------------------------------
*/

it('lets someone fix their own name and phone number', function () {
    $this->actingAs($this->staff)
        ->put('/settings/profile', [
            'name' => 'Rahul Das',
            'phone' => '98765 43210',
            'email' => 'rahul@demo.test',
        ])
        ->assertSessionHas('success', 'Saved.');

    expect($this->staff->fresh()->name)->toBe('Rahul Das')
        ->and($this->staff->fresh()->phone)->toBe('9876543210');
});

it('will not let two people share a phone number', function () {
    $other = userWithRole(RoleName::BranchStaff, $this->park, ['phone' => '9000000999']);

    $this->actingAs($this->staff)
        ->put('/settings/profile', ['name' => 'Someone', 'phone' => '9000000999'])
        ->assertSessionHasErrors(['phone' => 'Someone else already uses that phone number.']);
});

it('lets someone change their own password', function () {
    $user = userWithRole(RoleName::BranchStaff, $this->park, ['password' => 'old-password']);

    $this->actingAs($user)
        ->put('/password', [
            'current_password' => 'old-password',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])
        ->assertSessionHasNoErrors();

    $this->post('/logout');

    $this->post('/login', ['login' => $user->phone, 'password' => 'a-new-password'])
        ->assertRedirect('/home');
});

/*
|--------------------------------------------------------------------------
| When something goes wrong
|--------------------------------------------------------------------------
*/

it('explains a refused page in plain words instead of showing a number', function () {
    $this->actingAs($this->staff)
        ->get('/admin')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
});

it('explains a missing page the same way', function () {
    $this->actingAs($this->staff)
        ->get('/b/requests/999999')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});
