<?php

namespace App\Http\Requests\Dashboard;

use App\Models\WebGoal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebGoalRequest extends FormRequest
{
    /**
     * Authorization is handled by the 'auth' route middleware on the
     * dashboard group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in([WebGoal::TYPE_EVENT, WebGoal::TYPE_PAGEVIEW])],
            'match' => [
                'required',
                'string',
                'max:512',
                // An event goal names an event; a pageview goal is a path.
                Rule::when($this->input('type') === WebGoal::TYPE_EVENT, ['regex:/^[a-z][a-z0-9_]*$/']),
                Rule::when($this->input('type') === WebGoal::TYPE_PAGEVIEW, ['starts_with:/']),
            ],
            'label_match' => ['nullable', 'string', 'max:255', Rule::prohibitedIf($this->input('type') === WebGoal::TYPE_PAGEVIEW)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
