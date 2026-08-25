<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Categories/Index', [
            'categories' => Category::withCount('items')
                ->ordered()
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'colour' => $category->colour,
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->is_active,
                    'items' => $category->items_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);

        Category::create($data + ['is_active' => true]);

        return back()->with('success', "{$data['name']} added.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validateCategory($request, $category));

        return back()->with('success', "{$category->name} saved.");
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with(
            'success',
            $category->is_active ? "{$category->name} is on." : "{$category->name} is hidden.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('categories', 'name')->ignore($category?->id)->whereNull('deleted_at'),
            ],
            'colour' => ['nullable', 'string', 'in:green,rose,amber,orange,blue,violet,cyan,slate'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'name.required' => 'Enter a group name.',
            'name.unique' => 'That group already exists.',
        ]);
    }
}
