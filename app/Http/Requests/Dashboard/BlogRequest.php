<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $blogId = $this->route('blog')?->id ?? $this->route('blog');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blogs', 'slug')->ignore($blogId)],
            'excerpt' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'tags' => ['nullable'],
            'featured_image' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'author_avatar' => ['nullable', 'string', 'max:500'],
        ];
    }
}
