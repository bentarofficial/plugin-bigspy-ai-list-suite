<?php
/**
 * Post Writer module (from BIGSPY AI Post Writer 1.2.4). Uses the Listing Generator module for the AI call,
 * models and web research. Function, AJAX action and meta names are kept.
 */

if (!defined('ABSPATH')) exit;
if (defined('BIGSPY_POST_WRITER_VERSION')) return;

define('BIGSPY_POST_WRITER_VERSION', '1.2.4');

function bigspy_pw_ready() {
    return function_exists('bigspy_get_url') && function_exists('bigspy_get_key') && function_exists('bigspy_models') && function_exists('bigspy_call_ai') && function_exists('bigspy_parse_json');
}

function bigspy_pw_admin_notice() {
    if (!current_user_can('activate_plugins') || bigspy_pw_ready()) return;
    echo '<div class="notice notice-error"><p><strong>AI List Suite:</strong> Post Writer needs the Listing Generator module. Turn it on in <a href="' . esc_url(admin_url('admin.php?page=' . BSL_PAGE)) . '">AI List Suite</a>.</p></div>';
}
add_action('admin_notices', 'bigspy_pw_admin_notice');

function bsl_writer_site_name() {
    $name = wp_specialchars_decode((string)get_bloginfo('name'), ENT_QUOTES);
    return $name !== '' ? $name : 'this website';
}

function bigspy_pw_clip($value, $limit) {
    $value = trim(wp_strip_all_tags((string)$value));
    if (function_exists('mb_substr')) return mb_substr($value, 0, $limit);
    return substr($value, 0, $limit);
}

function bigspy_pw_models() {
    return bigspy_pw_ready() ? (array)bigspy_models() : array();
}

function bigspy_pw_customize_tab_nav() {
    if (!current_user_can('edit_posts') && !current_user_can('manage_options')) return;
    echo '<button type="button" class="bigspy-admin-tab" id="bigspy-admin-tab-create-post" role="tab" aria-selected="false" aria-controls="bigspy-admin-panel-create-post" tabindex="-1" data-bigspy-admin-tab="create-post"><span class="dashicons dashicons-edit-page" aria-hidden="true"></span>Create Post</button>';
}
add_action('bigspy_ai_admin_tabs_nav', 'bigspy_pw_customize_tab_nav');

function bigspy_pw_customize_tab_panel() {
    if (!current_user_can('edit_posts') && !current_user_can('manage_options')) return;
    echo '<div class="bigspy-admin-tab-panel" id="bigspy-admin-panel-create-post" role="tabpanel" aria-labelledby="bigspy-admin-tab-create-post" data-bigspy-admin-panel="create-post" hidden><section class="bigspy-sponsored-panel"><div class="bigspy-direct-settings__head"><div><h2>Create Post with AI</h2><p>Create a researched draft article, then open its editor to review the content and add images before publishing.</p></div></div>';
    bigspy_pw_render_meta_box((object)array('ID'=>0));
    echo '</section></div>';
}
add_action('bigspy_ai_admin_tabs_panels', 'bigspy_pw_customize_tab_panel');

function bigspy_pw_saved_rows($post_id, $key) {
    $rows = get_post_meta($post_id, $key, true);
    return is_array($rows) ? $rows : array();
}

function bigspy_pw_render_meta_box($post) {
    wp_nonce_field('bigspy_pw_meta_box', 'bigspy_pw_meta_nonce');
    $external = bigspy_pw_saved_rows($post->ID, '_bigspy_pw_external_anchors');
    $internal = bigspy_pw_saved_rows($post->ID, '_bigspy_pw_internal_anchors');
    if (!$internal && function_exists('bigspy_internal_link_history_get')) {
        $internal = array_map(function($row) {
            return array('name'=>(string)($row['label'] ?? ''), 'url'=>(string)($row['url'] ?? ''));
        }, bigspy_internal_link_history_get());
    }
    $description = (string)get_post_meta($post->ID, '_bigspy_pw_description', true);
    $word_count = max(500, min(5000, absint(get_post_meta($post->ID, '_bigspy_pw_word_count', true) ?: 1500)));
    $saved_model = (string)get_post_meta($post->ID, '_bigspy_pw_model', true);
    $models = bigspy_pw_models();
    $configured = bigspy_pw_ready() && bigspy_get_url() && bigspy_get_key();
    $research = function_exists('bigspy_get_serper_key') && bigspy_get_serper_key() !== '';
    ?>
    <div id="bigspy-pw-box">
      <?php if (!$configured): ?><div class="notice notice-warning inline"><p>Turn on the Listing Generator module and add an AI key in AI List Suite before writing an article.</p></div><?php endif; ?>
      <p style="margin:0 0 14px;padding:10px 12px;border-left:4px solid #008080;background:#f0f7f7"><strong>Google Web Research:</strong> <?php echo $research ? '<span style="color:#008000">Enabled</span>' : '<span style="color:#b32d2e">Disabled or missing API key</span>'; ?>. Research uses Google results and does not crawl partner websites.</p>

      <div class="bigspy-pw-panel" data-type="external">
        <h3>1. External Anchors</h3>
        <p>Enter the exact tool, product, service, or website name and its destination URL. The name is used as the link anchor.</p>
        <div class="bigspy-pw-rows">
          <?php $rows = $external ?: array(array()); foreach ($rows as $row): ?>
            <div class="bigspy-pw-row">
              <input type="text" class="bigspy-pw-name" placeholder="Tool, product or website name" value="<?php echo esc_attr($row['name'] ?? ''); ?>">
              <input type="url" class="bigspy-pw-url" placeholder="https://example.com/" value="<?php echo esc_attr($row['url'] ?? ''); ?>">
              <input type="url" class="bigspy-pw-affiliate" placeholder="Affiliate URL (optional)" value="<?php echo esc_attr($row['affiliate_url'] ?? ''); ?>">
              <label><input type="checkbox" class="bigspy-pw-sponsored" <?php checked(!empty($row['sponsored'])); ?>> Sponsored</label>
              <button type="button" class="button bigspy-pw-remove">Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="button bigspy-pw-add">Add External Anchor</button>
      </div>

      <div class="bigspy-pw-panel" data-type="internal">
        <h3>2. Internal Anchors</h3>
        <p>Optional links to existing articles, listings, categories, or pages on this site.</p>
        <div class="bigspy-pw-rows">
          <?php $rows = $internal ?: array(array()); foreach ($rows as $row): ?>
            <div class="bigspy-pw-row">
              <input type="text" class="bigspy-pw-name" list="bigspy-pw-internal-name-history" autocomplete="off" placeholder="Internal anchor text" value="<?php echo esc_attr($row['name'] ?? ''); ?>">
              <input type="url" class="bigspy-pw-url" list="bigspy-pw-internal-url-history" autocomplete="off" placeholder="<?php echo esc_attr(home_url('/') ); ?>" value="<?php echo esc_attr($row['url'] ?? ''); ?>">
              <button type="button" class="button bigspy-pw-remove">Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="button bigspy-pw-add">Add Internal Anchor</button>
        <datalist id="bigspy-pw-internal-name-history"></datalist>
        <datalist id="bigspy-pw-internal-url-history"></datalist>
        <p class="description">Internal anchors typed here are saved automatically and filled into the next new post. Remove a row to drop that link.</p>
      </div>

      <p><label for="bigspy-pw-description"><strong>3. Describe the Article You Want</strong></label><textarea id="bigspy-pw-description" rows="6" style="width:100%" placeholder="Describe the article in English: purpose, audience, angle, comparisons, and information to cover."><?php echo esc_textarea($description); ?></textarea><span class="description">Write the brief in English. The generated title, excerpt, article, taxonomy, and SEO metadata are always English, even if the brief contains another language.</span></p>
      <div style="display:grid;grid-template-columns:minmax(180px,260px) minmax(220px,1fr);gap:18px;align-items:end">
        <p><label for="bigspy-pw-word-count"><strong>4. Target Word Count</strong></label><br><input id="bigspy-pw-word-count" type="number" min="500" max="5000" step="100" value="<?php echo esc_attr($word_count); ?>" style="width:100%"></p>
        <p><label for="bigspy-pw-model"><strong>5. AI Model</strong></label><br><select id="bigspy-pw-model" style="width:100%"><option value="">Select a model</option><?php foreach ($models as $id=>$model): ?><option value="<?php echo esc_attr($id); ?>" <?php selected($saved_model, $id); ?>><?php echo esc_html(is_array($model) ? ($model['label'] ?? $id) : $id); ?></option><?php endforeach; ?></select></p>
      </div>
      <p><button type="button" id="bigspy-pw-generate" class="button button-primary button-large" <?php disabled(!$configured); ?>>Write Article Draft</button></p>
      <div id="bigspy-pw-status" aria-live="polite"></div>
    </div>
    <style>
      #bigspy-pw-box .bigspy-pw-panel{margin:14px 0;padding:14px;border:1px solid #dcdcde;border-radius:7px;background:#fafafa}#bigspy-pw-box h3{margin:0 0 5px}.bigspy-pw-row{display:grid;grid-template-columns:minmax(140px,1fr) minmax(180px,1.25fr) minmax(180px,1.25fr) auto auto;gap:8px;align-items:center;margin:8px 0}.bigspy-pw-panel[data-type="internal"] .bigspy-pw-row{grid-template-columns:minmax(160px,1fr) minmax(220px,1.6fr) auto}.bigspy-pw-row input[type=text],.bigspy-pw-row input[type=url]{width:100%}@media(max-width:900px){.bigspy-pw-row,.bigspy-pw-panel[data-type="internal"] .bigspy-pw-row{grid-template-columns:1fr}}
    </style>
    <script>
    jQuery(function($){
      const box=$('#bigspy-pw-box'), status=$('#bigspy-pw-status');
      function row(type){return type==='external'?'<div class="bigspy-pw-row"><input type="text" class="bigspy-pw-name" placeholder="Tool, product or website name"><input type="url" class="bigspy-pw-url" placeholder="https://example.com/"><input type="url" class="bigspy-pw-affiliate" placeholder="Affiliate URL (optional)"><label><input type="checkbox" class="bigspy-pw-sponsored"> Sponsored</label><button type="button" class="button bigspy-pw-remove">Remove</button></div>':'<div class="bigspy-pw-row"><input type="text" class="bigspy-pw-name" list="bigspy-pw-internal-name-history" autocomplete="off" placeholder="Internal anchor text"><input type="url" class="bigspy-pw-url" list="bigspy-pw-internal-url-history" autocomplete="off" placeholder="<?php echo esc_js(home_url('/') ); ?>"><button type="button" class="button bigspy-pw-remove">Remove</button></div>'}
      box.on('click','.bigspy-pw-add',function(){const panel=$(this).closest('.bigspy-pw-panel'),max=panel.data('type')==='external'?20:8;if(panel.find('.bigspy-pw-row').length>=max)return;panel.find('.bigspy-pw-rows').append(row(panel.data('type')))});
      box.on('click','.bigspy-pw-remove',function(){const rows=$(this).closest('.bigspy-pw-rows');$(this).closest('.bigspy-pw-row').remove();if(!rows.children().length)rows.append(row(rows.closest('.bigspy-pw-panel').data('type')))});
      function collect(type){const out=[];box.find('.bigspy-pw-panel[data-type="'+type+'"] .bigspy-pw-row').each(function(){const r=$(this),name=r.find('.bigspy-pw-name').val().trim(),url=r.find('.bigspy-pw-url').val().trim();if(name&&url){const item={name:name,url:url};if(type==='external'){item.affiliate_url=r.find('.bigspy-pw-affiliate').val().trim();item.sponsored=r.find('.bigspy-pw-sponsored').is(':checked')?1:0}out.push(item)}});return out}
      function msg(text,ok){status.html('<div style="margin-top:10px;padding:10px;border-left:4px solid '+(ok?'#00a32a':'#d63638')+';background:#f6f7f7">'+$('<div>').text(text).html()+'</div>')}
      $('#bigspy-pw-generate').on('click',function(){const btn=$(this),description=$('#bigspy-pw-description').val().trim(),model=$('#bigspy-pw-model').val();if(!description)return msg('Please describe the article you want.',false);if(!model)return msg('Please select an AI model.',false);btn.prop('disabled',true);msg('Starting the background article job...',true);$.post(ajaxurl,{action:'bigspy_pw_generate',nonce:'<?php echo esc_js(wp_create_nonce('bigspy_pw_generate')); ?>',post_id:<?php echo (int)$post->ID; ?>,description:description,word_count:$('#bigspy-pw-word-count').val(),model:model,external:JSON.stringify(collect('external')),internal:JSON.stringify(collect('internal'))}).done(function(resp){if(!resp||!resp.success){btn.prop('disabled',false);return msg(resp&&resp.data&&resp.data.message?resp.data.message:'Could not start the article job.',false)}const job=resp.data.job_id,targetPostId=resp.data.post_id;msg(resp.data.message,true);$.post(ajaxurl,{action:'bigspy_pw_background',nonce:resp.data.background_nonce,job_id:job,post_id:targetPostId,description:description,word_count:$('#bigspy-pw-word-count').val(),model:model,external:JSON.stringify(collect('external')),internal:JSON.stringify(collect('internal'))});let tries=0;const timer=setInterval(function(){tries++;$.post(ajaxurl,{action:'bigspy_pw_status',nonce:'<?php echo esc_js(wp_create_nonce('bigspy_pw_generate')); ?>',post_id:targetPostId,job_id:job}).done(function(s){if(!s||!s.success)return;if(s.data.state==='done'){clearInterval(timer);btn.prop('disabled',false);msg('Article draft created.',true);if(s.data.edit_url)status.append('<p><a class="button button-primary" href="'+$('<div>').text(s.data.edit_url).html()+'">View and edit new post</a></p>')}else if(s.data.state==='error'){clearInterval(timer);btn.prop('disabled',false);msg(s.data.message||'Article generation failed.',false)}else msg(s.data.message||'Researching and writing the article...',true)});if(tries>=180){clearInterval(timer);btn.prop('disabled',false);msg('The job is still running. Return later to see the result.',false)}},2000)}).fail(function(){btn.prop('disabled',false);msg('Could not connect to WordPress AJAX.',false)})});
    });
    </script>
    <?php
}

function bigspy_pw_sanitize_rows($raw, $external) {
    $decoded = json_decode(wp_unslash((string)$raw), true);
    if (!is_array($decoded)) return array();
    $max = $external ? 20 : 8;
    $site_host = strtolower((string)wp_parse_url(home_url('/'), PHP_URL_HOST));
    $out = array();
    foreach (array_slice($decoded, 0, $max) as $row) {
        if (!is_array($row)) continue;
        $name = sanitize_text_field($row['name'] ?? '');
        $url = esc_url_raw($row['url'] ?? '');
        if (!$name || !$url || !in_array(wp_parse_url($url, PHP_URL_SCHEME), array('http','https'), true)) continue;
        if (!$external && strtolower((string)wp_parse_url($url, PHP_URL_HOST)) !== $site_host) continue;
        $item = array('name'=>$name, 'url'=>$url);
        if ($external) {
            $item['affiliate_url'] = esc_url_raw($row['affiliate_url'] ?? '');
            $item['sponsored'] = !empty($row['sponsored']);
        }
        $out[] = $item;
    }
    return $out;
}

function bigspy_pw_prompt($description, $word_count, $external, $internal, $research) {
    $external_json = wp_json_encode($external, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $internal_json = wp_json_encode($internal, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return "You are the senior English-language editor for ".bsl_writer_site_name().", writing helpful, evidence-aware articles about tools, products, services, websites, software, and technology. LANGUAGE IS NON-NEGOTIABLE: every generated textual value must be written in natural English, including title, excerpt, content_html, SEO metadata, slug, focus keyword, secondary keywords, categories, and tags. The user brief may be written in Vietnamese or another language; silently understand or translate its intent, but never reproduce that language in the output. Proper names and supplied anchor names may remain unchanged.\n\nARTICLE REQUEST\n{$description}\n\nTARGET LENGTH\nApproximately {$word_count} words, within 10 percent where the subject supports it. Never pad or repeat ideas merely to hit the target.\n\nEXTERNAL ANCHORS\n{$external_json}\nUse every supplied name as the exact visible anchor text and use its affiliate_url when present, otherwise url. Add target=\"_blank\". Sponsored or affiliate links must use rel=\"nofollow sponsored noopener\"; ordinary external links use rel=\"noopener\". Never invent or alter a URL.\n\nINTERNAL ANCHORS\n{$internal_json}\nPlace these naturally where contextually relevant. Never invent or alter a URL.\n\nRESEARCH MATERIAL\n{$research}\nTreat search snippets as research leads, not unquestionable proof. Prefer recent, consistent information. Do not claim first-hand testing, invent facts, prices, dates, features, quotes, or statistics. Do not copy source wording.\n\nWRITING RULES\n- Write in friendly, natural American English for humans first, while making the answer easy for search engines and AI answer systems to understand.\n- Create an original post title of at most 70 characters. Do not merely repeat the user's description.\n- Create a 30-60 word excerpt/sapo.\n- content_html must not contain H1. Use logical H2 sections. If an H2 contains two or more distinct main ideas, divide it with H3 headings. If an H3 contains two or more distinct sub-ideas, divide it with H4 headings. Never skip heading levels.\n- Keep most paragraphs to two or three sentences. Start a new paragraph after roughly two complete sentences when doing so improves readability. Occasionally use an ellipsis for a natural pause, but do not overuse it.\n- Use tables, lists, definitions, direct answers, comparisons, best-for guidance, advantages, limitations, and concrete examples when they help the reader.\n- For a list or best-of article, give each supplied external entity a meaningful section and a fair, useful explanation.\n- Choose one focus keyword that describes the article topic and is not the name of a supplied tool, product, service, or website. Use it naturally around 1.0-1.5 percent and never exceed 1.8 percent. Choose zero to four semantic secondary keywords and use them naturally without a fixed density. Total keywords must be between one and five.\n- Create one or two useful blog categories and three to eight tags. Prefer broad, reusable taxonomy names rather than near-duplicates.\n- SEO title must be at most 60 characters. SEO description must be at most 160 characters.\n- Return clean WordPress-compatible HTML only in content_html. Do not include html, head, body, style, script, Markdown, a table of contents, author biography, dates, reading time, related posts, or featured listings. The theme renders those elements.\n\nReturn only valid JSON with exactly these keys: title, excerpt, content_html, seo_title, seo_description, slug, focus_keyword, secondary_keywords, categories, tags.";
}

function bigspy_pw_looks_vietnamese($data) {
    $text = strtolower(wp_strip_all_tags(implode(' ', array(
        (string)($data['title'] ?? ''), (string)($data['excerpt'] ?? ''), (string)($data['content_html'] ?? ''),
        (string)($data['seo_title'] ?? ''), (string)($data['seo_description'] ?? '')
    ))));
    if ($text === '') return false;
    preg_match_all('/\b(và|của|là|được|những|các|cho|với|trong|không|một|này|khi|người|để|từ|sản phẩm|công cụ|bài viết)\b/u', $text, $matches);
    return count($matches[0] ?? array()) >= 5;
}
function bigspy_pw_research($description, $external) {
    if (!function_exists('bigspy_get_serper_key') || bigspy_get_serper_key() === '' || !function_exists('bigspy_serper_search')) return '[Google Web Research API key is unavailable.]';
    $num = max(3, min(10, absint(bsl_get('generator.web_research_results', 5))));
    $items = array();
    foreach ($external as $row) {
        $host = (string)wp_parse_url($row['url'], PHP_URL_HOST);
        $query = trim($row['name'].' '.$host.' features pricing use cases');
        $items = array_merge($items, bigspy_serper_search($query, 'search', $num));
    }
    $topic = bigspy_pw_clip($description, 180);
    if ($topic) {
        $items = array_merge($items, bigspy_serper_search($topic, 'search', $num));
        $items = array_merge($items, bigspy_serper_search($topic.' latest news', 'news', min(5,$num)));
    }
    $dedup = array();
    foreach ($items as $item) {
        $key = !empty($item['url']) ? $item['url'] : md5(($item['title']??'').'|'.($item['snippet']??''));
        if (!isset($dedup[$key])) $dedup[$key] = $item;
    }
    return function_exists('bigspy_format_web_research') ? bigspy_format_web_research(array_values($dedup), 30000) : wp_json_encode(array_values($dedup));
}

function bigspy_pw_status_key($job_id) { return 'bigspy_pw_job_'.sanitize_key($job_id); }
function bigspy_pw_set_status($job_id, $data) { $data['updated_at'] = time(); set_transient(bigspy_pw_status_key($job_id), $data, HOUR_IN_SECONDS); }

function bigspy_pw_ajax_generate() {
    check_ajax_referer('bigspy_pw_generate', 'nonce');
    $post_id = absint($_POST['post_id'] ?? 0);
    if (!bigspy_pw_ready() || !bigspy_get_url() || !bigspy_get_key()) wp_send_json_error(array('message'=>'AI configuration is unavailable. Turn on Listing Generator and add an AI key in AI List Suite.'));
    $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
    $word_count = max(500, min(5000, absint($_POST['word_count'] ?? 1500)));
    $model = sanitize_text_field(wp_unslash($_POST['model'] ?? ''));
    if (!$description || !$model || !isset(bigspy_pw_models()[$model])) wp_send_json_error(array('message'=>'Article description and a valid model are required.'));
    // Validate the request before creating a draft so failed submissions leave no empty posts behind.
    if (!$post_id) {
        if (!current_user_can('edit_posts')) wp_send_json_error(array('message'=>'You cannot create posts.'));
        $post_id = wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'AI Post Draft','post_author'=>get_current_user_id()), true);
        if (is_wp_error($post_id)) wp_send_json_error(array('message'=>'Could not create the post draft: '.$post_id->get_error_message()));
    }
    if (!current_user_can('edit_post', $post_id)) wp_send_json_error(array('message'=>'You cannot edit this post.'));
    $external = bigspy_pw_sanitize_rows($_POST['external'] ?? '[]', true);
    $internal = bigspy_pw_sanitize_rows($_POST['internal'] ?? '[]', false);
    update_post_meta($post_id, '_bigspy_pw_description', $description);
    update_post_meta($post_id, '_bigspy_pw_word_count', $word_count);
    update_post_meta($post_id, '_bigspy_pw_model', $model);
    update_post_meta($post_id, '_bigspy_pw_external_anchors', $external);
    update_post_meta($post_id, '_bigspy_pw_internal_anchors', $internal);
    $job_id = wp_generate_uuid4();
    bigspy_pw_set_status($job_id, array('state'=>'queued','message'=>'Article job queued.','post_id'=>$post_id));
    // The browser sends the long-running request itself and keeps it open, so servers such as
    // LiteSpeed do not abort it the way they can abort a loopback request after its caller disconnects.
    wp_send_json_success(array('job_id'=>$job_id,'post_id'=>$post_id,'background_nonce'=>wp_create_nonce('bigspy_pw_background_'.$job_id),'message'=>'Researching sources and writing the article. Keep this page open...'));
}
add_action('wp_ajax_bigspy_pw_generate', 'bigspy_pw_ajax_generate');

function bigspy_pw_ajax_status() {
    check_ajax_referer('bigspy_pw_generate', 'nonce');
    $post_id = absint($_POST['post_id'] ?? 0); $job_id = sanitize_text_field(wp_unslash($_POST['job_id'] ?? ''));
    if (!$post_id || !current_user_can('edit_post',$post_id)) wp_send_json_error(array('message'=>'Permission denied.'));
    $status = get_transient(bigspy_pw_status_key($job_id));
    if (!is_array($status) || absint($status['post_id']??0)!==$post_id) wp_send_json_error(array('message'=>'Job status is unavailable.'));
    // Watchdog: a job that has not reported progress for six minutes was stopped by the server.
    if (in_array($status['state']??'', array('queued','running'), true) && !empty($status['updated_at']) && time()-(int)$status['updated_at'] > 6 * MINUTE_IN_SECONDS) {
        $status = array('state'=>'error','message'=>'The server stopped the article job at this step: "'.($status['message']??'unknown').'" Please try again with fewer anchors, a shorter word count, or a faster AI model.','post_id'=>$post_id);
        bigspy_pw_set_status($job_id, $status);
    }
    wp_send_json_success($status);
}
add_action('wp_ajax_bigspy_pw_status', 'bigspy_pw_ajax_status');

function bigspy_pw_term_ids($names, $taxonomy, $limit) {
    $ids = array();
    foreach (array_slice(array_unique(array_filter(array_map('sanitize_text_field',(array)$names))),0,$limit) as $name) {
        $existing = term_exists($name, $taxonomy);
        // Only users who may manage terms can have AI create new categories or tags.
        if (!$existing && current_user_can(get_taxonomy($taxonomy)->cap->manage_terms)) $existing = wp_insert_term($name, $taxonomy);
        if (!$existing || is_wp_error($existing)) continue;
        $ids[] = absint(is_array($existing) ? $existing['term_id'] : $existing);
    }
    return array_values(array_filter($ids));
}

function bigspy_pw_ajax_background() {
    $job_id = sanitize_text_field(wp_unslash($_POST['job_id'] ?? '')); $post_id = absint($_POST['post_id'] ?? 0);
    if (!$job_id || !wp_verify_nonce($_POST['nonce'] ?? '', 'bigspy_pw_background_'.$job_id) || !$post_id || !current_user_can('edit_post',$post_id)) wp_die('Invalid job.');
    ignore_user_abort(true); @set_time_limit(300);
    $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
    $word_count = max(500,min(5000,absint($_POST['word_count']??1500)));
    $model = sanitize_text_field(wp_unslash($_POST['model']??''));
    $external = bigspy_pw_sanitize_rows($_POST['external']??'[]',true); $internal = bigspy_pw_sanitize_rows($_POST['internal']??'[]',false);
    $internal = apply_filters('bigspy_pw_internal_anchors', $internal, $description);
    bigspy_pw_set_status($job_id,array('state'=>'running','message'=>'Researching Google results and writing the article...','post_id'=>$post_id));
    try {
        $research = bigspy_pw_research($description,$external);
        bigspy_pw_set_status($job_id,array('state'=>'running','message'=>'The AI is writing the article. This can take a few minutes...','post_id'=>$post_id));
        $reply = bigspy_call_ai(array(array('role'=>'user','content'=>bigspy_pw_prompt($description,$word_count,$external,$internal,$research))),$model);
        if (is_wp_error($reply)) throw new Exception($reply->get_error_message());
        $data = bigspy_parse_json($reply);
        if (!is_array($data)) throw new Exception('AI did not return valid article JSON.');
        if (bigspy_pw_looks_vietnamese($data)) {
            $retry_prompt = bigspy_pw_prompt($description,$word_count,$external,$internal,$research)."\n\nCRITICAL RETRY: The previous response contained Vietnamese. Regenerate the complete JSON from scratch in English only. Never output Vietnamese words or sentences.";
            $reply = bigspy_call_ai(array(array('role'=>'user','content'=>$retry_prompt)),$model);
            if (is_wp_error($reply)) throw new Exception($reply->get_error_message());
            $data = bigspy_parse_json($reply);
            if (!is_array($data) || bigspy_pw_looks_vietnamese($data)) throw new Exception('AI did not return an English article. Nothing was saved; please try again.');
        }
        $title = bigspy_pw_clip($data['title']??'',70); $excerpt = sanitize_textarea_field($data['excerpt']??''); $content = wp_kses_post($data['content_html']??'');
        if (!$title || !$content) throw new Exception('AI response is missing the title or article content.');
        $slug = sanitize_title($data['slug']??$title);
        wp_update_post(array('ID'=>$post_id,'post_title'=>$title,'post_excerpt'=>$excerpt,'post_content'=>$content,'post_name'=>$slug,'post_status'=>'draft'));
        $category_ids = bigspy_pw_term_ids($data['categories']??array(),'category',2);
        if ($category_ids) { wp_set_post_categories($post_id,$category_ids,false); update_post_meta($post_id,'rank_math_primary_category',$category_ids[0]); }
        $tag_ids = bigspy_pw_term_ids($data['tags']??array(),'post_tag',8); if ($tag_ids) wp_set_post_terms($post_id,$tag_ids,'post_tag',false);
        $focus = bigspy_pw_clip($data['focus_keyword']??'',100); $secondary = array_slice(array_unique(array_filter(array_map('sanitize_text_field',(array)($data['secondary_keywords']??array())))),0,4);
        $keywords = array_values(array_unique(array_filter(array_merge(array($focus),$secondary))));
        update_post_meta($post_id,'rank_math_focus_keyword',implode(', ',$keywords));
        update_post_meta($post_id,'rank_math_title',bigspy_pw_clip($data['seo_title']??$title,60));
        update_post_meta($post_id,'rank_math_description',bigspy_pw_clip($data['seo_description']??$excerpt,160));
        update_post_meta($post_id,'_bigspy_pw_sources',$research);
        bigspy_pw_set_status($job_id,array('state'=>'done','message'=>'Article draft created.','post_id'=>$post_id,'edit_url'=>get_edit_post_link($post_id,'')));
    } catch (Throwable $e) { bigspy_pw_set_status($job_id,array('state'=>'error','message'=>$e->getMessage(),'post_id'=>$post_id)); }
    wp_die();
}
add_action('wp_ajax_bigspy_pw_background', 'bigspy_pw_ajax_background');

function bigspy_pw_attachment_fields($fields, $post) {
    if (strpos((string)$post->post_mime_type,'image/')!==0) return $fields;
    $keyword = (string)get_post_meta($post->ID,'_bigspy_media_keyword',true);
    $desc = (string)get_post_meta($post->ID,'_bigspy_media_context',true);
    $saved_model = (string)get_post_meta($post->ID,'_bigspy_media_model',true);
    $options = '<option value="">Select a model</option>';
    foreach (bigspy_pw_models() as $id=>$model) $options .= '<option value="'.esc_attr($id).'" '.selected($saved_model,$id,false).'>'.esc_html(is_array($model)?($model['label']??$id):$id).'</option>';
    $fields['bigspy_media_keyword'] = array('label'=>'Primary keyword','input'=>'text','value'=>esc_attr($keyword),'required'=>true,'helps'=>'Required. Enter the main English SEO keyword for this image.');
    $fields['bigspy_media_context'] = array('label'=>'Short image description','input'=>'text','value'=>esc_attr($desc),'required'=>true,'helps'=>'Required. Briefly describe what is actually visible in the image so AI does not invent details.');
    $fields['bigspy_media_model'] = array('label'=>'AI model','input'=>'html','html'=>'<select name="attachments['.$post->ID.'][bigspy_media_model]" class="bigspy-media-model" style="width:100%">'.$options.'</select>');
    $fields['bigspy_media_generate'] = array('label'=>'','input'=>'html','html'=>'<button type="button" class="button button-primary bigspy-media-generate" data-id="'.$post->ID.'">Generate image SEO</button> <span class="bigspy-media-status" style="margin-left:8px"></span>');
    return $fields;
}
add_filter('attachment_fields_to_edit','bigspy_pw_attachment_fields',10,2);

function bigspy_pw_save_attachment_fields($post, $attachment) {
    if (isset($attachment['bigspy_media_keyword'])) update_post_meta($post['ID'],'_bigspy_media_keyword',sanitize_text_field($attachment['bigspy_media_keyword']));
    if (isset($attachment['bigspy_media_context'])) update_post_meta($post['ID'],'_bigspy_media_context',sanitize_text_field($attachment['bigspy_media_context']));
    if (isset($attachment['bigspy_media_model'])) update_post_meta($post['ID'],'_bigspy_media_model',sanitize_text_field($attachment['bigspy_media_model']));
    return $post;
}
add_filter('attachment_fields_to_save','bigspy_pw_save_attachment_fields',10,2);

function bigspy_pw_media_ajax() {
    check_ajax_referer('bigspy_pw_media','nonce');
    $id=absint($_POST['attachment_id']??0); $model=sanitize_text_field(wp_unslash($_POST['model']??'')); $keyword=sanitize_text_field(wp_unslash($_POST['keyword']??'')); $context=sanitize_text_field(wp_unslash($_POST['context']??''));
    if (!$id || !current_user_can('edit_post',$id)) wp_send_json_error(array('message'=>'You cannot edit this image.'));
    if (!$keyword) wp_send_json_error(array('message'=>'Enter the primary keyword.'));
    if (!$context) wp_send_json_error(array('message'=>'Enter a short image description.'));
    if (!bigspy_pw_ready() || !isset(bigspy_pw_models()[$model])) wp_send_json_error(array('message'=>'Select a valid model and configure AI List Suite.'));
    $attachment=get_post($id); if (!$attachment || strpos((string)$attachment->post_mime_type,'image/')!==0) wp_send_json_error(array('message'=>'This attachment is not an image.'));
    update_post_meta($id,'_bigspy_media_keyword',$keyword); update_post_meta($id,'_bigspy_media_context',$context); update_post_meta($id,'_bigspy_media_model',$model);
    $parent=$attachment->post_parent?get_post($attachment->post_parent):null; $parent_context=$parent?bigspy_pw_clip($parent->post_title.' '.wp_strip_all_tags($parent->post_excerpt.' '.$parent->post_content),1200):'';
    $filename=basename((string)get_attached_file($id));
    $prompt="Create accurate SEO metadata in English only for a WordPress image. Primary keyword: {$keyword}. Filename: {$filename}. Required description of visible content: {$context}. Parent article context: {$parent_context}. Use the primary keyword naturally where it accurately matches the visible content, without keyword stuffing. Do not claim objects, people, brands, text, actions, or details unsupported by the supplied description. Alt text must concisely describe the image for accessibility, normally under 125 characters, without phrases such as image of. The title must be concise. The caption must be useful when displayed below the image. The description may be one or two sentences. Every returned value must be written in natural English. Return only JSON with exactly these keys: alt, title, caption, description.";
    $reply=bigspy_call_ai(array(array('role'=>'user','content'=>$prompt)),$model); if(is_wp_error($reply))wp_send_json_error(array('message'=>$reply->get_error_message()));
    $data=bigspy_parse_json($reply); if(!is_array($data)||empty($data['alt'])||empty($data['title']))wp_send_json_error(array('message'=>'AI did not return valid image metadata.'));
    $alt=bigspy_pw_clip($data['alt'],180); $title=bigspy_pw_clip($data['title'],160); $caption=sanitize_textarea_field($data['caption']??''); $description=sanitize_textarea_field($data['description']??'');
    update_post_meta($id,'_wp_attachment_image_alt',$alt); wp_update_post(array('ID'=>$id,'post_title'=>$title,'post_excerpt'=>$caption,'post_content'=>$description));
    wp_send_json_success(array('alt'=>$alt,'title'=>$title,'caption'=>$caption,'description'=>$description));
}
add_action('wp_ajax_bigspy_pw_media_generate','bigspy_pw_media_ajax');

function bigspy_pw_media_script() {
    if (!is_admin()) return; ?>
    <script>jQuery(document).on('click','.bigspy-media-generate',function(e){e.preventDefault();const $=jQuery,b=$(this),id=b.data('id'),row=b.closest('.compat-item'),status=b.siblings('.bigspy-media-status'),keyword=(row.find('[name="attachments['+id+'][bigspy_media_keyword]"]').val()||'').trim(),context=(row.find('[name="attachments['+id+'][bigspy_media_context]"]').val()||'').trim(),model=row.find('[name="attachments['+id+'][bigspy_media_model]"]').val()||'';if(!keyword){status.css('color','#b32d2e').text('Enter the primary keyword.');return}if(!context){status.css('color','#b32d2e').text('Enter a short image description.');return}if(!model){status.css('color','#b32d2e').text('Select a model.');return}b.prop('disabled',true);status.css('color','#646970').text('Generating English image metadata...');$.post(ajaxurl,{action:'bigspy_pw_media_generate',nonce:'<?php echo esc_js(wp_create_nonce('bigspy_pw_media')); ?>',attachment_id:id,keyword:keyword,context:context,model:model}).done(function(r){if(r&&r.success){status.css('color','#008000').text('Image SEO metadata created.');if(window.wp&&wp.media)wp.media.model.Attachment.get(id).fetch()}else status.css('color','#b32d2e').text(r&&r.data&&r.data.message?r.data.message:'Generation failed.');}).fail(function(){status.css('color','#b32d2e').text('Connection failed.');}).always(function(){b.prop('disabled',false)})});</script>
<?php }
add_action('admin_footer','bigspy_pw_media_script');
