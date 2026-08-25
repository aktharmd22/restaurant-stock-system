<?php

use App\Enums\RoleName;
use App\Models\BranchItemSetting;
use App\Services\Purchasing\PurchaseService;

beforeEach(function () {
    seedRoles();
    $this->main = mainBranch();
    $this->park = subBranch();
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
    $this->onion = kgItem('Onion');
});

/** How much of an item a branch should keep on its shelf, in order units. */
function shelf($branch, $item, float $par): void
{
    BranchItemSetting::updateOrCreate(
        ['branch_id' => $branch->id, 'item_id' => $item->id],
        ['par_level' => (int) round($par * $item->conversion_factor), 'reorder_level' => 0],
    );
}

function suggestionFor(string $name, int $mainBranchId): ?array
{
    return collect(app(PurchaseService::class)->suggestions($mainBranchId))
        ->firstWhere('name', $name);
}

it('works out what to buy from what the branches are short', function () {
    shelf($this->park, $this->onion, 30);
    giveStock($this->park, $this->onion, 10);   // 20 kg short
    giveStock($this->main, $this->onion, 5);    // only 5 kg free to send

    $onion = suggestionFor('Onion', $this->main->id);

    expect($onion)->not->toBeNull()
        ->and($onion['branches_need_text'])->toBe('20 kg')
        ->and($onion['free_at_main_text'])->toBe('5 kg')
        ->and($onion['suggested_text'])->toBe('15 kg');   // 20 needed less 5 in hand
});

/*
 * The bug this file exists for.
 *
 * Quantities are BIGINT UNSIGNED. A branch holding MORE than its full shelf
 * made par_level - qty_on_hand underflow, and MySQL aborted the whole query
 * with "BIGINT UNSIGNED value is out of range" - so one well-stocked branch
 * took the entire What-to-buy screen down with a 500.
 */
it('survives a branch holding more than its full shelf', function () {
    shelf($this->park, $this->onion, 10);
    giveStock($this->park, $this->onion, 40);   // four times its shelf

    $rows = app(PurchaseService::class)->suggestions($this->main->id);

    // Nothing is needed, so nothing is suggested - and nothing blows up.
    expect($rows->pluck('name')->all())->not->toContain('Onion');
});

it('serves the What to buy screen without a server error', function () {
    shelf($this->park, $this->onion, 10);
    giveStock($this->park, $this->onion, 40);

    $this->actingAs($this->admin)
        ->get('/admin/purchase/suggestions')
        ->assertOk();
});

it('adds up what several branches are short, not just one', function () {
    $lake = subBranch('LAKE');

    shelf($this->park, $this->onion, 20);
    shelf($lake, $this->onion, 20);
    giveStock($this->park, $this->onion, 5);    // 15 short
    giveStock($lake, $this->onion, 20);         // exactly right, nothing short

    expect(suggestionFor('Onion', $this->main->id)['branches_need_text'])->toBe('15 kg');
});

it('leaves out an item every branch already has enough of', function () {
    shelf($this->park, $this->onion, 10);
    giveStock($this->park, $this->onion, 10);

    expect(suggestionFor('Onion', $this->main->id))->toBeNull();
});
