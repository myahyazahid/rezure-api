<?php

namespace App\Http\Requests\Site;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One batch from the website tracker. The contract (field meanings, limits)
 * is api-documentation/website-analytics-api.md — keep the two in sync.
 */
class SiteHitRequest extends FormRequest
{
    /**
     * Anything bigger than this isn't a batch of ≤ 50 hits from the tracker.
     */
    private const MAX_BODY_BYTES = 512 * 1024;

    /**
     * Public, unauthenticated — like the desktop telemetry endpoints, any
     * well-formed payload is accepted.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `navigator.sendBeacon` posts the JSON as text/plain (that's what keeps
     * it a CORS "simple request" with no preflight), which Laravel doesn't
     * parse into input on its own.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isJson()) {
            return;
        }

        $content = $this->getContent();
        $decoded = strlen($content) <= self::MAX_BODY_BYTES ? json_decode($content, true) : null;

        $this->replace(is_array($decoded) ? $decoded : []);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'v' => ['required', 'integer', Rule::in([1])],
            'visitor_id' => ['required', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'sent_at' => ['required', 'date'],

            'context' => ['required', 'array'],
            'context.is_new_visitor' => ['required', 'boolean'],
            'context.visit_number' => ['required', 'integer', 'min:1'],
            'context.first_visit_at' => ['nullable', 'date'],
            'context.screen_width' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'context.screen_height' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'context.viewport_width' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'context.viewport_height' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'context.pixel_ratio' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'context.language' => ['nullable', 'string', 'max:35'],
            'context.languages' => ['nullable', 'array', 'max:10'],
            'context.languages.*' => ['string', 'max:35'],
            'context.timezone' => ['nullable', 'string', 'max:64'],
            'context.color_scheme' => ['nullable', Rule::in(['dark', 'light'])],
            'context.site_theme' => ['nullable', Rule::in(['dark', 'light'])],
            'context.connection' => ['nullable', 'string', 'max:16'],
            'context.cpu_cores' => ['nullable', 'integer', 'min:0', 'max:255'],
            'context.memory_gb' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'context.touch_points' => ['nullable', 'integer', 'min:0', 'max:255'],
            'context.do_not_track' => ['nullable', 'boolean'],

            'hits' => ['required', 'array', 'min:1', 'max:'.config('web-analytics.max_hits_per_batch')],
            'hits.*.type' => ['required', Rule::in(['pageview', 'engagement', 'event', 'click', 'vitals', 'error'])],
            'hits.*.id' => ['required', 'uuid'],
            'hits.*.pageview_id' => ['required', 'uuid'],
            'hits.*.at' => ['required', 'date'],

            'hits.*.url' => ['required_if:hits.*.type,pageview', 'nullable', 'string', 'max:2048', 'url'],
            'hits.*.title' => ['nullable', 'string', 'max:255'],
            'hits.*.referrer' => ['nullable', 'string', 'max:2048'],
            'hits.*.not_found' => ['nullable', 'boolean'],

            'hits.*.engaged_ms' => ['required_if:hits.*.type,engagement', 'nullable', 'integer', 'min:0', 'max:86400000'],
            'hits.*.max_scroll_pct' => ['required_if:hits.*.type,engagement', 'nullable', 'integer', 'min:0', 'max:100'],
            'hits.*.scroll_milestones' => ['nullable', 'array', 'max:5'],
            'hits.*.scroll_milestones.*' => ['integer', Rule::in([25, 50, 75, 90, 100])],

            'hits.*.name' => ['required_if:hits.*.type,event', 'nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'hits.*.label' => ['nullable', 'string', 'max:255'],
            'hits.*.props' => ['nullable', 'array', 'max:20'],

            'hits.*.x_pct' => ['required_if:hits.*.type,click', 'nullable', 'numeric', 'min:0', 'max:100'],
            'hits.*.y_px' => ['required_if:hits.*.type,click', 'nullable', 'integer', 'min:0'],
            'hits.*.page_height' => ['required_if:hits.*.type,click', 'nullable', 'integer', 'min:0'],
            'hits.*.viewport_width' => ['required_if:hits.*.type,click', 'nullable', 'integer', 'min:0', 'max:65535'],
            'hits.*.selector' => ['nullable', 'string', 'max:255'],
            'hits.*.tag' => ['nullable', 'string', 'max:32'],
            'hits.*.text' => ['nullable', 'string', 'max:120'],
            'hits.*.href' => ['nullable', 'string', 'max:2048'],
            'hits.*.is_rage' => ['nullable', 'boolean'],

            'hits.*.lcp_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'hits.*.inp_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'hits.*.fcp_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'hits.*.ttfb_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'hits.*.dom_load_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'hits.*.page_load_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'hits.*.cls' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'hits.*.message' => ['required_if:hits.*.type,error', 'nullable', 'string', 'max:1000'],
            'hits.*.source' => ['nullable', 'string', 'max:2048'],
            'hits.*.line' => ['nullable', 'integer', 'min:0'],
            'hits.*.column' => ['nullable', 'integer', 'min:0'],
            'hits.*.stack' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
