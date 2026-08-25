<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Purchase/Suppliers', [
            'suppliers' => Supplier::withCount('purchaseOrders')
                ->orderBy('name')
                ->get()
                ->map(fn (Supplier $supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'contact_person' => $supplier->contact_person,
                    'phone' => $supplier->phone,
                    'address' => $supplier->address,
                    'is_active' => $supplier->is_active,
                    'orders' => $supplier->purchase_orders_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request) + ['is_active' => true]);

        return back()->with('success', "{$supplier->name} added.");
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return back()->with('success', "{$supplier->name} saved.");
    }

    public function toggle(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return back()->with(
            'success',
            $supplier->is_active ? "{$supplier->name} is on." : "{$supplier->name} is off.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('suppliers', 'name')->ignore($supplier?->id)->whereNull('deleted_at'),
            ],
            'contact_person' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Enter the supplier name.',
            'name.unique' => 'That supplier is already on the list.',
        ]);
    }

    /**
     * Orders keep their supplier's name for as long as the ledger does, so a
     * supplier you have ordered from is switched off rather than deleted.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $orders = PurchaseOrder::where('supplier_id', $supplier->id)->count();

        if ($orders > 0) {
            return back()->with(
                'error',
                "{$supplier->name} is on {$orders} order".($orders === 1 ? '' : 's').", so it cannot be deleted. Switch it off instead.",
            );
        }

        $name = $supplier->name;
        $supplier->delete();

        return back()->with('success', "{$name} deleted.");
    }
}
