<x-dashboard-layout
    title="{{ $isEditing ? 'Edit Article' : 'New Article' }}"
    subtitle="{{ $isEditing ? 'Update post content, status, and metadata.' : 'Create a new article for rezure.redscale.my.id static blog.' }}"
>
    <!-- Quill.js WYSIWYG Rich Text Editor Styles -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <style>
        /* Quill Custom Theme Variables matching Rezure Dashboard */
        .ql-toolbar.ql-snow {
            border: 1px solid var(--border-color, #232326) !important;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
            background-color: var(--surface-raised, #17171a);
            padding: 8px 12px !important;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 2px;
        }
        .ql-container.ql-snow {
            border: 1px solid var(--border-color, #232326) !important;
            border-top: none !important;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
            background-color: var(--surface, #111113);
            color: var(--foreground, #f1f1f4);
            font-family: inherit;
            font-size: 15px;
            line-height: 1.75;
        }
        .ql-editor {
            min-height: 400px;
            padding: 18px 20px !important;
            color: var(--foreground, #f1f1f4);
        }
        .ql-editor.ql-blank::before {
            color: var(--subtle, #57575f) !important;
            font-style: normal !important;
            font-size: 14px;
        }
        /* Toolbar Icons & Controls */
        .ql-snow .ql-stroke {
            stroke: var(--muted, #8b8b93) !important;
        }
        .ql-snow .ql-fill {
            fill: var(--muted, #8b8b93) !important;
        }
        .ql-snow .ql-picker {
            color: var(--muted, #8b8b93) !important;
            font-weight: 500;
        }
        .ql-snow .ql-picker-options {
            background-color: var(--surface-raised, #17171a) !important;
            border: 1px solid var(--border-color, #232326) !important;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            padding: 6px !important;
        }
        .ql-snow .ql-picker-item {
            color: var(--foreground, #f1f1f4) !important;
            border-radius: 0.25rem;
        }
        .ql-snow .ql-picker-item:hover,
        .ql-snow .ql-picker-item.ql-selected {
            color: var(--brand, #e0262c) !important;
            background-color: var(--surface, #111113);
        }
        .ql-snow .ql-picker-label:hover {
            color: var(--brand, #e0262c) !important;
        }
        .ql-snow button:hover,
        .ql-snow button:focus,
        .ql-snow button.ql-active {
            background-color: var(--surface, #111113) !important;
            border-radius: 0.375rem;
        }
        .ql-snow button:hover .ql-stroke,
        .ql-snow button.ql-active .ql-stroke {
            stroke: var(--brand, #e0262c) !important;
        }
        .ql-snow button:hover .ql-fill,
        .ql-snow button.ql-active .ql-fill {
            fill: var(--brand, #e0262c) !important;
        }
        /* Typography inside visual editor */
        .ql-editor h1 {
            font-size: 1.85rem !important;
            font-weight: 700 !important;
            color: var(--foreground, #f1f1f4) !important;
            margin-top: 1.5rem !important;
            margin-bottom: 0.75rem !important;
            line-height: 1.3 !important;
        }
        .ql-editor h2 {
            font-size: 1.45rem !important;
            font-weight: 700 !important;
            color: var(--foreground, #f1f1f4) !important;
            margin-top: 1.25rem !important;
            margin-bottom: 0.5rem !important;
            line-height: 1.35 !important;
        }
        .ql-editor h3 {
            font-size: 1.2rem !important;
            font-weight: 600 !important;
            color: var(--foreground, #f1f1f4) !important;
            margin-top: 1rem !important;
            margin-bottom: 0.5rem !important;
            line-height: 1.4 !important;
        }
        .ql-editor p {
            margin-bottom: 1rem !important;
        }
        .ql-editor blockquote {
            border-left: 4px solid var(--brand, #e0262c) !important;
            padding-left: 1rem !important;
            margin: 1.25rem 0 !important;
            color: var(--muted, #8b8b93) !important;
            font-style: italic !important;
            background-color: var(--surface-raised, #17171a);
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            border-radius: 0 0.5rem 0.5rem 0;
        }
        .ql-editor pre.ql-syntax {
            background-color: var(--canvas, #0a0a0b) !important;
            border: 1px solid var(--border-color, #232326) !important;
            border-radius: 0.5rem !important;
            padding: 1rem !important;
            color: #38bdf8 !important;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
            font-size: 0.85rem !important;
        }
        .ql-editor a {
            color: var(--brand, #e0262c) !important;
            text-decoration: underline !important;
        }
        .ql-editor img {
            border-radius: 0.5rem;
            max-width: 100%;
            height: auto;
            margin: 1rem 0;
        }
        .ql-editor ul, .ql-editor ol {
            padding-left: 1.5rem !important;
            margin-bottom: 1rem !important;
        }
    </style>

    <div class="mb-5 flex items-center justify-between">
        <a
            href="{{ route('dashboard.blogs.index') }}"
            class="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-foreground transition-colors"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Articles
        </a>

        @if ($isEditing && $blog->status === 'published')
            <a
                href="https://rezure.redscale.my.id/blog/posts/{{ $blog->slug }}"
                target="_blank"
                rel="noreferrer"
                class="inline-flex items-center gap-1 text-xs text-brand hover:underline"
            >
                View on website â†—
            </a>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-5 flex items-center gap-2.5 rounded-xl border border-positive/30 bg-positive/10 px-4 py-3 text-sm text-positive">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ session('status') }}</span>
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
            {{-- Main Editor Column (2 cols) --}}
            <div class="space-y-5 xl:col-span-2">
                <div class="rounded-xl border border-border bg-surface p-6 space-y-5 shadow-xs">
                    {{-- Title --}}
                    <div>
                        <label for="title" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-subtle">
                            Article Title <span class="text-negative">*</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title', $blog->title) }}"
                            placeholder="e.g. Panduan Lengkap Memulai dengan Rezure"
                            required
                            class="w-full rounded-lg border border-border bg-surface-raised px-4 py-2.5 text-lg font-semibold text-foreground placeholder:text-subtle focus:border-brand focus:outline-none transition-colors"
                        >
                        @error('title')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- URL Slug --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="slug" class="block text-xs font-semibold uppercase tracking-wider text-subtle">
                                URL Slug
                            </label>
                            <span class="text-[11px] text-subtle">Unique path for SEO canonical</span>
                        </div>
                        <div class="flex items-center rounded-lg border border-border bg-surface-raised px-3.5 focus-within:border-brand transition-colors">
                            <span class="text-xs text-subtle select-none font-mono">/blog/posts/</span>
                            <input
                                type="text"
                                name="slug"
                                id="slug"
                                value="{{ old('slug', $blog->slug) }}"
                                placeholder="panduan-lengkap-rezure"
                                class="w-full bg-transparent py-2 text-xs font-mono text-foreground placeholder:text-subtle focus:outline-none"
                            >
                        </div>
                        <p class="mt-1 text-[11px] text-subtle">Auto-generated from title if left blank.</p>
                        @error('slug')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Excerpt --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="excerpt" class="block text-xs font-semibold uppercase tracking-wider text-subtle">
                                Ringkasan / Excerpt <span class="text-negative">*</span>
                            </label>
                            <span class="text-[11px] text-subtle">1-2 kalimat untuk deskripsi di Google & ringkasan blog</span>
                        </div>
                        <textarea
                            name="excerpt"
                            id="excerpt"
                            rows="2"
                            placeholder="Tulis ringkasan singkat artikel di sini..."
                            required
                            class="w-full rounded-lg border border-border bg-surface-raised px-3.5 py-2.5 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none transition-colors leading-relaxed"
                        >{{ old('excerpt', $blog->excerpt) }}</textarea>
                        @error('excerpt')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- WYSIWYG Rich Text Content Body --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-subtle">
                                Isi Artikel / Konten Blog <span class="text-negative">*</span>
                            </label>
                            <span class="text-[11px] text-subtle">Standar visual blogging (seperti Word / Kompas)</span>
                        </div>

                        {{-- Quill Custom Toolbar --}}
                        <div id="quill-toolbar">
                            <span class="ql-formats">
                                <select class="ql-header" title="Format Judul / Paragraf">
                                    <option selected>Teks Normal</option>
                                    <option value="1">Judul Utama (H1)</option>
                                    <option value="2">Sub Judul (H2)</option>
                                    <option value="3">Sub Judul Kecil (H3)</option>
                                </select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-bold" title="Tebal (Ctrl+B)"></button>
                                <button class="ql-italic" title="Miring (Ctrl+I)"></button>
                                <button class="ql-underline" title="Garis Bawah (Ctrl+U)"></button>
                                <button class="ql-strike" title="Coret"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered" title="Daftar Bernomor (1, 2, 3)"></button>
                                <button class="ql-list" value="bullet" title="Daftar Poin (Bullet)"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-align" title="Perataan Teks"></select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-blockquote" title="Kutipan (Quote)"></button>
                                <button class="ql-code-block" title="Blok Kode"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-link" title="Sisipkan Link"></button>
                                <button type="button" id="btn-custom-image" title="Sisipkan Gambar (URL)">
                                    <svg viewBox="0 0 18 18">
                                        <rect class="ql-stroke" height="10" width="12" x="3" y="4"></rect>
                                        <circle class="ql-fill" cx="6" cy="7" r="1"></circle>
                                        <polyline class="ql-even ql-fill" points="5 12 5 11 7 9 8 10 11 7 13 9 13 12 5 12"></polyline>
                                    </svg>
                                </button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-clean" title="Hapus Pemformatan"></button>
                            </span>
                            <span class="ql-formats ml-auto">
                                <button
                                    type="button"
                                    id="toggle-source-btn"
                                    class="!w-auto !px-2.5 !py-1 text-xs font-mono font-medium rounded border border-border text-muted hover:text-foreground hover:bg-surface transition-colors"
                                    title="Alihkan antara Editor Visual dan Kode HTML"
                                >
                                    &lt;/&gt; HTML
                                </button>
                            </span>
                        </div>

                        {{-- Visual WYSIWYG Container --}}
                        <div id="quill-editor"></div>

                        {{-- Raw HTML Fallback / Synchronized Textarea (Hidden in visual mode) --}}
                        <textarea
                            name="content"
                            id="content"
                            rows="18"
                            required
                            class="hidden w-full rounded-b-lg border border-t-0 border-border bg-surface px-4 py-3 font-mono text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none transition-colors leading-relaxed"
                        >{{ old('content', $blog->content) }}</textarea>

                        {{-- Live Stats Bar --}}
                        <div class="mt-2 flex items-center justify-between text-xs text-subtle px-1">
                            <div class="flex items-center gap-3.5">
                                <span id="stat-words">0 kata</span>
                                <span class="text-border">â€¢</span>
                                <span id="stat-chars">0 karakter</span>
                                <span class="text-border">â€¢</span>
                                <span id="stat-read-time">~1 mnt baca</span>
                            </div>
                            <div class="text-[11px] text-muted">
                                Mode: <span id="current-mode-label" class="text-brand font-medium">Visual WYSIWYG (Standar Blog)</span>
                            </div>
                        </div>

                        @error('content')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Sidebar Column (1 col) --}}
            <div class="space-y-5">
                {{-- Publish & Save Card --}}
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-subtle">Publishing Settings</p>

                    <div>
                        <label for="status" class="mb-1 block text-xs font-medium text-muted">Status</label>
                        <select
                            name="status"
                            id="status"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm font-medium text-foreground focus:border-brand focus:outline-none"
                        >
                            <option value="draft" {{ old('status', $blog->status) === 'draft' ? 'selected' : '' }}>
                                ðŸ“ Draft (Hidden from website)
                            </option>
                            <option value="published" {{ old('status', $blog->status) === 'published' ? 'selected' : '' }}>
                                ðŸŸ¢ Published (Live on website)
                            </option>
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

                    <div class="pt-3 border-t border-border space-y-2">
                        <button
                            type="submit"
                            id="btn-save-blog"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-brand py-2.5 px-4 text-center text-sm font-medium text-white shadow-sm hover:bg-brand/90 transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $isEditing ? 'Update Article' : 'Publish / Save Article' }}
                        </button>

                        <a
                            href="{{ route('dashboard.blogs.index') }}"
                            class="w-full inline-flex items-center justify-center rounded-lg border border-border bg-surface-raised py-2 text-center text-xs font-medium text-muted hover:text-foreground transition-colors"
                        >
                            Cancel
                        </a>
                    </div>
                </div>

                {{-- Categorization & SEO Card --}}
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-subtle">Tags & Category</p>

                    <div>
                        <label for="tags" class="mb-1 block text-xs font-medium text-muted">Tags (comma-separated)</label>
                        <input
                            type="text"
                            name="tags"
                            id="tags"
                            value="{{ old('tags', is_array($blog->tags) ? implode(', ', $blog->tags) : '') }}"
                            placeholder="tutorial, php, getting-started"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                        <p class="mt-1 text-[11px] text-subtle">Digunakan untuk kategori pill dan tags artikel di website.</p>
                        @error('tags')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="featured_image" class="mb-1 block text-xs font-medium text-muted">Featured Image URL (Optional)</label>
                        <input
                            type="text"
                            name="featured_image"
                            id="featured_image"
                            value="{{ old('featured_image', $blog->featured_image) }}"
                            placeholder="https://example.com/cover.png"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                        @error('featured_image')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Author Information Card --}}
                <div class="rounded-xl border border-border bg-surface p-5 space-y-4 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-subtle">Author Details</p>

                    <div>
                        <label for="author_name" class="mb-1 block text-xs font-medium text-muted">Author Name</label>
                        <input
                            type="text"
                            name="author_name"
                            id="author_name"
                            value="{{ old('author_name', $blog->author_name ?? 'Muhammad Yahya Zahid') }}"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-xs text-foreground focus:border-brand focus:outline-none"
                        >
                        @error('author_name')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="author_avatar" class="mb-1 block text-xs font-medium text-muted">Author Avatar URL</label>
                        <input
                            type="text"
                            name="author_avatar"
                            id="author_avatar"
                            value="{{ old('author_avatar', $blog->author_avatar ?? 'https://github.com/myahyazahid.png') }}"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-xs text-foreground focus:border-brand focus:outline-none"
                        >
                        @error('author_avatar')
                            <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Quill.js WYSIWYG Rich Text Editor Script -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const textarea = document.getElementById('content');
            const editorDiv = document.getElementById('quill-editor');
            const toggleSourceBtn = document.getElementById('toggle-source-btn');
            const modeLabel = document.getElementById('current-mode-label');
            const form = document.getElementById('blog-form');

            // Initialize Quill
            const quill = new Quill('#quill-editor', {
                theme: 'snow',
                placeholder: 'Tulis isi artikel di sini seperti di Microsoft Word atau Kompas... Anda bisa langsung menebalkan teks, membuat daftar poin, menyisipkan gambar atau link.',
                modules: {
                    toolbar: '#quill-toolbar'
                }
            });

            // Populate initial content if present
            const initialHtml = textarea.value.trim();
            if (initialHtml) {
                quill.clipboard.dangerouslyPasteHTML(initialHtml);
            }

            // Word & Character count calculator
            function updateStats() {
                const text = quill.getText().trim();
                const charCount = text ? text.length : 0;
                const words = text ? text.split(/\s+/).filter(w => w.length > 0) : [];
                const wordCount = words.length;
                const readTime = Math.max(1, Math.ceil(wordCount / 200));

                document.getElementById('stat-words').textContent = wordCount.toLocaleString() + ' kata';
                document.getElementById('stat-chars').textContent = charCount.toLocaleString() + ' karakter';
                document.getElementById('stat-read-time').textContent = '~' + readTime + ' mnt baca';
            }

            // Keep textarea synchronized with Quill
            quill.on('text-change', function () {
                const html = quill.root.innerHTML;
                textarea.value = (html === '<p><br></p>') ? '' : html;
                updateStats();
            });

            updateStats();

            // Custom Image Insertion Button
            const customImageBtn = document.getElementById('btn-custom-image');
            if (customImageBtn) {
                customImageBtn.addEventListener('click', function () {
                    const url = prompt('Masukkan URL Gambar (contoh: https://images.unsplash.com/...):');
                    if (url && url.trim() !== '') {
                        const range = quill.getSelection(true);
                        quill.insertEmbed(range.index, 'image', url.trim());
                        quill.setSelection(range.index + 1);
                    }
                });
            }

            // Toggle Visual WYSIWYG vs Raw HTML Source
            let isHtmlMode = false;
            toggleSourceBtn.addEventListener('click', function () {
                isHtmlMode = !isHtmlMode;

                if (isHtmlMode) {
                    // Switch to HTML mode
                    const currentHtml = (quill.root.innerHTML === '<p><br></p>') ? '' : quill.root.innerHTML;
                    textarea.value = currentHtml;
                    editorDiv.classList.add('hidden');
                    textarea.classList.remove('hidden');
                    textarea.focus();
                    toggleSourceBtn.textContent = 'ðŸ‘ï¸ Visual';
                    toggleSourceBtn.classList.add('bg-brand', 'text-white');
                    toggleSourceBtn.classList.remove('text-muted');
                    modeLabel.textContent = 'Kode Sumber HTML';
                    modeLabel.classList.add('text-subtle');
                    modeLabel.classList.remove('text-brand');
                } else {
                    // Switch back to Visual WYSIWYG mode
                    const htmlFromTextarea = textarea.value.trim();
                    quill.clipboard.dangerouslyPasteHTML(htmlFromTextarea);
                    textarea.classList.add('hidden');
                    editorDiv.classList.remove('hidden');
                    quill.focus();
                    toggleSourceBtn.innerHTML = '&lt;/&gt; HTML';
                    toggleSourceBtn.classList.remove('bg-brand', 'text-white');
                    toggleSourceBtn.classList.add('text-muted');
                    modeLabel.textContent = 'Visual WYSIWYG (Standar Blog)';
                    modeLabel.classList.remove('text-subtle');
                    modeLabel.classList.add('text-brand');
                    updateStats();
                }
            });

            // Ensure form submission always has the latest HTML
            form.addEventListener('submit', function () {
                if (!isHtmlMode) {
                    const html = quill.root.innerHTML;
                    textarea.value = (html === '<p><br></p>') ? '' : html;
                }
            });

            // Auto-slug generator from title if slug not manually changed
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
        });
    </script>
</x-dashboard-layout>
