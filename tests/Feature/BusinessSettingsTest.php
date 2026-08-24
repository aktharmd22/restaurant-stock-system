<?php

use App\Enums\RoleName;
use App\Support\Settings;

beforeEach(function () {
    seedRoles();
});

it('shows the restaurant name on the sign in screen', function () {
    app(Settings::class)->set('business_name', 'Spice Route Kitchens');

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('business.name', 'Spice Route Kitchens'));
});

it('lets the admin change the restaurant name', function () {
    $admin = userWithRole(RoleName::MainAdmin, mainBranch());

    $this->actingAs($admin)
        ->put('/admin/settings/business', [
            'business_name' => 'Coastal Kitchen Co',
            'business_tagline' => 'Fresh every morning.',
            'business_phone' => '9000000000',
            'business_address' => 'Dock Road',
            'currency_symbol' => '₹',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Name saved.');

    expect(app(Settings::class)->get('business_name'))->toBe('Coastal Kitchen Co');
});

it('will not accept an empty restaurant name', function () {
    $admin = userWithRole(RoleName::MainAdmin, mainBranch());

    $this->actingAs($admin)
        ->put('/admin/settings/business', [
            'business_name' => '',
            'currency_symbol' => '₹',
        ])
        ->assertSessionHasErrors(['business_name' => 'Enter the restaurant name.']);
});

it('keeps branch users out of the name settings', function () {
    $manager = userWithRole(RoleName::BranchManager, subBranch());

    $this->actingAs($manager)
        ->put('/admin/settings/business', [
            'business_name' => 'Hacked Name',
            'currency_symbol' => '₹',
        ])
        ->assertForbidden();

    expect(app(Settings::class)->get('business_name'))->not->toBe('Hacked Name');
});
