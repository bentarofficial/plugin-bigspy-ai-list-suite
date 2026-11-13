<?php
/**
 * Listing Generator module (from BIGSPY AI LIST Generator 2.9.9).
 *
 * Function, hook, AJAX action and meta names are kept so the theme and older code keep working.
 */

if (!defined('ABSPATH')) exit;
// Prevent duplicate loading when an older BIGSPY Generator copy is still present.
if (defined('BIGSPY_AI_VERSION')) return;
require_once __DIR__ . '/includes_integration.php';

define('BIGSPY_AI_VERSION', '2.9.9');
require_once __DIR__ . '/popular-analytics.php';
define('BIGSPY_AI_TITLE_LIMIT', 70);
define('BIGSPY_AI_SEO_TITLE_LIMIT', 60);
define('BIGSPY_AI_SEO_DESC_LIMIT', 160);
define('BIGSPY_AI_DESC_MIN_WORDS', 1800);
define('BIGSPY_AI_DESC_MAX_WORDS', 2200);

function bigspy_default_prompt() {
    $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ?: 'this directory';
    return <<<PROMPT
You are the senior SEO content strategist, product researcher, and editorial writer for {$site}, a professional English-language directory for AI tools, software products, online services, platforms, and technology products.

Your job is to create a genuinely useful, detailed, human-readable listing that can rank in organic search and help a visitor decide whether the listed product or service is worth exploring.

LANGUAGE
- Write the entire output in professional, natural English.
- Never write Vietnamese unless the user explicitly requests another language.
- Do not translate English product names, brand names, feature names, URLs, or official terminology.
- Use American English unless the source clearly uses another official spelling.

FACTUAL ACCURACY
- Treat the supplied URLs, short description, keywords, and any supplied source information as the primary evidence.
- Never invent pricing, plans, features, integrations, user counts, awards, security certifications, performance claims, testimonials, company facts, or technical specifications.
- If a fact is not available, write around the missing information instead of fabricating it.
- Clearly distinguish confirmed facts from reasonable editorial interpretation.
- Do not claim that a tool is "the best", "number one", "guaranteed", "100% accurate", or similar unless the supplied evidence explicitly supports it.
- Do not fabricate reviews or customer quotes.

CATEGORY TAXONOMY
- Categories are hierarchical WooCommerce product categories, not a flat list.
- Return category paths using " > " between levels, for example: "Marketing > SEO > Technical SEO".
- Use only as many levels as are naturally useful for the niche: from 1 to 5 levels.
- Never create artificial, redundant, or near-duplicate levels just to reach five levels.
- A broad niche may need deeper taxonomy; a narrow niche may need only 1-2 levels.
- Maximum depth is 5 levels. Never return a category path deeper than 5 levels.
- Reuse the same logical parent/child structure across listings. Do not invent alternate names for an existing concept merely to create a new branch.
- You may return multiple category paths when a listing genuinely belongs to multiple branches. Each path must independently follow the 1-5 level rule.

SEO OBJECTIVE
Create a comprehensive listing intended to satisfy informational and commercial-intent searches.
The primary keyword should appear naturally in the H1/title, opening section, at least one relevant subheading when appropriate, body copy, and SEO metadata.
Use the four secondary keywords naturally where semantically relevant.
Do not keyword-stuff. Do not repeat an exact keyword unnaturally just to increase density.
Use related terminology, entities, synonyms, and semantic variations naturally.
The article must be written for humans first and search engines second.

ARTICLE LENGTH
- The main listing description MUST target approximately 2,000 English words.
- Acceptable range: 1,800 to 2,200 words.
- Do not produce a short 300-500 word summary.
- Do not pad the article with meaningless repetition.
- Every section should provide useful information.
- If the available evidence is limited, expand through useful explanation, practical context, use cases, limitations, selection guidance, workflow examples, and decision-making information without inventing unsupported product facts.

CONTENT STRUCTURE
Create a substantial, well-organized listing using clean HTML suitable for WordPress.
Use:
- One clear H1 represented by the title field only; do not place another H1 inside description_html.
- Multiple H2 sections.
- H3 subsections where useful.
- Short paragraphs.
- Bullet lists when they improve readability.
- Tables only when the available information supports a useful comparison.
- Do not use excessive headings simply to make the article look longer.

A strong structure will normally include relevant sections such as:
1. Introduction / overview
2. What the product or tool is
3. How it works
4. Key features and capabilities
5. Main use cases
6. Who it is best suited for
7. Benefits and practical advantages
8. Limitations or considerations
9. Pricing / plans, only when supported
10. Integrations / compatibility, only when supported
11. How to get started
12. Practical tips for getting better results
13. Alternatives or selection considerations, only when evidence allows
14. Final verdict / summary
Do not force every section if the source information does not support it. Use the structure that best fits the product.

PRODUCT LISTING QUALITY
The visitor should be able to understand:
- What the product is.
- What problem it solves.
- Who should use it.
- What makes it useful.
- How it fits into a real workflow.
- What its important features do in practical terms.
- What limitations or considerations a buyer/user should know.
- What the visitor should do next.

Do not turn the listing into generic AI filler. Each paragraph should add new information or explain a practical implication.

TITLE
- Create an H1/title with a maximum of 70 characters.
- Make it clear and useful.
- Include the primary keyword naturally when possible.
- Avoid clickbait and unnecessary symbols.

SEO TITLE
- Maximum 60 characters.
- Include the primary keyword naturally.
- Make it compelling but factual.
- Do not duplicate the H1 mechanically if a better SEO title is possible.

SEO DESCRIPTION
- Maximum 160 characters.
- Include the primary keyword naturally.
- Clearly communicate what the listing covers.
- Encourage a click without making unsupported claims.

KEYWORDS
- Preserve the supplied primary keyword whenever it is relevant.
- Preserve up to four supplied secondary keywords.
- Do not invent additional "secondary keywords" in the returned secondary_keywords field.
- The article itself may naturally use semantic variations and related terms.

EXTERNAL LINKS
The tool/product/service URL, homepage URL, and affiliate URL are reference information.
Do not invent URLs.
Do not replace an affiliate URL with a different URL.
Do not place raw affiliate links into article prose unless the user supplied them and the field explicitly requires it.

DYNAMIC LISTING ATTRIBUTES
- First identify the most appropriate listing_type from the actual source: examples include AI Tool, SaaS, Software, Product, Travel Website, Hotel, Restaurant, Course, Service, Marketplace, or another specific type. Do not force the listing into a predefined category when the evidence supports another type.
- Create dynamic_attributes as an array of objects with exactly: label, value.
- Labels must be natural, concise, human-readable labels appropriate to the detected listing type. Do NOT always use Pricing, Trial, Platform, API, or other fixed labels.
- Create only attributes supported by the supplied source. Never invent a value.
- Prefer the most useful attributes for the detected listing type. Return no more than the requested maximum dynamic labels.
- If a piece of information is unavailable, omit that attribute entirely rather than returning N/A, Unknown, or an empty label/value.
- Avoid duplicate or near-duplicate labels.

OUTPUT FORMAT
Return ONLY valid JSON.
Do not wrap the JSON in Markdown fences.
Do not add commentary before or after the JSON.
Use exactly these keys:
title
short_description
description_html
seo_title
seo_description
slug
focus_keyword
secondary_keywords
categories
tags
listing_type
dynamic_attributes
company_name
pricing
pricing_type
starting_price
price_min
price_max
price_currency
price_unit
price_custom_text
free_trial
key_features
pros
cons
integrations

The description_html value must contain the full approximately 2,000-word article in clean WordPress-compatible HTML.
Do not include <html>, <head>, <body>, <style>, <script>, or Markdown.
Do not put the H1 in description_html.

QUALITY CHECK BEFORE RETURNING JSON
Before returning the final JSON, internally verify:
- Everything is in English.
- description_html is approximately 1,800-2,200 words.
- The article is informative rather than repetitive.
- There is no unsupported factual claim presented as fact.
- The primary keyword is used naturally.
- Up to four supplied secondary keywords are used naturally where appropriate.
- title <= 70 characters.
- seo_title <= 60 characters.
- seo_description <= 160 characters.
- JSON is valid.
PROMPT;
}

/*
 * IMPORTANT:
 * This list is deliberately a direct model list. There is NO /models resolver,
 * no fallback and no automatic model substitution.
 *
 * Kyma's live catalog should be checked before using a model whose exact ID
 * is not currently available. The UI marks models that were not verified in
 * the current catalog instead of silently sending a different model.
 */
function bigspy_models() {
    return apply_filters('bigspy_models', [
        'gemini-2.5-flash' => [
            'label' => 'Gemini 2.5 Flash',
            'verified' => true,
        ],
        'qwen-3.7-max' => [
            'label' => 'Qwen 3.7 Max',
            'verified' => true,
        ],
        'claude-sonnet-4.6' => [
            'label' => 'Claude Sonnet 4.6',
            'verified' => true,
        ],
        'gpt-5.6-luna' => [
            'label' => 'GPT-5.6 Luna',
            'verified' => true,
        ],
        'deepseek-v4-pro' => [
            'label' => 'DeepSeek V4 Pro',
            'verified' => true,
        ],
        'qwen-3.7-plus' => [
            'label' => 'Qwen 3.7 Plus',
            'verified' => true,
        ],
        'deepseek-v3' => [
            'label' => 'DeepSeek V3',
            'verified' => true,
        ],
        'qwen-3.8-max' => [
            'label' => 'Qwen 3.8 Max',
            'verified' => true,
        ],
        'qwen-3.7-flash' => [
            'label' => 'Qwen 3.7 Flash',
            'verified' => true,
        ],
        'qwen-3.6-plus' => [
            'label' => 'Qwen 3.6 Plus',
            'verified' => true,
        ],
    ]);
}

function bigspy_get_url() {
    return bsl_ai_url();
}

function bigspy_get_key() {
    return bsl_ai_key();
}

function bigspy_get_prompt() {
    $prompt = trim((string)bsl_get('generator.system_prompt', ''));
    return $prompt !== '' ? $prompt : bigspy_default_prompt();
}

function bigspy_settings_url() {
    return admin_url('admin.php?page=' . BSL_PAGE);
}

function bigspy_normalize_api_url($url) {
    $url = trim($url);
    if (!$url) return '';
    $url = preg_replace('~/+$~', '', $url);
    if (preg_match('~/chat/completions$~i', $url)) return $url;
    if (preg_match('~/v1$~i', $url)) return $url . '/chat/completions';
    return $url . '/v1/chat/completions';
}

/**
 * Validate an external HTTP(S) URL before WordPress makes a server-side request.
 * API endpoints require HTTPS; content/image URLs may use HTTP or HTTPS.
 */
function bigspy_validate_external_url($url, $require_https = false) {
    $url = trim((string)$url);
    if (!$url || !wp_http_validate_url($url)) return false;

    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) return false;

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, array('http', 'https'), true)) return false;
    if ($require_https && $scheme !== 'https') return false;

    // Reject embedded credentials even if a custom filter changes HTTP validation behaviour.
    if (!empty($parts['user']) || !empty($parts['pass'])) return false;

    return true;
}

function bigspy_call_ai($messages, $model) {
    $url = bigspy_normalize_api_url(bigspy_get_url());
    $key = bigspy_get_key();

    if (!$url || !$key) {
        return new WP_Error('bigspy_no_config', 'Please configure the Full API URL and API Key in AI List Suite settings.');
    }

    if (!bigspy_validate_external_url($url, true)) {
        return new WP_Error('bigspy_invalid_api_url', 'The API URL must be a valid HTTPS endpoint.');
    }

    $models = bigspy_models();
    if (!isset($models[$model])) {
        return new WP_Error('bigspy_invalid_model', 'Invalid BIGSPY model.');
    }
    if (empty($models[$model]['verified'])) {
        return new WP_Error('bigspy_unverified_model', 'Model "' . $models[$model]['label'] . '" is not currently verified as active on Kyma. The plugin will not switch to another model automatically.');
    }

    // Long listings need a generous output budget; without one the gateway's default limit
    // can cut the JSON off mid-article. Thinking models spend part of this budget reasoning.
    $max_tokens = (int)apply_filters('bigspy_ai_max_tokens', 16384, $model);
    $started = microtime(true);
    $result = bigspy_ai_request($url, $key, $model, $messages, $max_tokens);
    if (is_wp_error($result) && $max_tokens > 8192 && preg_match('/max[_ ]?(completion[_ ]|output[_ ])?tokens|maximum.{0,40}tokens|output.{0,20}limit/i', $result->get_error_message())) {
        $max_tokens = 8192;
        $result = bigspy_ai_request($url, $key, $model, $messages, $max_tokens);
    }
    if (is_wp_error($result)) return $result;

    $content = $result['content'];
    // A reply stopped by the length limit is continued from where it ended, within the time budget.
    for ($attempt = 0; $attempt < 2 && $result['truncated'] && $content !== '' && microtime(true) - $started < 150; $attempt++) {
        $more = bigspy_ai_request($url, $key, $model, array_merge($messages, array(
            array('role' => 'assistant', 'content' => $content),
            array('role' => 'user', 'content' => 'Your previous reply was cut off by the length limit. Continue exactly where it stopped. Output only the remaining text, without repeating anything already written and without commentary or Markdown fences.'),
        )), $max_tokens);
        if (is_wp_error($more) || $more['content'] === '') break;
        $content .= $more['content'];
        $result = $more;
    }

    if ($result['truncated']) {
        return new WP_Error('bigspy_truncated', 'The AI reply was cut off before it finished because it reached the output length limit of the model. Please try again, choose another model, or ask for a shorter article.');
    }
    if ($content === '') {
        return new WP_Error('bigspy_bad_response', 'API returned an unexpected response format. Response: ' . substr((string)$result['raw'], 0, 1000));
    }
    return $content;
}

/** One chat-completions request. Returns content, whether it was cut off, and the raw body. */
function bigspy_ai_request($url, $key, $model, $messages, $max_tokens) {
    $body = array(
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => $max_tokens,
    );

    $response = wp_remote_post($url, [
        'timeout' => 170,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('bigspy_connection', 'API connection error: ' . $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $json = json_decode($raw, true);

    if ($code < 200 || $code >= 300) {
        $message = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? '') : '';
        $message = $message ?: wp_strip_all_tags(substr($raw, 0, 1000));
        return new WP_Error('bigspy_api_error', 'API HTTP error ' . $code . ': ' . $message);
    }

    $finish = strtolower((string)($json['choices'][0]['finish_reason'] ?? ''));
    return array(
        'content' => (string)($json['choices'][0]['message']['content'] ?? ''),
        'truncated' => in_array($finish, array('length', 'max_tokens'), true),
        'raw' => $raw,
    );
}

add_action('wp_ajax_bigspy_test_api', function() {
    check_ajax_referer('bigspy_ai_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied.');

    $model = 'gemini-2.5-flash';
    $result = bigspy_call_ai([
        ['role'=>'system','content'=>bigspy_get_prompt()],
        ['role'=>'user','content'=>'Reply with exactly one sentence: connection successful.']
    ], $model);

    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success(['sample'=>wp_strip_all_tags($result)]);
});

function bigspy_ai_listing_customize_tab_nav() {
    if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) return;
    echo '<button type="button" class="bigspy-admin-tab" id="bigspy-admin-tab-create-listing" role="tab" aria-selected="false" aria-controls="bigspy-admin-panel-create-listing" tabindex="-1" data-bigspy-admin-tab="create-listing"><span class="dashicons dashicons-products" aria-hidden="true"></span>Create Listing</button>';
}
add_action('bigspy_ai_admin_tabs_nav', 'bigspy_ai_listing_customize_tab_nav');

function bigspy_ai_listing_customize_tab_panel() {
    if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) return;
    echo '<div class="bigspy-admin-tab-panel" id="bigspy-admin-panel-create-listing" role="tabpanel" aria-labelledby="bigspy-admin-tab-create-listing" data-bigspy-admin-panel="create-listing" hidden><section class="bigspy-sponsored-panel"><div class="bigspy-direct-settings__head"><div><h2>Create Listing with AI</h2><p>Create a new draft listing, then open its editor to review images and content before publishing.</p></div></div>';
    bigspy_render_listing_box((object)array('ID'=>0));
    echo '</section></div>';
}
add_action('bigspy_ai_admin_tabs_panels', 'bigspy_ai_listing_customize_tab_panel');

function bigspy_render_listing_box($post) {
    $models = bigspy_models();
    $configured = bigspy_get_url() && bigspy_get_key();
    ?>
    <div id="bigspy-list-box" style="padding:4px 0">
        <p style="margin:0 0 14px;padding:10px 12px;background:#f6f7f7;border-left:4px solid #003366"><strong>All listing fields are optional.</strong> Leave any field empty when the information is unavailable. Empty fields will be treated as absent and will not be displayed by the theme.</p>
        <?php if (!$configured): ?>
            <div class="notice notice-warning inline"><p>Please configure the API in <a href="<?php echo esc_url(bigspy_settings_url()); ?>">AI List Suite settings</a>.</p></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px">
            <p style="margin:0">
                <label><strong>1. Tool / Product / Service Website URL</strong></label>
                <input type="url" id="bigspy-tool-url" style="width:100%" placeholder="https://example.com">
            </p>
            <p style="margin:0">
                <label><strong>2. Homepage URL</strong></label>
                <input type="url" id="bigspy-home-url" style="width:100%" placeholder="https://example.com">
            </p>
            <p style="margin:0">
                <label><strong>3. Affiliate URL</strong></label>
                <input type="url" id="bigspy-affiliate-url" style="width:100%" placeholder="Optional">
            </p>
            <p style="margin:0">
                <label><strong>4. Primary Keyword</strong></label>
                <input type="text" id="bigspy-primary-keyword" style="width:100%" placeholder="Optional">
            </p>
            <p style="margin:0">
                <label><strong>5. 4 Secondary Keywords</strong></label>
                <input type="text" id="bigspy-secondary-keywords" style="width:100%" placeholder="keyword 1, keyword 2, keyword 3, keyword 4">
            </p>
            <p style="margin:0">
                <label><strong>6. Short Description</strong></label>
                <textarea id="bigspy-short-input" rows="3" style="width:100%" placeholder="Optional"></textarea>
            </p>
            <div style="grid-column:1/-1;padding:12px;background:#f7f7f7;border:1px solid #dcdcde;border-radius:6px">
                <strong>7. Internal Links (Optional)</strong>
                <p style="margin:6px 0 10px">Add up to 8 internal links for this listing. Every field is optional. Leave a pair empty when you do not need it.</p>
                <?php $saved_internal_links = get_post_meta($post->ID, '_bigspy_internal_links', true); $saved_internal_links = is_array($saved_internal_links) ? $saved_internal_links : array(); ?>
                <?php for ($i=0; $i<8; $i++): $il = isset($saved_internal_links[$i]) && is_array($saved_internal_links[$i]) ? $saved_internal_links[$i] : array(); ?>
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:8px;margin:0 0 8px">
                        <input type="text" id="bigspy-internal-label-<?php echo $i+1; ?>" list="bigspy-internal-label-history" autocomplete="off" style="width:100%" placeholder="Inlink <?php echo $i+1; ?> anchor text (optional)" value="<?php echo esc_attr($il['label'] ?? ''); ?>">
                        <input type="url" id="bigspy-internal-url-<?php echo $i+1; ?>" list="bigspy-internal-url-history" autocomplete="off" style="width:100%" placeholder="https://<?php echo esc_attr(wp_parse_url(home_url('/'), PHP_URL_HOST)); ?>/... (optional)" value="<?php echo esc_attr($il['url'] ?? ''); ?>">
                    </div>
                <?php endfor; ?>
                <datalist id="bigspy-internal-label-history"></datalist>
                <datalist id="bigspy-internal-url-history"></datalist>
                <p class="description" style="margin:4px 0 0">Links typed here are saved automatically and filled into the next new listing. Clear a row to remove that link.</p>
            </div>
            <div style="grid-column:1/-1;padding:12px;background:#fff8e5;border:1px solid #e0c36a;border-radius:6px"><strong>7. Research Sources</strong><p style="margin:6px 0 8px">Choose which external information the AI may use. Website Information is optional; Web Research uses Google web/news results and can still work when the partner website blocks direct crawling.</p><label style="display:block;margin:5px 0"><input type="checkbox" id="bigspy-use-website" checked> Use Website Information</label><label style="display:block;margin:5px 0"><input type="checkbox" id="bigspy-use-web-research" <?php checked((bool)bsl_get('generator.web_research_enabled',0)); ?> <?php disabled(!bigspy_get_serper_key()); ?>> Use Google Web + Recent News Research <?php if(!bigspy_get_serper_key()): ?><span style="color:#b32d2e">(Configure the Web Research API Key in Settings — <a href="https://serper.dev/api-key" target="_blank" rel="noopener noreferrer">get a key at serper.dev</a>)</span><?php endif; ?></label></div>
            <div style="grid-column:1/-1;padding:12px;background:#f0f6fc;border:1px solid #c7d7e7;border-radius:6px">
                <strong>8. Dynamic Listing Information</strong>
                <p style="margin:6px 0 0">The AI will detect whether this is a tool, product, travel site, service, course, hotel, or another type and will automatically create the most relevant <strong>Label → Value</strong> attributes. There are no fixed Pricing / Trial / Platform fields here.</p>
                <label style="display:block;margin-top:8px"><strong>Maximum labels for this listing</strong></label>
                <select id="bigspy-max-dynamic-labels" style="width:180px">
                    <?php $current_max = max(1, min(10, absint(bsl_get('generator.max_dynamic_labels', 6)))); for ($i=1; $i<=10; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($current_max, $i); ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <p style="margin:0">
                <label><strong>8. AI Model</strong></label>
                <select id="bigspy-model" style="width:100%">
                    <?php foreach ($models as $id => $m): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php disabled(empty($m['verified'])); ?>>
                            <?php echo esc_html($m['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>

        <p style="margin-top:16px">
            <button type="button" id="bigspy-list-now" class="button button-primary" style="min-width:180px;height:40px;font-weight:600">LISTING NOW</button>
            <button type="button" id="bigspy-test-selected" class="button" style="height:40px;margin-left:6px">Test Selected Model</button>
        </p>

        <div id="bigspy-loading" style="display:none;margin:12px 0">
            <span class="spinner is-active" style="float:none"></span> Calling AI and generating listing...
        </div>
        <div id="bigspy-status" style="margin-top:10px"></div>
    </div>

    <script>
    (function(){
        const box = document.getElementById('bigspy-list-box');
        if (!box) return;
        const ajax = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        const nonce = '<?php echo esc_js(wp_create_nonce('bigspy_ai_nonce')); ?>';
        const status = document.getElementById('bigspy-status');
        const loading = document.getElementById('bigspy-loading');

        const internalLinkHistoryKey = 'bigspyInternalLinkHistory:v1';
        const internalLinkHost = '<?php echo esc_js(strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST))); ?>';
        const internalLinkRows = Array.from({length:8}, function(_, index) {
            return {
                label: document.getElementById('bigspy-internal-label-' + (index + 1)),
                url: document.getElementById('bigspy-internal-url-' + (index + 1))
            };
        });
        let internalLinkHistory = [];

        function normalizeInternalLinkPair(label, url) {
            label = String(label || '').trim();
            url = String(url || '').trim();
            if (!label || !url) return null;
            try {
                const parsed = new URL(url);
                if (!/^https?:$/.test(parsed.protocol) || parsed.hostname.toLowerCase() !== internalLinkHost) return null;
                return {label: label, url: parsed.href};
            } catch (error) { return null; }
        }

        function renderInternalLinkHistory() {
            const labelList = document.getElementById('bigspy-internal-label-history');
            const urlList = document.getElementById('bigspy-internal-url-history');
            if (!labelList || !urlList) return;
            labelList.replaceChildren();
            urlList.replaceChildren();
            internalLinkHistory.forEach(function(pair) {
                const labelOption = document.createElement('option');
                labelOption.value = pair.label;
                labelOption.label = pair.url;
                labelList.appendChild(labelOption);
                const urlOption = document.createElement('option');
                urlOption.value = pair.url;
                urlOption.label = pair.label;
                urlList.appendChild(urlOption);
            });
        }

        function saveInternalLinkHistory() {
            const current = internalLinkRows.map(function(row) {
                return normalizeInternalLinkPair(row.label.value, row.url.value);
            }).filter(Boolean);
            const seen = new Set();
            internalLinkHistory = current.concat(internalLinkHistory).filter(function(pair) {
                const key = pair.label.toLowerCase() + '\n' + pair.url.toLowerCase();
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            }).slice(0, 50);
            try { window.localStorage.setItem(internalLinkHistoryKey, JSON.stringify(internalLinkHistory)); } catch (error) {}
            renderInternalLinkHistory();
        }

        try {
            const stored = JSON.parse(window.localStorage.getItem(internalLinkHistoryKey) || '[]');
            if (Array.isArray(stored)) internalLinkHistory = stored.map(function(pair) {
                return pair && normalizeInternalLinkPair(pair.label, pair.url);
            }).filter(Boolean).slice(0, 50);
        } catch (error) { internalLinkHistory = []; }

        internalLinkRows.forEach(function(row) {
            row.label.addEventListener('input', function() {
                const match = internalLinkHistory.find(function(pair) { return pair.label === row.label.value; });
                if (match) row.url.value = match.url;
            });
            row.url.addEventListener('input', function() {
                const match = internalLinkHistory.find(function(pair) { return pair.url === row.url.value; });
                if (match) row.label.value = match.label;
            });
        });
        saveInternalLinkHistory();

        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        }
        function setStatus(msg, good) {
            status.innerHTML = '<div style="padding:10px;border-left:4px solid '+(good?'#00a32a':'#d63638')+';background:#f6f7f7">'+esc(msg)+'</div>';
        }
        async function call(action, fields) {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('nonce', nonce);
            Object.keys(fields || {}).forEach(k => fd.append(k, fields[k]));
            const r = await fetch(ajax,{method:'POST',body:fd});
            const text = await r.text();
            let data;
            try { data = JSON.parse(text); }
            catch(e) { throw new Error('Server did not return JSON: '+text.substring(0,1000)); }
            if (!data.success) {
                const failure = new Error(typeof data.data === 'string' ? data.data : 'Request failed.');
                failure.fromServer = true;
                throw failure;
            }
            return data.data;
        }

        document.getElementById('bigspy-test-selected').onclick = async function(){
            const btn=this;
            btn.disabled=true; loading.style.display='block'; status.innerHTML='';
            try {
                const d=await call('bigspy_test_selected',{
                    model:document.getElementById('bigspy-model').value
                });
                setStatus('Model is working: '+d.model+'. '+d.sample,true);
            } catch(e) { setStatus(e.message,false); }
            finally { btn.disabled=false; loading.style.display='none'; }
        };

        document.getElementById('bigspy-list-now').onclick = async function(){
            saveInternalLinkHistory();
            const btn=this;
            btn.disabled=true; loading.style.display='block'; status.innerHTML='';
            try {
                const fields={
                    post_id:<?php echo (int)$post->ID; ?>,
                    tool_url:document.getElementById('bigspy-tool-url').value,
                    home_url:document.getElementById('bigspy-home-url').value,
                    affiliate_url:document.getElementById('bigspy-affiliate-url').value,
                    primary_keyword:document.getElementById('bigspy-primary-keyword').value,
                    secondary_keywords:document.getElementById('bigspy-secondary-keywords').value,
                    short_input:document.getElementById('bigspy-short-input').value,
                    internal_link_1_label:document.getElementById('bigspy-internal-label-1').value,
                    internal_link_1_url:document.getElementById('bigspy-internal-url-1').value,
                    internal_link_2_label:document.getElementById('bigspy-internal-label-2').value,
                    internal_link_2_url:document.getElementById('bigspy-internal-url-2').value,
                    internal_link_3_label:document.getElementById('bigspy-internal-label-3').value,
                    internal_link_3_url:document.getElementById('bigspy-internal-url-3').value,
                    internal_link_4_label:document.getElementById('bigspy-internal-label-4').value,
                    internal_link_4_url:document.getElementById('bigspy-internal-url-4').value,
                    internal_link_5_label:document.getElementById('bigspy-internal-label-5').value,
                    internal_link_5_url:document.getElementById('bigspy-internal-url-5').value,
                    internal_link_6_label:document.getElementById('bigspy-internal-label-6').value,
                    internal_link_6_url:document.getElementById('bigspy-internal-url-6').value,
                    internal_link_7_label:document.getElementById('bigspy-internal-label-7').value,
                    internal_link_7_url:document.getElementById('bigspy-internal-url-7').value,
                    internal_link_8_label:document.getElementById('bigspy-internal-label-8').value,
                    internal_link_8_url:document.getElementById('bigspy-internal-url-8').value,
                    max_dynamic_labels:document.getElementById('bigspy-max-dynamic-labels').value,
                    use_website_information:document.getElementById('bigspy-use-website').checked ? '1' : '',
                    use_web_research:document.getElementById('bigspy-use-web-research').checked ? '1' : '',
                    model:document.getElementById('bigspy-model').value
                };
                const d=await call('bigspy_generate_listing',fields);
                if (!d.background || !d.job_id) throw new Error('The listing job could not be started.');
                setStatus(d.message,true);
                // This browser request does the long work and stays open, so the server keeps the job alive.
                let runResult=null, runFailure=null;
                call('bigspy_generate_listing',Object.assign({},fields,{post_id:d.post_id,bigspy_background:'1',bigspy_job_id:d.job_id}))
                    .then(function(result){ runResult=result; })
                    .catch(function(error){ runFailure=error; });
                const showDone=function(job){
                    setStatus(job.message || 'Listing generated by AI and updated successfully.',true);
                    if (job.edit_url) status.innerHTML += '<p><a class="button button-primary" href="'+esc(job.edit_url)+'">View and edit new listing</a></p>';
                    if (job.seo_message) status.innerHTML += '<p>'+esc(job.seo_message)+'</p>';
                };
                let finished=false;
                for (let attempt=0; attempt<240 && !finished; attempt++) {
                    await new Promise(resolve=>setTimeout(resolve,2000));
                    if (runResult) { finished=true; showDone(runResult); break; }
                    // A message from the plugin itself is final; a dropped connection is not, the job may still finish.
                    if (runFailure && runFailure.fromServer) throw runFailure;
                    let job=null;
                    try { job=await call('bigspy_listing_status',{post_id:d.post_id,job_id:d.job_id}); } catch (pollError) { continue; }
                    if (job.state === 'done') { finished=true; showDone(job); }
                    else if (job.state === 'error') throw new Error(job.message || 'Listing generation failed.');
                    else setStatus(job.message || 'AI is still generating the listing…',true);
                }
                if (!finished) throw new Error('Listing generation is taking longer than expected. Open Products later to check whether the draft was completed.');
            } catch(e) { setStatus(e.message,false); }
            finally { btn.disabled=false; loading.style.display='none'; }
        };
    })();
    </script>
    <?php
}

function bigspy_extract_json_object($text) {
    $length = strlen($text);
    $start = strpos($text, '{');
    if ($start === false) return '';

    $depth = 0;
    $in_string = false;
    $escaped = false;

    for ($i = $start; $i < $length; $i++) {
        $ch = $text[$i];

        if ($in_string) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($ch === "\\") {
                $escaped = true;
                continue;
            }
            if ($ch === '"') {
                $in_string = false;
            }
            continue;
        }

        if ($ch === '"') {
            $in_string = true;
            continue;
        }
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }
    }

    return '';
}

function bigspy_escape_json_control_chars($json) {
    $length = strlen($json);
    $out = '';
    $in_string = false;
    $escaped = false;

    for ($i = 0; $i < $length; $i++) {
        $ch = $json[$i];

        if ($in_string) {
            if ($escaped) {
                $out .= $ch;
                $escaped = false;
                continue;
            }
            if ($ch === "\\") {
                $out .= $ch;
                $escaped = true;
                continue;
            }
            if ($ch === '"') {
                $out .= $ch;
                $in_string = false;
                continue;
            }

            $ord = ord($ch);
            if ($ord < 32) {
                if ($ch === "\n") {
                    $out .= '\\n';
                } elseif ($ch === "\r") {
                    $out .= '\\r';
                } elseif ($ch === "\t") {
                    $out .= '\\t';
                } else {
                    $out .= sprintf('\\u%04x', $ord);
                }
            } else {
                $out .= $ch;
            }
            continue;
        }

        $out .= $ch;
        if ($ch === '"') $in_string = true;
    }

    return $out;
}

function bigspy_parse_json($text) {
    $text = trim((string)$text);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $text = trim($text);

    $flags = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0;

    $json = json_decode($text, true, 512, $flags);
    if (is_array($json)) return $json;
    $direct_error = json_last_error_msg();

    $candidate = bigspy_extract_json_object($text);
    if ($candidate !== '') {
        $json = json_decode($candidate, true, 512, $flags);
        if (is_array($json)) return $json;

        // Some AI responses contain literal newlines/tabs inside description_html.
        // Escape only JSON control characters that occur inside quoted strings;
        // do not rewrite normal HTML or valid JSON escape sequences.
        $normalized = bigspy_escape_json_control_chars($candidate);
        $json = json_decode($normalized, true, 512, $flags);
        if (is_array($json)) return $json;

        $normalized_error = json_last_error_msg();
    } else {
        $normalized_error = 'No complete JSON object found.';
    }

    return new WP_Error(
        'bigspy_json',
        'AI did not return valid JSON. Parser errors: direct=' . $direct_error . '; normalized=' . $normalized_error . '. Response: ' . substr($text, 0, 1200)
    );
}

/**
 * The theme prints the listing title as the page H1, so an H1 inside the article
 * would duplicate it. Drop a leading H1 and demote any other H1 to H2.
 */
function bigspy_strip_duplicate_h1($html) {
    $html = preg_replace('~^\s*<h1\b[^>]*>.*?</h1>\s*~is', '', (string)$html, 1);
    return preg_replace(array('~<h1\b~i', '~</h1\s*>~i'), array('<h2', '</h2>'), $html);
}

/** Existing product category paths ("Parent > Child") so the AI reuses them instead of inventing near-duplicates. */
function bigspy_existing_category_paths($limit = 200) {
    if (!taxonomy_exists('product_cat')) return array();
    // Only categories that hold listings, so retired empty categories are never suggested again.
    $all = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
    if (is_wp_error($all) || !$all) return array();
    $by_id = array();
    foreach ($all as $term) $by_id[$term->term_id] = $term;
    $terms = array_filter($all, function($term) { return (int)$term->count > 0; });
    $paths = array();
    foreach ($terms as $term) {
        if ('uncategorized' === $term->slug) continue;
        $names = array();
        $current = $term;
        for ($depth = 0; $current && $depth < 6; $depth++) {
            array_unshift($names, html_entity_decode($current->name, ENT_QUOTES, 'UTF-8'));
            $current = $current->parent && isset($by_id[$current->parent]) ? $by_id[$current->parent] : null;
        }
        $paths[] = implode(' > ', $names);
    }
    sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
    return array_slice($paths, 0, $limit);
}

/** Links to the listed website are paid or affiliate links, so they are always marked as sponsored. */
function bigspy_mark_listing_links_sponsored($html, array $urls) {
    $hosts = array();
    foreach ($urls as $url) {
        $host = strtolower((string)wp_parse_url((string)$url, PHP_URL_HOST));
        if ($host !== '') $hosts[] = preg_replace('/^www\./', '', $host);
    }
    $hosts = array_values(array_unique($hosts));
    if (!$hosts) return (string)$html;
    return preg_replace_callback('/<a\b[^>]*>/i', function($match) use ($hosts) {
        $tag = $match[0];
        if (!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag, $href)) return $tag;
        $host = preg_replace('/^www\./', '', strtolower((string)wp_parse_url(html_entity_decode($href[2]), PHP_URL_HOST)));
        if ($host === '') return $tag;
        foreach ($hosts as $listed) {
            if ($host === $listed || substr($host, -strlen('.' . $listed)) === '.' . $listed) {
                $tag = preg_replace('/\s+(rel|target)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $tag);
                return preg_replace('/\s*\/?>$/', ' target="_blank" rel="sponsored nofollow noopener">', $tag);
            }
        }
        return $tag;
    }, (string)$html);
}

/** Deepest of the given product categories; used as the Rank Math primary category. */
function bigspy_deepest_term_id(array $term_ids) {
    $best = 0;
    $best_depth = -1;
    foreach ($term_ids as $term_id) {
        $depth = count(get_ancestors((int)$term_id, 'product_cat', 'taxonomy'));
        if ($depth > $best_depth) { $best = (int)$term_id; $best_depth = $depth; }
    }
    return $best;
}

function bigspy_limit($text, $limit) {
    $text = trim((string)$text);
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text,0,$limit), " \t\n\r-–—,.;:") : $text;
}

function bigspy_keywords($primary, $secondary) {
    $items = [];
    if ($primary !== '') $items[] = $primary;
    foreach (preg_split('/\s*,\s*/', $secondary) as $kw) {
        $kw = trim($kw);
        if ($kw !== '' && !in_array($kw,$items,true)) $items[]=$kw;
    }
    return $items;
}

function bigspy_get_serper_key() { return trim((string)bsl_get('serper_key', '')); }
function bigspy_web_research_enabled() { return (bool)bsl_get('generator.web_research_enabled', 0) && bigspy_get_serper_key() !== ''; }
function bigspy_serper_search($query,$type='search',$num=5) { $key=bigspy_get_serper_key();$query=trim(wp_strip_all_tags((string)$query));if(!$key||!$query)return array();$type=in_array($type,array('search','news'),true)?$type:'search';$num=max(3,min(10,absint($num)));$r=wp_remote_post('https://google.serper.dev/'.$type,array('timeout'=>15,'headers'=>array('X-API-KEY'=>$key,'Content-Type'=>'application/json','Accept'=>'application/json'),'body'=>wp_json_encode(array('q'=>$query,'num'=>$num,'hl'=>'en'))));if(is_wp_error($r))return array();$code=wp_remote_retrieve_response_code($r);$j=json_decode(wp_remote_retrieve_body($r),true);if($code<200||$code>=300||!is_array($j))return array();$rows=isset($j['organic'])&&is_array($j['organic'])?$j['organic']:(isset($j['news'])&&is_array($j['news'])?$j['news']:array());$items=array();foreach($rows as $row){if(!is_array($row))continue;$title=sanitize_text_field($row['title']??'');$snippet=sanitize_textarea_field($row['snippet']??$row['description']??'');if(!$title&&!$snippet)continue;$items[]=array('title'=>$title,'url'=>esc_url_raw($row['link']??$row['url']??''),'snippet'=>$snippet,'date'=>sanitize_text_field($row['date']??$row['publishedAt']??''),'source'=>sanitize_text_field($row['source']??''),'type'=>$type);}return $items;}
function bigspy_build_web_research($tool,$home,$primary,$secondary,$short) {if(!bigspy_web_research_enabled())return array();$num=max(3,min(10,absint(bsl_get('generator.web_research_results',5))));$host=$tool?(wp_parse_url($tool,PHP_URL_HOST)?:''):(wp_parse_url($home,PHP_URL_HOST)?:'');$queries=array();if($primary)$queries[]=$primary;if($host)$queries[]=$host.' '.($primary?:$short);if(!$queries)$queries[]=trim($short.' '.$secondary);$results=array();foreach(array_slice(array_unique(array_filter($queries)),0,2) as $q)$results=array_merge($results,bigspy_serper_search($q,'search',$num));$news=trim(($primary?:$host?:$short).' latest news');if($news)$results=array_merge($results,bigspy_serper_search($news,'news',min(5,$num)));$dedup=array();foreach($results as $item){$k=$item['url']?:md5($item['title'].'|'.$item['snippet']);if(!isset($dedup[$k]))$dedup[$k]=$item;}return array_values($dedup);}
function bigspy_format_web_research($items,$max_chars=12000){if(!$items)return '[No web research results were retrieved.]';$out=array();$used=0;foreach($items as $i=>$item){$line='SOURCE '.($i+1)."\nTitle: ".($item['title']??'')."\nURL: ".($item['url']??'')."\nDate: ".($item['date']??'')."\nSource: ".($item['source']??'')."\nSnippet: ".($item['snippet']??'');if($used+strlen($line)>$max_chars)break;$out[]=$line;$used+=strlen($line);}return implode("\n\n",$out);}

function bigspy_fetch_page_text($url) {
    if (!bigspy_validate_external_url($url)) return '';
    $response = wp_safe_remote_get($url, [
        'timeout' => 20,
        'redirection' => 4,
        'limit_response_size' => 2 * 1024 * 1024,
        'headers' => [
            'User-Agent' => 'BIGSPY AI List Suite/'.BSL_VERSION
        ]
    ]);
    if (is_wp_error($response)) return '';
    $code = (int)wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) return '';
    $html = wp_remote_retrieve_body($response);
    if (!$html) return '';

    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
    $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', ' ', $html);
    $text = wp_strip_all_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    // Keep the prompt payload reasonable while retaining substantial source material.
    if (mb_strlen($text) > 16000) $text = mb_substr($text, 0, 16000);
    return $text;
}

add_action('wp_ajax_bigspy_test_selected', function(){
    check_ajax_referer('bigspy_ai_nonce','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied.');

    $model=sanitize_text_field(wp_unslash($_POST['model']??''));

    $result=bigspy_call_ai([
        ['role'=>'system','content'=>bigspy_get_prompt()],
        ['role'=>'user','content'=>'Reply with exactly one sentence: BIGSPY MODEL TEST OK.']
    ],$model);

    if(is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success(['model'=>$model,'sample'=>wp_strip_all_tags($result)]);
});

function bigspy_update_listing_field($post_id, $field, $value) {
    // ACF is the preferred storage layer when the BIGSPY field group is active.
    if (function_exists('update_field')) {
        $result = update_field($field, $value, $post_id);
        if ($result !== false) return;
    }
    update_post_meta($post_id, $field, $value);
}

function bigspy_lines($value) {
    $items = array_filter(array_map(function($v){ return sanitize_text_field($v); }, (array)$value));
    return implode("\n", $items);
}

function bigspy_normalize_dynamic_attributes($value, $max = 6) {
    $max = max(1, min(20, absint($max)));
    $items = is_array($value) ? $value : array();
    $out = array();
    $seen = array();
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $label = sanitize_text_field($item['label'] ?? '');
        $val = sanitize_textarea_field($item['value'] ?? '');
        if ($label === '' || $val === '') continue;
        $key = strtolower(preg_replace('/\s+/', ' ', trim($label)));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = array('label' => $label, 'value' => $val);
        if (count($out) >= $max) break;
    }
    return $out;
}

function bigspy_dynamic_find_value($attributes, $names) {
    $names = array_map(function($v){ return strtolower(trim($v)); }, (array)$names);
    foreach ((array)$attributes as $item) {
        $label = strtolower(trim((string)($item['label'] ?? '')));
        if (in_array($label, $names, true)) return sanitize_text_field($item['value'] ?? '');
    }
    return '';
}

function bigspy_bool_value($value) {
    if (is_bool($value)) return $value ? 1 : 0;
    $v = strtolower(trim((string)$value));
    return in_array($v, array('1','true','yes','y','available','included'), true) ? 1 : 0;
}

function bigspy_set_external_product_url($post_id, $url) {
    if (!$url || !function_exists('wc_get_product')) return;
    $product = wc_get_product($post_id);
    if (!$product) return;

    // BIGSPY listings are directory entries, not purchasable WooCommerce products.
    wp_set_object_terms($post_id, 'external', 'product_type', false);
    update_post_meta($post_id, '_product_url', esc_url_raw($url));
    update_post_meta($post_id, '_button_text', 'Open site');
    update_post_meta($post_id, '_virtual', 'yes');
    update_post_meta($post_id, '_downloadable', 'no');
    update_post_meta($post_id, '_sold_individually', 'no');
}

function bigspy_normalize_internal_links_from_request() {
    $links = array();
    $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    for ($i = 1; $i <= 8; $i++) {
        $label = sanitize_text_field(wp_unslash($_POST['internal_link_'.$i.'_label'] ?? ''));
        $url = esc_url_raw(wp_unslash($_POST['internal_link_'.$i.'_url'] ?? ''));
        if ($label === '' && $url === '') continue;
        if ($label === '' || $url === '') continue;
        if (!bigspy_validate_external_url($url, false)) continue;
        $url_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($site_host && $url_host && $url_host !== $site_host) continue;
        $links[] = array('label' => $label, 'url' => $url);
    }
    return array_slice($links, 0, 8);
}

/**
 * Background listing status helpers.
 * The generator remains functionally identical, but long AI/source/image work is
 * moved into a non-blocking secondary admin-ajax request so Nginx cannot return
 * a 504 before WordPress sends the initial JSON response.
 */
function bigspy_listing_status_key($job_id) {
    return 'bigspy_listing_job_' . sanitize_key($job_id);
}

function bigspy_save_listing_status($job_id, $status, $ttl = 3600) {
    $job_id = sanitize_text_field((string)$job_id);
    if (!$job_id || !is_array($status)) return false;
    $ok = set_transient(bigspy_listing_status_key($job_id), $status, $ttl);
    if (!empty($status['post_id'])) update_post_meta((int)$status['post_id'], '_bigspy_listing_job_' . md5($job_id), $status);
    return $ok;
}
function bigspy_get_listing_status($job_id, $post_id = 0) {
    $status = get_transient(bigspy_listing_status_key($job_id));
    if (is_array($status)) return $status;
    if ($post_id) {
        $saved = get_post_meta((int)$post_id, '_bigspy_listing_job_' . md5(sanitize_text_field((string)$job_id)), true);
        if (is_array($saved)) return $saved;
    }
    return false;
}

add_action('wp_ajax_bigspy_listing_status', function(){
    check_ajax_referer('bigspy_ai_nonce','nonce');
    $post_id=(int)($_POST['post_id']??0);
    $job_id=sanitize_text_field(wp_unslash($_POST['job_id']??''));
    if(!$post_id || !$job_id || !current_user_can('edit_post',$post_id)) wp_send_json_error('Invalid listing job.');
    $status=bigspy_get_listing_status($job_id,$post_id);
    if(!is_array($status)) wp_send_json_error('Listing job not found or expired.');
    // Watchdog: no stage takes longer than the AI request timeout, so a job that has not
    // reported progress for six minutes was stopped by the server.
    if(($status['state']??'')==='processing' && !empty($status['updated_at']) && time()-(int)$status['updated_at'] > 6 * MINUTE_IN_SECONDS) {
        $status['state']='error';
        $status['message']='The server stopped the listing job at this step: "'.($status['message']??'unknown').'" Please try again. If it happens again, choose a faster AI model or untick a research source.';
        bigspy_save_listing_status($job_id,$status,15 * MINUTE_IN_SECONDS);
    }
    wp_send_json_success($status);
});

add_action('wp_ajax_bigspy_generate_listing', function(){
    check_ajax_referer('bigspy_ai_nonce','nonce');
    $post_id=(int)($_POST['post_id']??0);
    if(!$post_id) {
        if(!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) wp_send_json_error('You do not have permission to create products.');
        $post_id=wp_insert_post(array('post_type'=>'product','post_status'=>'draft','post_title'=>'AI Listing Draft','post_author'=>get_current_user_id()),true);
        if(is_wp_error($post_id)) wp_send_json_error('Could not create the listing draft: '.$post_id->get_error_message());
    }
    if(!current_user_can('edit_post',$post_id)) wp_send_json_error('You do not have permission to edit this product.');

    // Two-step flow driven by the browser. Step 1 (this branch) only creates the job and
    // returns at once. Step 2 is a second request from the same browser that does the long
    // work while the browser polls the status. The browser keeps that connection open, so
    // servers such as LiteSpeed do not abort the PHP process the way they can abort a
    // loopback request whose caller has already disconnected.
    $is_background = !empty($_POST['bigspy_background']);
    $job_id = sanitize_text_field(wp_unslash($_POST['bigspy_job_id']??''));
    if(!$is_background) {
        $job_id = wp_generate_uuid4();
        bigspy_save_listing_status($job_id, array(
            'state'=>'processing',
            'message'=>'Listing job created. Starting…',
            'post_id'=>$post_id,
            'job_id'=>$job_id,
            'started_at'=>current_time('mysql'),
            'updated_at'=>time(),
        ), 15 * MINUTE_IN_SECONDS);
        wp_send_json_success(array(
            'message'=>'Listing generation started. Keep this page open while the AI writes the listing.',
            'job_id'=>$job_id,
            'post_id'=>$post_id,
            'background'=>true,
        ));
    }

    // Long-running step. It keeps going even if the browser connection drops, and every
    // stage is written to the job status so a stopped job can be diagnosed.
    if(!$job_id) wp_send_json_error('Invalid background listing job.');
    if(function_exists('ignore_user_abort')) @ignore_user_abort(true);
    if(function_exists('set_time_limit')) @set_time_limit(0);
    $stage = function($message) use ($job_id, $post_id) {
        bigspy_save_listing_status($job_id, array('state'=>'processing','message'=>$message,'post_id'=>$post_id,'job_id'=>$job_id,'updated_at'=>time()), 15 * MINUTE_IN_SECONDS);
    };
    register_shutdown_function(function() use ($job_id, $post_id) {
        $error = error_get_last();
        if(!$error) return;
        $fatal_types = array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR);
        if(in_array($error['type'], $fatal_types, true)) {
            bigspy_save_listing_status($job_id, array(
                'state'=>'error',
                'message'=>'Listing generation stopped by a server/PHP fatal error: '.$error['message'],
                'post_id'=>$post_id,
                'job_id'=>$job_id,
            ), 15 * MINUTE_IN_SECONDS);
        }
    });

    $tool=esc_url_raw(wp_unslash($_POST['tool_url']??''));
    $home=esc_url_raw(wp_unslash($_POST['home_url']??''));
    $affiliate=esc_url_raw(wp_unslash($_POST['affiliate_url']??''));
    $primary=sanitize_text_field(wp_unslash($_POST['primary_keyword']??''));
    $secondary=sanitize_text_field(wp_unslash($_POST['secondary_keywords']??''));
    $short=sanitize_textarea_field(wp_unslash($_POST['short_input']??''));
    $internal_links=bigspy_normalize_internal_links_from_request();
    $max_dynamic_labels=max(1,min(10,absint($_POST['max_dynamic_labels']??bsl_get('generator.max_dynamic_labels',6))));
    $model=sanitize_text_field(wp_unslash($_POST['model']??''));
    $use_website_information=!empty($_POST['use_website_information']);
    $use_web_research=!empty($_POST['use_web_research']) && bigspy_get_serper_key() !== '';

    if($use_website_information) $stage('Reading the website…');
    $source_text=$use_website_information ? bigspy_fetch_page_text($tool ?: $home) : '';
    if($use_web_research) $stage('Searching Google web and news results…');
    $web_research=$use_web_research ? bigspy_build_web_research($tool,$home,$primary,$secondary,$short) : array();
    $existing_title = sanitize_text_field(get_the_title($post_id));
    $existing_excerpt = sanitize_textarea_field(get_post_field('post_excerpt',$post_id));
    $existing_content = wp_trim_words(wp_strip_all_tags(get_post_field('post_content',$post_id)), 120, '…');

    $input=[
        'existing_product_title'=>$existing_title,
        'existing_short_description'=>$existing_excerpt,
        'existing_content_context'=>$existing_content,
        'tool_url'=>$tool,
        'homepage_url'=>$home,
        'affiliate_url'=>$affiliate,
        'primary_keyword'=>$primary,
        'secondary_keywords'=>array_values(array_filter(array_map('trim',preg_split('/\s*,\s*/',$secondary)))),
        'short_description'=>$short,
        'max_dynamic_labels'=>$max_dynamic_labels,
    ];

    // All BIGSPY listing fields are optional. Empty values are treated as absent.
    // Existing WordPress product data is supplied as fallback context when inputs are blank.

    $existing_categories = bigspy_existing_category_paths();
    $user_prompt = "Create a complete directory listing from the JSON data below. Every BIGSPY input field is optional. Empty fields mean the information is unavailable and must not be invented. Use existing_product_title, existing_short_description, and existing_content_context as fallback context when present. Everything must be written in English.\n\n"
        . wp_json_encode($input,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
        . "\n\nSOURCE WEBSITE TEXT (reference only; do not copy verbatim and do not invent facts beyond it):\n"
        . ($source_text ?: '[Website Information was disabled or no source page text could be retrieved.]')
        . "\n\nWEB RESEARCH RESULTS (Google web/news snippets; use as current reference, verify consistency across sources, do not copy verbatim):\n"
        . bigspy_format_web_research($web_research)
        . "\n\nRequirements:\n"
        . "1. title: this becomes the WordPress product title and the theme H1. Maximum 70 characters. Make it the actual tool/product name or a clear SEO-friendly title, not a generic phrase.\n"
        . "2. short_description: this becomes the WooCommerce Product short description and the BIGSPY card tagline/excerpt. Keep it concise, useful, and in English.\n"
        . "3. pricing: If pricing information is supplied or supported by the source, populate flexible pricing fields. If pricing is unavailable or variable, leave numeric price fields empty and use an appropriate pricing_type such as variable, contact, quote, or price_on_website. Never invent a price.\n3. description_html: this becomes the WooCommerce Product description and the main BIGSPY listing article. Write approximately ".BIGSPY_AI_DESC_MIN_WORDS."-".BIGSPY_AI_DESC_MAX_WORDS." words. This is the MAIN listing article, not a short summary. Use clean HTML with H2/H3/P/UL/LI when useful. Do NOT include any H1 in description_html, even if other instructions ask for one: the theme already renders the title as the page H1, so the article must start directly with its first H2.\n"
        . "4. seo_title: this is written directly to Rank Math as the SEO title. Maximum ".BIGSPY_AI_SEO_TITLE_LIMIT." characters.\n"
        . "5. seo_description: this is written directly to Rank Math as the meta description. Maximum ".BIGSPY_AI_SEO_DESC_LIMIT." characters.\n"
        . "6. focus_keyword: use EXACTLY the supplied primary_keyword when it is present. If it is empty, choose one suitable primary keyword.\n"
        . "7. secondary_keywords: when four secondary keywords are supplied by the user, return those exact four in the same order. Never replace them with invented keywords.\n"
        . "8. slug: lowercase URL slug containing the primary keyword when possible.\n"
        . "9. categories: return up to 5 relevant WooCommerce product category PATHS, not a flat list. Each path must use the exact delimiter \" > \" between hierarchy levels, for example \"Marketing > SEO > Technical SEO\". Use only 1 to 5 levels per path based on the natural breadth and specificity of the niche. Do not force every listing to five levels, do not create artificial levels, and never return more than 5 levels in a single path. If a listing belongs to multiple genuine branches, return multiple paths. Reuse logical category names consistently. EXISTING CATEGORIES on the site: " . ($existing_categories ? implode('; ', $existing_categories) : '[none yet]') . ". Reuse an existing path exactly whenever one fits the listing. Create a new path only when none fits, and never create a near-duplicate of an existing name (for example \"Travel & Tourism\" or \"Travel Services\" when \"Travel\" exists).\n"
        . "10. tags: maximum 10 relevant WooCommerce product tags.\n"
        . "11. listing_type: identify the actual type of listing from the source. dynamic_attributes: create no more than the requested maximum number of the most useful label/value pairs for that type. Labels must be generated for the listing, not chosen from a fixed Pricing/Trial/Platform list. Only include facts supported by the source.
12. company_name, pricing, pricing_type, starting_price, price_min, price_max, price_currency, price_unit, price_custom_text, free_trial, api_available, api_url, founded_year, key_features, pros, cons, integrations: populate these legacy/theme fields only when supported by the source. They remain for backward compatibility; they must not constrain dynamic_attributes. Never invent unsupported prices.\n"
        . "13. Theme synchronization: the returned fields will be mapped into BIGSPY Theme listing fields including tagline, flexible pricing fields, free trial, API availability/API URL, company, founded year, features, pros, cons, integrations, official URL, homepage URL and affiliate URL.\n"
        . "14. SEO synchronization: the plugin will write one primary focus keyword plus up to four secondary focus keywords to Rank Math as a comma-separated focus keyword list. Do not omit supplied keywords.\n\n"
        . "Write everything in English. Only return valid JSON, no Markdown, using exactly this schema:\n"
        . '{"title":"","short_description":"","description_html":"","seo_title":"","seo_description":"","slug":"","focus_keyword":"","secondary_keywords":[],"categories":[],"tags":[],"listing_type":"","dynamic_attributes":[],"company_name":"","pricing":"","pricing_type":"","starting_price":"","price_min":"","price_max":"","price_currency":"","price_unit":"","price_custom_text":"","free_trial":"","api_available":"","api_url":"","founded_year":"","key_features":[],"pros":[],"cons":[],"integrations":[]}';

    $stage('The AI is writing the listing. This usually takes 1 to 3 minutes…');
    $result=bigspy_call_ai([
        ['role'=>'system','content'=>bigspy_get_prompt()],
        ['role'=>'user','content'=>$user_prompt]
    ],$model);
    $stage('Saving the listing, categories and SEO data…');

    if(is_wp_error($result)) {
        bigspy_save_listing_status($job_id, array('state'=>'error','message'=>$result->get_error_message(),'post_id'=>$post_id,'job_id'=>$job_id), 15 * MINUTE_IN_SECONDS);
        wp_send_json_error($result->get_error_message());
    }

    $data=bigspy_parse_json($result);
    if(is_wp_error($data)) {
        bigspy_save_listing_status($job_id, array('state'=>'error','message'=>$data->get_error_message(),'post_id'=>$post_id,'job_id'=>$job_id), 15 * MINUTE_IN_SECONDS);
        wp_send_json_error($data->get_error_message());
    }

    $dynamic_attributes=bigspy_normalize_dynamic_attributes($data['dynamic_attributes']??array(),$max_dynamic_labels);
    $listing_type=sanitize_text_field($data['listing_type']??'');
    // Keep legacy pricing fields synchronized when the AI exposes a clearly equivalent dynamic attribute.
    $legacy_pricing = sanitize_text_field($data['pricing']??'');
    if ($legacy_pricing === '') $legacy_pricing = bigspy_dynamic_find_value($dynamic_attributes,array('Pricing','Price','Cost','Starting Price'));
    $legacy_pricing_type = sanitize_key($data['pricing_type']??'');
    $legacy_starting_price = sanitize_text_field($data['starting_price']??'');
    if ($legacy_starting_price === '') $legacy_starting_price = bigspy_dynamic_find_value($dynamic_attributes,array('Starting Price','From','Price'));

    // AI output can be steered by fetched source pages, so plain-text fields never keep markup.
    $title=bigspy_limit(sanitize_text_field((string)($data['title']??'')),$GLOBALS['bigspy_title_limit']??BIGSPY_AI_TITLE_LIMIT);
    if($title==='') $title=$existing_title ?: sanitize_text_field($short ?: ($home ?: ($tool ?: 'BIGSPY Listing')));
    $short_desc=sanitize_textarea_field((string)($data['short_description']??$short));
    $description=bigspy_mark_listing_links_sponsored(bigspy_strip_duplicate_h1(wp_kses_post((string)($data['description_html']??''))), array($tool,$home,$affiliate));
    $seo_title=bigspy_limit(sanitize_text_field((string)($data['seo_title']??'')),$GLOBALS['bigspy_seo_title_limit']??BIGSPY_AI_SEO_TITLE_LIMIT);
    $seo_desc=bigspy_limit(sanitize_text_field((string)($data['seo_description']??'')),$GLOBALS['bigspy_seo_desc_limit']??BIGSPY_AI_SEO_DESC_LIMIT);
    $ai_focus=sanitize_text_field($data['focus_keyword']??'');
    $focus=$primary !== '' ? $primary : $ai_focus;
    $user_secondary=array_values(array_filter(array_map('sanitize_text_field',preg_split('/\s*,\s*/',$secondary))));
    $user_secondary=array_slice($user_secondary,0,4);
    $ai_secondary=array_values(array_filter(array_map('sanitize_text_field',(array)($data['secondary_keywords']??[]))));
    $secondary_out=$user_secondary ? $user_secondary : array_slice($ai_secondary,0,4);

    $keywords=bigspy_keywords($focus,implode(',',$secondary_out));
    $slug=sanitize_title($data['slug']??$title);

    $post_update=[
        'ID'=>$post_id,
        'post_title'=>$title,
        'post_content'=>$description,
        'post_excerpt'=>$short_desc,
        'post_name'=>$slug,
    ];
    $updated=wp_update_post($post_update,true);
    if(is_wp_error($updated)) {
        $update_error='Unable to update product: '.$updated->get_error_message();
        bigspy_save_listing_status($job_id, array('state'=>'error','message'=>$update_error,'post_id'=>$post_id,'job_id'=>$job_id), 15 * MINUTE_IN_SECONDS);
        wp_send_json_error($update_error);
    }

    // Keep both BIGSPY internal fields and the exact field names used by Theme v1.1.
    if($tool) {
        update_post_meta($post_id,'_bigspy_tool_url',$tool);
        bigspy_update_listing_field($post_id,'official_url',$tool);
        bigspy_update_listing_field($post_id,'source_url',$tool);
    }
    if($home) {
        update_post_meta($post_id,'_bigspy_home_url',$home);
        bigspy_update_listing_field($post_id,'homepage_url',$home);
    }
    if($affiliate) {
        update_post_meta($post_id,'_bigspy_affiliate_url',$affiliate);
        bigspy_update_listing_field($post_id,'affiliate_url',$affiliate);
    }

    update_post_meta($post_id,'_bigspy_primary_keyword',$focus);
    update_post_meta($post_id,'_bigspy_secondary_keywords',$secondary_out);
    update_post_meta($post_id,'_bigspy_listing_type',$listing_type);
    update_post_meta($post_id,'_bigspy_dynamic_attributes',$dynamic_attributes);
    update_post_meta($post_id,'_bigspy_internal_links',$internal_links);
    bigspy_update_listing_field($post_id,'listing_type',$listing_type);
    bigspy_update_listing_field($post_id,'dynamic_attributes',$dynamic_attributes);
    bigspy_update_listing_field($post_id,'internal_links',$internal_links);

    $company = sanitize_text_field($data['company_name']??'');
    $pricing = $legacy_pricing;
    $pricing_type = $legacy_pricing_type;
    $starting_price = $legacy_starting_price;
    $price_min = sanitize_text_field($data['price_min']??'');
    $price_max = sanitize_text_field($data['price_max']??'');
    $price_currency = sanitize_text_field($data['price_currency']??'');
    $price_unit = sanitize_text_field($data['price_unit']??'');
    $price_custom_text = sanitize_text_field($data['price_custom_text']??'');
    $free_trial = bigspy_bool_value($data['free_trial']??'');
    $api_available = bigspy_bool_value($data['api_available']??'');
    $api_url = esc_url_raw($data['api_url']??'');
    $founded_year = absint($data['founded_year']??0);
    $features = array_values(array_filter(array_map('sanitize_text_field',(array)($data['key_features']??[]))));
    $pros = array_values(array_filter(array_map('sanitize_text_field',(array)($data['pros']??[]))));
    $cons = array_values(array_filter(array_map('sanitize_text_field',(array)($data['cons']??[]))));
    $integrations = array_values(array_filter(array_map('sanitize_text_field',(array)($data['integrations']??[]))));

    // Exact ACF field mapping used by BIGSPY AI LIST Theme v1.1.
    bigspy_update_listing_field($post_id,'tool_tagline',$short_desc);
    bigspy_update_listing_field($post_id,'pricing_model',$pricing);
    bigspy_update_listing_field($post_id,'pricing_type',$pricing_type);
    bigspy_update_listing_field($post_id,'starting_price',$starting_price);
    bigspy_update_listing_field($post_id,'price_min',$price_min);
    bigspy_update_listing_field($post_id,'price_max',$price_max);
    bigspy_update_listing_field($post_id,'price_currency',$price_currency);
    bigspy_update_listing_field($post_id,'price_unit',$price_unit);
    bigspy_update_listing_field($post_id,'price_custom_text',$price_custom_text);
    bigspy_update_listing_field($post_id,'free_trial',$free_trial);
    bigspy_update_listing_field($post_id,'api_available',$api_available);
    bigspy_update_listing_field($post_id,'api_url',$api_url);
    bigspy_update_listing_field($post_id,'company_name',$company);
    bigspy_update_listing_field($post_id,'founded_year',$founded_year ?: '');
    bigspy_update_listing_field($post_id,'key_features',bigspy_lines($features));
    bigspy_update_listing_field($post_id,'pros',bigspy_lines($pros));
    bigspy_update_listing_field($post_id,'cons',bigspy_lines($cons));
    bigspy_update_listing_field($post_id,'integrations',bigspy_lines($integrations));
    bigspy_update_listing_field($post_id,'ai_generated',1);
    bigspy_update_listing_field($post_id,'ai_model',$model);
    bigspy_update_listing_field($post_id,'ai_generated_at',current_time('mysql'));

    // Also keep the internal meta aliases for backwards compatibility.
    update_post_meta($post_id,'_bigspy_company_name',$company);
    update_post_meta($post_id,'_bigspy_pricing',$pricing);
    update_post_meta($post_id,'_bigspy_pricing_type',$pricing_type);
    update_post_meta($post_id,'_bigspy_starting_price',$starting_price);
    update_post_meta($post_id,'_bigspy_price_min',$price_min);
    update_post_meta($post_id,'_bigspy_price_max',$price_max);
    update_post_meta($post_id,'_bigspy_price_currency',$price_currency);
    update_post_meta($post_id,'_bigspy_price_unit',$price_unit);
    update_post_meta($post_id,'_bigspy_price_custom_text',$price_custom_text);
    update_post_meta($post_id,'_bigspy_free_trial',$free_trial);
    update_post_meta($post_id,'_bigspy_key_features',$features);
    update_post_meta($post_id,'_bigspy_pros',$pros);
    update_post_meta($post_id,'_bigspy_cons',$cons);
    update_post_meta($post_id,'_bigspy_integrations',$integrations);
    update_post_meta($post_id,'_bigspy_ai_model',$model);

    // The theme's Open site CTA reads official_url first. Use affiliate when supplied
    // so an affiliate-enabled listing remains monetizable without changing the visible CTA.
    $cta_url = $affiliate ?: $tool;
    if($cta_url) {
        update_post_meta($post_id,'_bigspy_external_url',$cta_url);
        update_post_meta($post_id,'_bigspy_visit_url',$cta_url);
        bigspy_update_listing_field($post_id,'official_url',$cta_url);
        bigspy_set_external_product_url($post_id,$cta_url);
    }

    // Images are never fetched or set automatically: featured and in-content images
    // are the editor's own screenshots and designs, uploaded manually.

/**
 * Resolve AI category paths into real hierarchical WooCommerce product categories.
 *
 * Accepted input examples:
 *   ['Travel > Flights', 'Marketing > SEO > Technical SEO']
 *   ['Flights'] (legacy flat format; remains a root category)
 *   [['Marketing', 'SEO', 'Technical SEO']] (defensive support for nested AI output)
 *
 * A path is limited to $max_depth levels. Existing terms are reused when the
 * same category name already exists under the same parent. New terms are
 * created with the correct parent term ID. Only leaf term IDs are returned
 * for assignment to the current product.
 */
function bigspy_resolve_product_category_paths($raw_categories, $max_depth = 5) {
    $max_depth = max(1, min(5, absint($max_depth)));
    $paths = array();

    $flatten = function($value) use (&$flatten, &$paths, $max_depth) {
        if (is_array($value)) {
            $all_scalars = true;
            foreach ($value as $item) {
                if (is_array($item) || is_object($item)) {
                    $all_scalars = false;
                    break;
                }
            }
            if ($all_scalars && !empty($value)) {
                $has_explicit_paths = false;
                foreach ($value as $item) {
                    if (strpos((string)$item, '>') !== false) {
                        $has_explicit_paths = true;
                        break;
                    }
                }

                if ($has_explicit_paths) {
                    // A top-level list such as ["Travel > Flights", "Marketing > SEO"]
                    // represents multiple independent category paths.
                    foreach ($value as $item) $flatten($item);
                } else {
                    // Legacy flat output such as ["Flights", "Hotels"] represents
                    // multiple root categories, not one multi-level path.
                    foreach ($value as $item) {
                        $item = sanitize_text_field((string)$item);
                        if ($item !== '') $paths[] = array($item);
                    }
                }
                return;
            }
            foreach ($value as $item) $flatten($item);
            return;
        }
        if (is_object($value)) {
            $flatten((array)$value);
            return;
        }

        $value = sanitize_text_field((string)$value);
        if ($value === '') return;

        $parts = array();
        foreach (preg_split('/\s*>\s*/u', $value) as $part) {
            $part = sanitize_text_field(trim($part));
            if ($part !== '') $parts[] = $part;
        }
        if ($parts) $paths[] = array_slice($parts, 0, $max_depth);
    };

    $flatten($raw_categories);

    $normalized_paths = array();
    $seen_paths = array();
    foreach ($paths as $parts) {
        $clean = array();
        foreach ($parts as $part) {
            $part = sanitize_text_field(trim($part));
            if ($part === '') continue;
            if (empty($clean) || strcasecmp(end($clean), $part) !== 0) $clean[] = $part;
        }
        if (!$clean) continue;
        $clean = array_slice($clean, 0, $max_depth);
        $key = strtolower(implode(' > ', $clean));
        if (isset($seen_paths[$key])) continue;
        $seen_paths[$key] = true;
        $normalized_paths[] = $clean;
    }

    $result = array(
        'term_ids' => array(),
        'paths' => array(),
        'created' => array(),
        'errors' => array(),
    );

    foreach ($normalized_paths as $parts) {
        $parent_id = 0;
        $resolved = array();
        $failed = false;

        foreach ($parts as $part) {
            $existing = term_exists($part, 'product_cat', $parent_id);

            if (is_array($existing) && !empty($existing['term_id'])) {
                $term_id = absint($existing['term_id']);
            } elseif (is_int($existing) || ctype_digit((string)$existing)) {
                $term_id = absint($existing);
            } else {
                $inserted = wp_insert_term($part, 'product_cat', array('parent' => $parent_id));
                if (is_wp_error($inserted)) {
                    $result['errors'][] = array(
                        'path' => implode(' > ', $parts),
                        'category' => $part,
                        'message' => $inserted->get_error_message(),
                    );
                    $failed = true;
                    break;
                }
                $term_id = absint($inserted['term_id']);
                if ($term_id) {
                    $result['created'][] = array(
                        'term_id' => $term_id,
                        'name' => $part,
                        'parent_id' => $parent_id,
                        'path' => implode(' > ', array_merge($resolved, array($part))),
                    );
                }
            }

            if (!$term_id) {
                $result['errors'][] = array(
                    'path' => implode(' > ', $parts),
                    'category' => $part,
                    'message' => 'Unable to resolve product category term.',
                );
                $failed = true;
                break;
            }

            $resolved[] = $part;
            // Keep every resolved level so WordPress/WooCommerce explicitly
            // checks the full category chain on the product edit screen.
            $result['term_ids'][] = $term_id;
            $parent_id = $term_id;
        }

        if (!$failed && $parent_id) {
            $result['paths'][] = implode(' > ', $resolved);
        }
    }

    $result['term_ids'] = array_values(array_unique(array_map('absint', $result['term_ids'])));
    $result['paths'] = array_values(array_unique($result['paths']));
    return $result;
}

    // WooCommerce categories/tags are created only when WooCommerce is active.
    // Category paths are resolved into a real parent/child taxonomy tree (max 5 levels).
    $category_result = array(
        'term_ids' => array(),
        'paths' => array(),
        'created' => array(),
        'errors' => array(),
    );
    if(taxonomy_exists('product_cat')) {
        $category_result = bigspy_resolve_product_category_paths($data['categories'] ?? array(), 5);
        if(!empty($category_result['term_ids'])) {
            // Assign every resolved level (parent + child + deeper descendants) so the
            // product edit screen explicitly checks the complete category chain.
            wp_set_object_terms($post_id, $category_result['term_ids'], 'product_cat', false);
            // The Categories page lists listings by their primary category; keep any editor's choice.
            if (!get_post_meta($post_id, 'rank_math_primary_product_cat', true)) {
                $primary_category = bigspy_deepest_term_id($category_result['term_ids']);
                if ($primary_category) update_post_meta($post_id, 'rank_math_primary_product_cat', $primary_category);
            }
        }
    }
    if(taxonomy_exists('product_tag')) {
        $tags=array_values(array_filter(array_map('sanitize_text_field',(array)($data['tags']??[]))));
        if($tags) wp_set_object_terms($post_id,$tags,'product_tag',false);
    }

    // Rank Math: always persist canonical metadata. Runtime class loading must not block saves.
    $seo_message='';
    if($focus) update_post_meta($post_id,'rank_math_focus_keyword',implode(', ',$keywords));
    if($seo_title) update_post_meta($post_id,'rank_math_title',$seo_title);
    if($seo_desc) update_post_meta($post_id,'rank_math_description',$seo_desc);
    update_post_meta($post_id,'_bigspy_rank_math_synced_at',current_time('mysql'));
    $rank_math_detected = defined('RANK_MATH_VERSION') || class_exists('RankMath\Admin\Admin');
    update_post_meta($post_id,'_bigspy_rank_math_status',$rank_math_detected ? 'detected' : 'metadata_saved_rank_math_not_detected');
    $seo_message = $rank_math_detected ? 'Rank Math: Focus Keyword, SEO Title, and Meta Description have been saved.' : 'SEO metadata has been saved to the Rank Math meta keys; Rank Math is not currently detected.';

    $edit_url=get_edit_post_link($post_id,'');
    $final_result = [
        'message'=>'Listing generated by AI and updated successfully.',
        'edit_url'=>$edit_url,
        'seo_message'=>$seo_message,
        'title'=>$title,
        'seo_title'=>$seo_title,
        'seo_description'=>$seo_desc,
        'focus_keyword'=>$focus,
        'secondary_keywords'=>$secondary_out,
        'theme_fields_synced'=>true,
        'listing_type'=>$listing_type,
        'dynamic_attributes'=>$dynamic_attributes,
        'dynamic_attribute_count'=>count($dynamic_attributes),
        'internal_links'=>$internal_links,
        'internal_link_count'=>count($internal_links),
         'category_paths'=>$category_result['paths'],
         'category_terms_created'=>count($category_result['created']),
         'category_errors'=>$category_result['errors'],
        'diagnostics'=>array(
            'plugin_version'=>BIGSPY_AI_VERSION,
            'theme_version'=>function_exists('bigspy_theme_integration_version') ? bigspy_theme_integration_version() : 'not detected',
            'article_words'=>str_word_count(wp_strip_all_tags($description)),
            'seo_title_length'=>function_exists('mb_strlen') ? mb_strlen($seo_title) : strlen($seo_title),
            'seo_description_length'=>function_exists('mb_strlen') ? mb_strlen($seo_desc) : strlen($seo_desc),
            'focus_keywords'=>$keywords,
            'rank_math_metadata_saved'=>true,
            'acf_detected'=>function_exists('update_field'),
            'featured_image'=>has_post_thumbnail($post_id)
        )
    ];
    if($web_research) update_post_meta($post_id,'_bigspy_web_research_sources',$web_research);
    bigspy_save_listing_status($job_id, array_merge(array(
        'state'=>'done',
        'post_id'=>$post_id,
        'job_id'=>$job_id,
        'completed_at'=>current_time('mysql'),
    ), $final_result), 15 * MINUTE_IN_SECONDS);
    wp_send_json_success($final_result);
});


/**
 * Shared, editable internal-link history for BIGSPY Generator and Post Writer.
 * Stored in WordPress so the same list is available across posts and browsers.
 */
function bigspy_internal_link_history_clean( $items ) {
    if ( ! is_array( $items ) ) return array();
    $site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
    $clean = array();
    $seen = array();
    foreach ( $items as $item ) {
        if ( ! is_array( $item ) ) continue;
        $label = sanitize_text_field( $item['label'] ?? $item['name'] ?? '' );
        $url = esc_url_raw( $item['url'] ?? '' );
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
        if ( $label === '' || $url === '' || ! in_array( $scheme, array( 'http', 'https' ), true ) || $host === '' || $host !== $site_host ) continue;
        $key = strtolower( $label ) . '|' . strtolower( untrailingslashit( $url ) );
        if ( isset( $seen[ $key ] ) ) continue;
        $seen[ $key ] = true;
        $clean[] = array( 'label' => $label, 'url' => $url );
        if ( count( $clean ) >= 100 ) break;
    }
    return $clean;
}

function bigspy_internal_link_history_get() {
    return bigspy_internal_link_history_clean( get_option( 'bigspy_internal_link_history', array() ) );
}

function bigspy_internal_link_history_ajax() {
    check_ajax_referer( 'bigspy_internal_link_history', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_products' ) ) wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
    $method = sanitize_key( wp_unslash( $_POST['method'] ?? 'list' ) );
    if ( $method === 'save' ) {
        $decoded = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
        $items = bigspy_internal_link_history_clean( $decoded );
        update_option( 'bigspy_internal_link_history', $items, false );
    } else {
        $items = bigspy_internal_link_history_get();
    }
    wp_send_json_success( array( 'items' => $items ) );
}
add_action( 'wp_ajax_bigspy_internal_link_history', 'bigspy_internal_link_history_ajax' );

function bigspy_internal_link_history_assets( $hook ) {
    // The Create Listing and Create Post forms live on the BIGSPY AI Customize page.
    if ( 'toplevel_page_bigspy-ai' !== $hook && ! bsl_is_create_page( $hook ) ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'product' ), true ) ) return;
    }
    wp_enqueue_script(
        'bigspy-internal-link-history',
        plugin_dir_url( __FILE__ ) . 'internal-link-history.js',
        array(),
        BIGSPY_AI_VERSION,
        true
    );
    wp_localize_script( 'bigspy-internal-link-history', 'BIGSPY_INTERNAL_LINK_HISTORY', array(
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'bigspy_internal_link_history' ),
        'siteHost' => strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'bigspy_internal_link_history_assets', 40 );
