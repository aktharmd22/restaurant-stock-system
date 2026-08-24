<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $itemId = $this->route('item')?->id;

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('items', 'name')->ignore($itemId)->whereNull('deleted_at'),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'base_unit' => ['required', Rule::in(['g', 'ml', 'piece'])],
            'order_unit' => ['required', Rule::in(['kg', 'g', 'litre', 'ml', 'sack', 'piece', 'dozen', 'packet'])],
            'conversion_factor' => ['required', 'integer', 'min:1', 'max:1000000'],
            'step' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'is_perishable' => ['boolean'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:is_perishable,true'],
            'storage_location' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],

            'par_levels' => ['array'],
            'par_levels.*.par' => ['nullable', 'numeric', 'min:0'],
            'par_levels.*.reorder' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the item name.',
            'name.unique' => 'That item is already on the list.',
            'category_id.required' => 'Pick a group for this item.',
            'conversion_factor.required' => 'Say how many small units are in one order unit.',
            'shelf_life_days.required_if' => 'Say how many days it keeps.',
            'photo.image' => 'That file is not a picture.',
            'photo.max' => 'That picture is too big. Use one under 4 MB.',
        ];
    }

    /**
     * Attributes for the item row itself. The step is typed in order units and
     * stored as a whole number times 100, so no float reaches the database.
     *
     * @return array<string, mixed>
     */
    public function itemAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'base_unit' => $validated['base_unit'],
            'order_unit' => $validated['order_unit'],
            'conversion_factor' => $validated['conversion_factor'],
            'step_x100' => max(1, (int) round(((float) $validated['step']) * 100)),
            'is_perishable' => $validated['is_perishable'] ?? false,
            'shelf_life_days' => ($validated['is_perishable'] ?? false) ? $validated['shelf_life_days'] : null,
            'storage_location' => $validated['storage_location'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }
}
