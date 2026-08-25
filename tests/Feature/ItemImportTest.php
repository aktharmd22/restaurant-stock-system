<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Item;
use App\Services\Items\ItemImporter;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    seedRoles();
    $this->main = mainBranch();
    $this->owner = userWithRole(RoleName::SuperAdmin, $this->main);
    $this->vegetables = Category::factory()->create(['name' => 'Vegetables']);
});

/** Writes a sheet with the template's own headings. */
function sheet(array $rows): UploadedFile
{
    $headings = array_values(App\Services\Items\ItemImporter::COLUMNS);

    $lines = [implode(',', $headings)];

    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(fn ($cell) => (string) $cell, $row));
    }

    $path = tempnam(sys_get_temp_dir(), 'items').'.csv';
    file_put_contents($path, implode("\n", $lines));

    return new UploadedFile($path, 'items.csv', 'text/csv', null, true);
}

it('hands out a template carrying the restaurant\'s own groups', function () {
    Category::factory()->create(['name' => 'Cold drinks']);

    $response = $this->actingAs($this->owner)->get('/admin/settings/items/template');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('item-template.xlsx');
});

it('brings in new items from a filled-in sheet', function () {
    $file = sheet([
        ['Paneer', 'Vegetables', 'kg', 'g', 1000, 0.5, 'no', '', 'Cold room'],
        ['Napkins', 'Vegetables', 'packet', 'piece', 50, 1, 'no', '', 'Store'],
    ]);

    $result = app(ItemImporter::class)->import($file);

    expect($result['added'])->toBe(2)
        ->and($result['problems'])->toBeEmpty();

    $paneer = Item::where('name', 'Paneer')->first();

    expect($paneer->conversion_factor)->toBe(1000)
        ->and($paneer->step_x100)->toBe(50)
        ->and($paneer->order_unit)->toBe('kg')
        ->and($paneer->storage_location)->toBe('Cold room');
});

it('updates an item that is already on the list rather than making a second one', function () {
    kgItem('Onion', ['category_id' => $this->vegetables->id, 'storage_location' => 'Cold room']);

    $result = app(ItemImporter::class)->import(sheet([
        ['onion', 'Vegetables', 'kg', 'g', 1000, 0.5, 'no', '', 'Dry store'],
    ]));

    expect($result['updated'])->toBe(1)
        ->and($result['added'])->toBe(0)
        ->and(Item::where('name', 'like', 'onion')->count())->toBe(1)
        ->and(Item::first()->storage_location)->toBe('Dry store');
});

it('leaves existing items alone when told to', function () {
    kgItem('Onion', ['category_id' => $this->vegetables->id, 'storage_location' => 'Cold room']);

    $result = app(ItemImporter::class)->import(sheet([
        ['Onion', 'Vegetables', 'kg', 'g', 1000, 0.5, 'no', '', 'Dry store'],
    ]), updateExisting: false);

    expect($result['skipped'])->toBe(1)
        ->and(Item::first()->storage_location)->toBe('Cold room');
});

it('names the row and the reason when something cannot be read', function () {
    $result = app(ItemImporter::class)->import(sheet([
        ['Good One', 'Vegetables', 'kg', 'g', 1000, 1, 'no', '', ''],
        ['Bad Group', 'Nonsense', 'kg', 'g', 1000, 1, 'no', '', ''],
        ['Bad Unit', 'Vegetables', 'barrel', 'g', 1000, 1, 'no', '', ''],
        ['', 'Vegetables', 'kg', 'g', 1000, 1, 'no', '', ''],
        ['Goes Off', 'Vegetables', 'kg', 'g', 1000, 1, 'yes', '', ''],
    ]));

    expect($result['added'])->toBe(1)
        ->and($result['problems'])->toHaveCount(4);

    // Row numbers match what a person sees in Excel: heading is row 1.
    expect(collect($result['problems'])->pluck('row')->all())->toBe([3, 4, 5, 6]);

    expect($result['problems'][0]['problem'])->toContain('no group called "Nonsense"');
    expect($result['problems'][3]['problem'])->toContain('how many days it keeps');
});

it('never half-writes a sheet: a broken row does not stop the good ones', function () {
    app(ItemImporter::class)->import(sheet([
        ['Keeper', 'Vegetables', 'kg', 'g', 1000, 1, 'no', '', ''],
        ['Broken', 'Nonsense', 'kg', 'g', 1000, 1, 'no', '', ''],
    ]));

    expect(Item::pluck('name')->all())->toBe(['Keeper']);
});

it('brings back an item that was deleted before, rather than clashing on the name', function () {
    $item = kgItem('Cinnamon', ['category_id' => $this->vegetables->id]);
    $item->delete();

    $result = app(ItemImporter::class)->import(sheet([
        ['Cinnamon', 'Vegetables', 'kg', 'g', 1000, 1, 'no', '', 'Spice rack'],
    ]));

    expect($result['updated'])->toBe(1)
        ->and(Item::where('name', 'Cinnamon')->first()->storage_location)->toBe('Spice rack');
});

it('reads the sheet even when the columns have been moved about', function () {
    $headings = ['Group', 'Item name', 'Counted in', 'Ordered by', 'How many counted units in one ordered unit', 'Step', 'Goes off (yes/no)', 'Days it keeps', 'Where it is kept'];

    $path = tempnam(sys_get_temp_dir(), 'items').'.csv';
    file_put_contents($path, implode("\n", [
        implode(',', $headings),
        'Vegetables,Ginger,g,kg,1000,0.5,no,,Cold room',
    ]));

    $result = app(ItemImporter::class)->import(
        new UploadedFile($path, 'items.csv', 'text/csv', null, true),
    );

    expect($result['added'])->toBe(1)
        ->and(Item::first()->name)->toBe('Ginger')
        ->and(Item::first()->order_unit)->toBe('kg');
});

it('refuses a file that is not a spreadsheet', function () {
    $this->actingAs($this->owner)
        ->post('/admin/settings/items/import', [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors(['file' => 'That is not a spreadsheet. Save it as .xlsx or .csv.']);
});

it('keeps a branch user out of the import', function () {
    $staff = userWithRole(RoleName::BranchManager, subBranch());

    $this->actingAs($staff)->get('/admin/settings/items/template')->assertForbidden();
    $this->actingAs($staff)->post('/admin/settings/items/import')->assertForbidden();
});
