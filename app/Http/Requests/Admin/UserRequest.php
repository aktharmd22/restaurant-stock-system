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
            /*
             * Either one is a sign-in, and one of them has to be there.
             * A kitchen hand has a phone and no email; an office admin is
             * often the other way round. The sign-in screen already accepts
             * both, and this used to demand a phone regardless - so the only
             * way to make an email-only account was by hand in the database.
             */
            'email' => [
                'required_without:phone', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'phone' => [
                'required_without:email', 'nullable', 'string', 'max:20',
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
            'phone.required_without' => 'Give them a phone number or an email. They sign in with one of them.',
            'phone.unique' => 'Someone else already uses that phone number.',
            'email.required_without' => 'Give them an email or a phone number. They sign in with one of them.',
            'email.unique' => 'Someone else already uses that email.',
            'email.email' => 'That does not look like an email address.',
            'branch_id.required' => 'Pick which branch they work at.',
            'role.required' => 'Pick what they are allowed to do.',
            'password.required' => 'Set a first password. You can tell them what it is.',
            'password.min' => 'Use at least 8 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = preg_replace('/[^0-9+]/', '', (string) $this->input('phone'));

            // An empty box means "they do not have one". Storing '' instead
            // would collide on the unique index with the next person who also
            // has no phone.
            $this->merge(['phone' => $phone === '' ? null : $phone]);
        }

        if ($this->has('email')) {
            $email = trim((string) $this->input('email'));
            $this->merge(['email' => $email === '' ? null : $email]);
        }
    }
}
