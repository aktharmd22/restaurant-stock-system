<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Users/Index', [
            'people' => User::with(['branch', 'roles'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'branch' => $user->branch?->name,
                    'role' => $user->getRoleNames()->first(),
                    'role_label' => $this->roleLabel($user->getRoleNames()->first()),
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at?->diffForHumans(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Settings/Users/Form', [
            'person' => null,
            'branches' => $this->branchOptions(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'branch_id' => $data['branch_id'],
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "{$user->name} added.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Settings/Users/Form', [
            'person' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_id' => $user->branch_id,
                'role' => $user->getRoleNames()->first(),
                'is_active' => $user->is_active,
            ],
            'branches' => $this->branchOptions(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'branch_id' => $data['branch_id'],
            'is_active' => $data['is_active'] ?? true,
            ...(filled($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "{$user->name} saved.");
    }

    /**
     * A new password, shown once to the admin so they can pass it on. These
     * users lose passwords and there is no email to send a link to.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $newPassword = Str::upper(Str::random(3)).random_int(1000, 9999);

        $user->forceFill(['password' => $newPassword])->save();

        return back()->with('success', "New password for {$user->firstName()}: {$newPassword}");
    }

    public function toggle(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot switch off your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with(
            'success',
            $user->is_active ? "{$user->firstName()} can sign in." : "{$user->firstName()} cannot sign in now.",
        );
    }

    /** @return array<int, array{value: int, label: string}> */
    private function branchOptions(): array
    {
        return Branch::active()
            ->orderByRaw("FIELD(type, 'main', 'sub')")
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => ['value' => $branch->id, 'label' => $branch->name])
            ->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function roleOptions(): array
    {
        return array_map(fn (RoleName $role) => [
            'value' => $role->value,
            'label' => $role->label(),
        ], RoleName::cases());
    }

    private function roleLabel(?string $role): string
    {
        return $role ? (RoleName::tryFrom($role)?->label() ?? $role) : 'No role';
    }
}
