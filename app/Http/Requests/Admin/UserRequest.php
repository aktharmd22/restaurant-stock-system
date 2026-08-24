<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isNew = $userId === null;

        return [
            'name' => ['required', 'string', 'max:60'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('users', 'phone')->ignore($userId)->whereNull('deleted_at'),
            ],
            'branch_id' => ['required', 'exists:branches,id'],
            'role' => ['required', Rule::in(RoleName::values())],
            'password' => [$isNew ? 'required' : 'nullable', 'string', 'min:8'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter their name.',
            'phone.required' => 'Enter a phone number. They sign in with it.',
            'phone.unique' => 'Someone else already uses that phone number.',
            'email.unique' => 'Someone else already uses that email.',
            'branch_id.required' => 'Pick which branch they work at.',
            'role.required' => 'Pick what they are allowed to do.',
            'password.required' => 'Set a first password. You can tell them what it is.',
            'password.min' => 'Use at least 8 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => preg_replace('/[^0-9+]/', '', (string) $this->input('phone'))]);
        }
    }
}
