<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Branches/Index', [
            'branches' => Branch::withCount('users')
                ->orderByRaw("FIELD(type, 'main', 'sub')")
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'type' => $branch->type,
                    'phone' => $branch->phone,
                    'address' => $branch->address,
                    'cutoff_time' => substr($branch->cutoff_time, 0, 5),
                    'is_active' => $branch->is_active,
                    'people' => $branch->users_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Settings/Branches/Form', [
            'branch' => null,
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = Branch::create($request->validated());

        return redirect()
            ->route('admin.branches.index')
            ->with('success', "{$branch->name} added.");
    }

    public function edit(Branch $branch): Response
    {
        return Inertia::render('Admin/Settings/Branches/Form', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'type' => $branch->type,
                'phone' => $branch->phone,
                'address' => $branch->address,
                'cutoff_time' => substr($branch->cutoff_time, 0, 5),
                'is_active' => $branch->is_active,
            ],
        ]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        return redirect()
            ->route('admin.branches.index')
            ->with('success', "{$branch->name} saved.");
    }

    /**
     * Branches are switched off, never deleted - their stock history has to
     * stay readable.
     */
    public function toggle(Branch $branch): RedirectResponse
    {
        if ($branch->isMain() && $branch->is_active) {
            return back()->with('error', 'The main store cannot be switched off.');
        }

        $branch->update(['is_active' => ! $branch->is_active]);

        return back()->with(
            'success',
            $branch->is_active ? "{$branch->name} is on." : "{$branch->name} is off.",
        );
    }
}
