<x-dashboard-layout
    title="{{ $isEditing ? 'Edit Article' : 'New Article' }}"
    subtitle="{{ $isEditing ? 'Update post content, status, and metadata.' : 'Create a new article for rezure.redscale.my.id static blog.' }}"
>
    <x-slot:actions>
        <a
            href="{{ route('dashboard.blogs.index') }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-muted hover:bg-surface-raised hover:text-foreground transition-colors"
        >
            &larr; Back to articles
        </a>

        @if ($isEditing && $blog->status === 'published')
            <a
                href="https://rezure.redscale.my.id/blog/posts/{{ $blog->slug }}"
                target="_blank"
                rel="noreferrer"
                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-muted hover:bg-surface-raised hover:text-foreground transition-colors"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
                View live
            </a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ $isEditing ? route('dashboard.blogs.update', $blog) : route('dashboard.blogs.store') }}"
        class="space-y-6"
        id="blog-form"
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Main Column (2 cols) --}}
            <div class="space-y-4 xl:col-span-2">
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4">
                    {{-- Title --}}
                    <div>
                        <label for="title" class="mb-1 block text-xs font-medium uppercase tracking-wide text-subtle">
                            Article Title <span class="text-negative">*</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title', $blog->title) }}"
                            placeholder="e.g. Getting Started with Rezure on Windows"
                            required
                            class="w-full rounded-lg border border-border bg-surface-raised px-3.5 py-2 text-base font-semibold text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                        @error('title')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- URL Slug --}}
                    <div>
                        <label for="slug" class="mb-1 block text-xs font-medium uppercase tracking-wide text-subtle">
                            URL Slug
                        </label>
                        <div class="flex items-center rounded-lg border border-border bg-surface-raised px-3">
                            <span class="font-mono text-xs text-subtle select-none">/blog/posts/</span>
                            <input
                                type="text"
                                name="slug"
                                id="slug"
                                value="{{ old('slug', $blog->slug) }}"
                                placeholder="getting-started-with-rezure"
                                class="w-full bg-transparent py-2 font-mono text-xs text-foreground placeholder:text-subtle focus:outline-none"
                            >
                        </div>
                        <p class="mt-1 text-[11px] text-subtle">Auto-generated from title if left blank.</p>
                        @error('slug')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Excerpt --}}
                    <div>
                        <label for="excerpt" class="mb-1 block text-xs font-medium uppercase tracking-wide text-subtle">
                            Excerpt / Description <span class="text-negative">*</span>
                        </label>
                        <textarea
                            name="excerpt"
                            id="excerpt"
                            rows="2"
                            placeholder="Short summary for sitemap, previews, and SEO meta tags..."
                            required
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >{{ old('excerpt', $blog->excerpt) }}</textarea>
                        @error('excerpt')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Content --}}
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label for="content" class="block text-xs font-medium uppercase tracking-wide text-subtle">
                                Article Content (HTML / Markdown) <span class="text-negative">*</span>
                            </label>
                            <span class="text-[11px] text-subtle">Rendered at static build time</span>
                        </div>

                        {{-- Quick formatting toolbar --}}
                        <div class="mb-2 flex flex-wrap items-center gap-1 rounded-lg border border-border bg-surface-raised p-1 text-xs text-muted">
                            <button type="button" onclick="insertTag('<h2>', '</h2>')" class="rounded px-2 py-0.5 font-semibold hover:bg-surface hover:text-foreground">H2</button>
                            <button type="button" onclick="insertTag('<h3>', '</h3>')" class="rounded px-2 py-0.5 font-semibold hover:bg-surface hover:text-foreground">H3</button>
                            <button type="button" onclick="insertTag('<strong>', '</strong>')" class="rounded px-2 py-0.5 font-bold hover:bg-surface hover:text-foreground">B</button>
                            <button type="button" onclick="insertTag('<em>', '</em>')" class="rounded px-2 py-0.5 italic hover:bg-surface hover:text-foreground">I</button>
                            <button type="button" onclick="insertTag('<code>', '</code>')" class="rounded px-2 py-0.5 font-mono hover:bg-surface hover:text-foreground">&lt;/&gt;</button>
                            <button type="button" onclick="insertTag('<a href=\'\'>', '</a>')" class="rounded px-2 py-0.5 underline hover:bg-surface hover:text-foreground">Link</button>
                            <button type="button" onclick="insertTag('<ul>\n  <li>', '</li>\n</ul>')" class="rounded px-2 py-0.5 hover:bg-surface hover:text-foreground">List</button>
                            <button type="button" onclick="insertTag('<blockquote><p>', '</p></blockquote>')" class="rounded px-2 py-0.5 hover:bg-surface hover:text-foreground">Quote</button>
                        </div>

                        <textarea
                            name="content"
                            id="content"
                            rows="16"
                            placeholder="Write article HTML/markdown body here..."
                            required
                            class="w-full rounded-lg border border-border bg-surface-raised px-3.5 py-2.5 font-mono text-xs leading-relaxed text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >{{ old('content', $blog->content) }}</textarea>
                        @error('content')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Sidebar Column (1 col) --}}
            <div class="space-y-4">
                {{-- Publishing Settings --}}
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-subtle">Publishing Settings</p>

                    <div>
                        <label for="status" class="mb-1 block text-xs font-medium text-muted">Status</label>
                        <select
                            name="status"
                            id="status"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground focus:border-brand focus:outline-none"
                        >
                            <option value="draft" {{ old('status', $blog->status) === 'draft' ? 'selected' : '' }}>Draft (Hidden from build)</option>
                            <option value="published" {{ old('status', $blog->status) === 'published' ? 'selected' : '' }}>Published (Live on website)</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="published_at" class="mb-1 block text-xs font-medium text-muted">Publish Date</label>
                        <input
                            type="datetime-local"
                            name="published_at"
                            id="published_at"
                            value="{{ old('published_at', $blog->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-xs font-mono text-foreground focus:border-brand focus:outline-none"
                        >
                        @error('published_at')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2 border-t border-border space-y-2">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand/90 transition-colors"
                        >
                            {{ $isEditing ? 'Save changes' : 'Create article' }}
                        </button>
                        <a
                            href="{{ route('dashboard.blogs.index') }}"
                            class="block w-full text-center rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-xs text-muted hover:text-foreground"
                        >
                            Cancel
                        </a>
                    </div>
                </div>

                {{-- Metadata --}}
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-subtle">Metadata & Tags</p>

                    <div>
                        <label for="tags" class="mb-1 block text-xs font-medium text-muted">Tags (comma-separated)</label>
                        <input
                            type="text"
                            name="tags"
                            id="tags"
                            value="{{ old('tags', is_array($blog->tags) ? implode(', ', $blog->tags) : '') }}"
                            placeholder="tutorial, php, getting-started"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                        @error('tags')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="featured_image" class="mb-1 block text-xs font-medium text-muted">Featured Image URL</label>
                        <input
                            type="text"
                            name="featured_image"
                            id="featured_image"
                            value="{{ old('featured_image', $blog->featured_image) }}"
                            placeholder="https://rezure.redscale.my.id/hero.png"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                        @error('featured_image')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Author --}}
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-subtle">Author</p>

                    <div>
                        <label for="author_name" class="mb-1 block text-xs font-medium text-muted">Name</label>
                        <input
                            type="text"
                            name="author_name"
                            id="author_name"
                            value="{{ old('author_name', $blog->author_name ?? 'Muhammad Yahya Zahid') }}"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-xs text-foreground focus:border-brand focus:outline-none"
                        >
                        @error('author_name')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="author_avatar" class="mb-1 block text-xs font-medium text-muted">Avatar URL</label>
                        <input
                            type="text"
                            name="author_avatar"
                            id="author_avatar"
                            value="{{ old('author_avatar', $blog->author_avatar ?? 'https://github.com/myahyazahid.png') }}"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-xs text-foreground focus:border-brand focus:outline-none"
                        >
                        @error('author_avatar')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        function insertTag(startTag, endTag) {
            const textarea = document.getElementById('content');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const selected = text.substring(start, end);
            const replacement = startTag + (selected || 'sample') + endTag;
            textarea.value = text.substring(0, start) + replacement + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + startTag.length, start + startTag.length + (selected.length || 6));
        }

        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        let userEditedSlug = Boolean(slugInput.value);

        slugInput.addEventListener('input', () => {
            userEditedSlug = slugInput.value.trim() !== '';
        });

        titleInput.addEventListener('input', () => {
            if (!userEditedSlug) {
                slugInput.value = titleInput.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/[\s_-]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        });
    </script>
</x-dashboard-layout>