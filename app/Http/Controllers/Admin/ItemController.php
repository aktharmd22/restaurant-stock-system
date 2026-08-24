<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ItemRequest;
use App\Models\Branch;
use App\Models\BranchItemSetting;
use App\Models\Category;
use App\Models\Item;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $items = Item::with('category')
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->integer('category'), fn ($query, int $id) => $query->where('category_id', $id))
            ->ordered()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category->name,
                'unit' => $item->order_unit,
                'conversion' => $item->conversion_factor,
                'base_unit' => $item->base_unit,
                'storage_location' => $item->storage_location,
                'is_perishable' => $item->is_perishable,
                'is_active' => $item->is_active,
                'photo' => $item->photoUrl(),
            ]);

        return Inertia::render('Admin/Settings/Items/Index', [
            'items' => $items,
            'categories' => $this->categoryOptions(),
            'filters' => [
                'search' => $request->string('search')->value(),
                'category' => $request->integer('category') ?: null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Settings/Items/Form', [
            'item' => null,
            'categories' => $this->categoryOptions(),
            'branches' => $this->branchParLevels(null),
            'units' => $this->unitOptions(),
        ]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $item = Item::create($request->itemAttributes());

        $this->savePhoto($request, $item);
        $this->saveParLevels($request, $item);

        return redirect()
            ->route('admin.items.index')
            ->with('success', "{$item->name} added.");
    }

    public function edit(Item $item): Response
    {
        return Inertia::render('Admin/Settings/Items/Form', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'category_id' => $item->category_id,
                'base_unit' => $item->base_unit,
                'order_unit' => $item->order_unit,
                'conversion_factor' => $item->conversion_factor,
                'step' => $item->stepSize(),
                'is_perishable' => $item->is_perishable,
                'shelf_life_days' => $item->shelf_life_days,
                'storage_location' => $item->storage_location,
                'is_active' => $item->is_active,
                'photo' => $item->photoUrl(),
            ],
            'categories' => $this->categoryOptions(),
            'branches' => $this->branchParLevels($item),
            'units' => $this->unitOptions(),
        ]);
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->itemAttributes());

        $this->savePhoto($request, $item);
        $this->saveParLevels($request, $item);

        return redirect()
            ->route('admin.items.index')
            ->with('success', "{$item->name} saved.");
    }

    public function toggle(Item $item): RedirectResponse
    {
        $item->update(['is_active' => ! $item->is_active]);

        return back()->with(
            'success',
            $item->is_active ? "{$item->name} is back on the list." : "{$item->name} is off the list.",
        );
    }

    private function savePhoto(ItemRequest $request, Item $item): void
    {
        if ($request->hasFile('photo')) {
            $item->addMediaFromRequest('photo')->toMediaCollection('photo');
        }
    }

    /**
     * Par levels arrive in order units (what a person typed) and are stored in
     * base units, like every other quantity in the system.
     */
    private function saveParLevels(ItemRequest $request, Item $item): void
    {
        foreach ($request->input('par_levels', []) as $branchId => $levels) {
            $par = Quantity::fromOrderUnit($levels['par'] ?? 0, $item);
            $reorder = Quantity::fromOrderUnit($levels['reorder'] ?? 0, $item);

            BranchItemSetting::updateOrCreate(
                ['branch_id' => (int) $branchId, 'item_id' => $item->id],
                ['par_level' => max(0, $par->baseUnits), 'reorder_level' => max(0, $reorder->baseUnits)],
            );
        }
    }

    /** @return array<int, array{value: int, label: string}> */
    private function categoryOptions(): array
    {
        return Category::active()->ordered()->get()
            ->map(fn (Category $category) => ['value' => $category->id, 'label' => $category->name])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function branchParLevels(?Item $item): array
    {
        $settings = $item
            ? BranchItemSetting::where('item_id', $item->id)->get()->keyBy('branch_id')
            : collect();

        return Branch::active()
            ->orderByRaw("FIELD(type, 'main', 'sub')")
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch) use ($item, $settings) {
                $setting = $settings->get($branch->id);
                $factor = $item?->conversion_factor ?: 1;

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'is_main' => $branch->isMain(),
                    'par' => $setting ? round($setting->par_level / $factor, 2) : 0,
                    'reorder' => $setting ? round($setting->reorder_level / $factor, 2) : 0,
                ];
            })
            ->all();
    }

    /** @return array<string, array<int, string>> */
    private function unitOptions(): array
    {
        return [
            'base' => ['g', 'ml', 'piece'],
            'order' => ['kg', 'g', 'litre', 'ml', 'sack', 'piece', 'dozen', 'packet'],
        ];
    }
}
