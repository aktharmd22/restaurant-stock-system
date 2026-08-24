<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchItemSetting;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Seeder;

/**
 * A realistic Indian restaurant catalogue: 60 items in 6 categories, so the
 * app can be demonstrated without typing anything in.
 *
 * Columns: name, order unit, base unit, conversion, step (order units x100),
 * perishable days (null = keeps), storage location, par level (order units).
 */
class ItemSeeder extends Seeder
{
    private const CATALOGUE = [
        'Vegetables' => [
            ['Onion', 'kg', 'g', 1000, 100, 14, 'Cold room', 40],
            ['Tomato', 'kg', 'g', 1000, 100, 5, 'Cold room', 25],
            ['Potato', 'kg', 'g', 1000, 100, 21, 'Dry store', 40],
            ['Ginger', 'kg', 'g', 1000, 25, 10, 'Cold room', 6],
            ['Garlic', 'kg', 'g', 1000, 25, 21, 'Dry store', 6],
            ['Green chilli', 'kg', 'g', 1000, 25, 5, 'Cold room', 4],
            ['Coriander leaves', 'kg', 'g', 1000, 25, 3, 'Cold room', 3],
            ['Capsicum', 'kg', 'g', 1000, 50, 7, 'Cold room', 8],
            ['Cabbage', 'kg', 'g', 1000, 50, 10, 'Cold room', 10],
            ['Carrot', 'kg', 'g', 1000, 50, 10, 'Cold room', 10],
            ['Lemon', 'piece', 'piece', 1, 100, 14, 'Cold room', 60],
            ['Curry leaves', 'kg', 'g', 1000, 10, 4, 'Cold room', 1],
        ],

        'Meat and fish' => [
            ['Chicken with bone', 'kg', 'g', 1000, 50, 2, 'Freezer', 30],
            ['Boneless chicken', 'kg', 'g', 1000, 50, 2, 'Freezer', 20],
            ['Mutton', 'kg', 'g', 1000, 50, 2, 'Freezer', 12],
            ['Fish (rohu)', 'kg', 'g', 1000, 50, 1, 'Freezer', 10],
            ['Prawns', 'kg', 'g', 1000, 25, 1, 'Freezer', 8],
            ['Crab', 'kg', 'g', 1000, 25, 1, 'Freezer', 5],
            ['Chicken liver', 'kg', 'g', 1000, 25, 1, 'Freezer', 4],
            ['Eggs', 'piece', 'piece', 1, 600, 14, 'Cold room', 180],
        ],

        'Grains and staples' => [
            ['Basmati rice', 'sack', 'g', 25000, 100, null, 'Dry store', 4],
            ['Sona masoori rice', 'sack', 'g', 25000, 100, null, 'Dry store', 4],
            ['Wheat flour', 'kg', 'g', 1000, 100, null, 'Dry store', 40],
            ['Maida', 'kg', 'g', 1000, 100, null, 'Dry store', 25],
            ['Sugar', 'kg', 'g', 1000, 100, null, 'Dry store', 25],
            ['Salt', 'kg', 'g', 1000, 100, null, 'Dry store', 15],
            ['Toor dal', 'kg', 'g', 1000, 100, null, 'Dry store', 20],
            ['Chana dal', 'kg', 'g', 1000, 100, null, 'Dry store', 15],
            ['Urad dal', 'kg', 'g', 1000, 100, null, 'Dry store', 12],
            ['Moong dal', 'kg', 'g', 1000, 100, null, 'Dry store', 12],
            ['Besan', 'kg', 'g', 1000, 50, null, 'Dry store', 10],
            ['Semolina', 'kg', 'g', 1000, 50, null, 'Dry store', 8],
            ['Poha', 'kg', 'g', 1000, 50, null, 'Dry store', 6],
            ['Tamarind', 'kg', 'g', 1000, 25, null, 'Dry store', 5],
        ],

        'Spices' => [
            ['Turmeric powder', 'kg', 'g', 1000, 25, null, 'Spice rack', 4],
            ['Red chilli powder', 'kg', 'g', 1000, 25, null, 'Spice rack', 6],
            ['Coriander powder', 'kg', 'g', 1000, 25, null, 'Spice rack', 6],
            ['Cumin seeds', 'kg', 'g', 1000, 25, null, 'Spice rack', 4],
            ['Mustard seeds', 'kg', 'g', 1000, 25, null, 'Spice rack', 3],
            ['Garam masala', 'kg', 'g', 1000, 10, null, 'Spice rack', 3],
            ['Black pepper', 'kg', 'g', 1000, 10, null, 'Spice rack', 2],
            ['Cardamom', 'kg', 'g', 1000, 10, null, 'Spice rack', 1],
            ['Cloves', 'kg', 'g', 1000, 10, null, 'Spice rack', 1],
            ['Cinnamon', 'kg', 'g', 1000, 10, null, 'Spice rack', 1],
            ['Bay leaf', 'kg', 'g', 1000, 10, null, 'Spice rack', 1],
            ['Fenugreek seeds', 'kg', 'g', 1000, 10, null, 'Spice rack', 2],
        ],

        'Dairy and oils' => [
            ['Milk', 'litre', 'ml', 1000, 100, 2, 'Cold room', 60],
            ['Curd', 'kg', 'g', 1000, 50, 4, 'Cold room', 20],
            ['Paneer', 'kg', 'g', 1000, 25, 3, 'Cold room', 10],
            ['Butter', 'kg', 'g', 1000, 25, 30, 'Cold room', 8],
            ['Ghee', 'kg', 'g', 1000, 25, 90, 'Dry store', 6],
            ['Cheese', 'kg', 'g', 1000, 25, 20, 'Cold room', 5],
            ['Sunflower oil', 'litre', 'ml', 1000, 100, null, 'Dry store', 40],
            ['Mustard oil', 'litre', 'ml', 1000, 100, null, 'Dry store', 15],
        ],

        'Packaging and supplies' => [
            ['Foil container 500 ml', 'piece', 'piece', 1, 5000, null, 'Packaging store', 1000],
            ['Foil container 750 ml', 'piece', 'piece', 1, 5000, null, 'Packaging store', 600],
            ['Paper bag', 'piece', 'piece', 1, 10000, null, 'Packaging store', 1500],
            ['Carry bag', 'piece', 'piece', 1, 10000, null, 'Packaging store', 2000],
            ['Tissue paper', 'packet', 'piece', 100, 100, null, 'Packaging store', 30],
            ['Cling film roll', 'piece', 'piece', 1, 100, null, 'Packaging store', 12],
        ],
    ];

    public function run(): void
    {
        $categorySort = 0;

        foreach (self::CATALOGUE as $categoryName => $items) {
            $category = Category::updateOrCreate(
                ['name' => $categoryName],
                ['sort_order' => $categorySort += 10, 'is_active' => true],
            );

            $itemSort = 0;

            foreach ($items as [$name, $orderUnit, $baseUnit, $factor, $step, $shelfLife, $location, $parOrderUnits]) {
                $item = Item::updateOrCreate(
                    ['name' => $name],
                    [
                        'category_id' => $category->id,
                        'base_unit' => $baseUnit,
                        'order_unit' => $orderUnit,
                        'conversion_factor' => $factor,
                        'step_x100' => $step,
                        'is_perishable' => $shelfLife !== null,
                        'shelf_life_days' => $shelfLife,
                        'storage_location' => $location,
                        'sort_order' => $itemSort += 10,
                        'is_active' => true,
                    ],
                );

                $this->setParLevels($item, $parOrderUnits);
            }
        }
    }

    /**
     * Par level is the "full shelf" figure a branch works back from. The main
     * store holds much more than a branch does, so it gets a bigger number.
     */
    private function setParLevels(Item $item, int $parOrderUnits): void
    {
        foreach (Branch::all() as $branch) {
            $multiplier = $branch->isMain() ? 3 : 1;
            $parBase = $parOrderUnits * $multiplier * $item->conversion_factor;

            BranchItemSetting::updateOrCreate(
                ['branch_id' => $branch->id, 'item_id' => $item->id],
                [
                    'par_level' => $parBase,
                    // Below a third of par is "running low".
                    'reorder_level' => (int) round($parBase / 3),
                ],
            );
        }
    }
}
