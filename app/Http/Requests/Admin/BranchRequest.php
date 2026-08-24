<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('branches.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = $this->route('branch')?->id;

        return [
            'name' => ['required', 'string', 'max:60'],
            'code' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('branches', 'code')->ignore($branchId),
            ],
            'type' => ['required', Rule::in(['main', 'sub'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'cutoff_time' => ['required', 'date_format:H:i'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name for this branch.',
            'code.required' => 'Enter a short code, like PARK.',
            'code.unique' => 'Another branch already uses that code.',
            'code.alpha_dash' => 'Use letters and numbers only, no spaces.',
            'cutoff_time.required' => 'Set the daily cut-off time.',
            'cutoff_time.date_format' => 'Use a time like 18:00.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        // Stored as a full time; the form only deals in hours and minutes.
        $data['cutoff_time'] = $data['cutoff_time'].':00';

        return $data;
    }
}
