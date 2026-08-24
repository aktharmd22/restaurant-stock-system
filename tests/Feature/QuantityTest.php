<?php

use App\Models\Category;
use App\Models\Item;
use App\Support\Quantity;

/**
 * Units are the number one source of silent data corruption in stock systems:
 * a "5" that means kilograms in one place and grams in another. These tests
 * pin the conversion down in both directions.
 */
function makeItem(array $attributes = []): Item
{
    return Item::factory()->create([
        'category_id' => Category::factory(),
        ...$attributes,
    ]);
}

it('turns kilograms into grams', function () {
    $rice = makeItem(['order_unit' => 'kg', 'base_unit' => 'g', 'conversion_factor' => 1000]);

    expect(Quantity::fromOrderUnit(2.5, $rice)->baseUnits)->toBe(2500)
        ->and(Quantity::fromOrderUnit(0.25, $rice)->baseUnits)->toBe(250)
        ->and(Quantity::fromOrderUnit(40, $rice)->baseUnits)->toBe(40000);
});

it('turns grams back into a readable amount', function () {
    $rice = makeItem(['order_unit' => 'kg', 'base_unit' => 'g', 'conversion_factor' => 1000]);

    expect($rice->quantity(2500)->forDisplay())->toBe('2.5 kg')
        ->and($rice->quantity(1000)->forDisplay())->toBe('1 kg')
        ->and($rice->quantity(250)->forDisplay())->toBe('0.25 kg');
});

it('handles litres and millilitres', function () {
    $milk = makeItem(['order_unit' => 'litre', 'base_unit' => 'ml', 'conversion_factor' => 1000]);

    expect(Quantity::fromOrderUnit(1.5, $milk)->baseUnits)->toBe(1500)
        ->and($milk->quantity(750)->forDisplay())->toBe('0.75 L');
});

it('handles a sack that is not a round unit', function () {
    $rice = makeItem(['order_unit' => 'sack', 'base_unit' => 'g', 'conversion_factor' => 25000]);

    expect(Quantity::fromOrderUnit(2, $rice)->baseUnits)->toBe(50000)
        ->and($rice->quantity(50000)->forDisplay())->toBe('2 sacks')
        ->and($rice->quantity(25000)->forDisplay())->toBe('1 sack');
});

it('counts pieces as whole things', function () {
    $eggs = makeItem(['order_unit' => 'piece', 'base_unit' => 'piece', 'conversion_factor' => 1]);

    expect(Quantity::fromOrderUnit(30, $eggs)->baseUnits)->toBe(30)
        ->and($eggs->quantity(30)->forDisplay())->toBe('30 pcs')
        ->and($eggs->quantity(1)->forDisplay())->toBe('1 pc');
});

it('survives a round trip', function () {
    $item = makeItem(['order_unit' => 'kg', 'base_unit' => 'g', 'conversion_factor' => 1000]);

    foreach ([0.1, 0.25, 1, 2.5, 7.75, 40, 123.5] as $amount) {
        expect(Quantity::fromOrderUnit($amount, $item)->toOrderUnit())->toBe((float) $amount);
    }
});

it('adds and subtracts without losing anything', function () {
    $item = makeItem(['conversion_factor' => 1000]);

    $total = Quantity::fromOrderUnit(1.1, $item)
        ->plus(Quantity::fromOrderUnit(2.2, $item))
        ->plus(Quantity::fromOrderUnit(3.3, $item));

    // A float sum of 1.1 + 2.2 + 3.3 would drift. Whole base units cannot.
    expect($total->baseUnits)->toBe(6600)
        ->and($total->forDisplay())->toBe('6.6 kg');
});

it('refuses to mix two different items', function () {
    $rice = makeItem(['name' => 'Rice']);
    $oil = makeItem(['name' => 'Oil']);

    expect(fn () => $rice->quantity(1000)->plus($oil->quantity(1000)))
        ->toThrow(InvalidArgumentException::class);
});

it('never stores a fraction of a base unit', function () {
    $item = makeItem(['order_unit' => 'kg', 'base_unit' => 'g', 'conversion_factor' => 1000]);

    $quantity = Quantity::fromOrderUnit(0.3333, $item);

    expect($quantity->baseUnits)->toBeInt()->toBe(333);
});
