<?php
/**
 * Listing tools: allowed categories (G-155), duplicate check, listing facts with a
 * "last checked" date (G-184) and outbound affiliate click tracking with SubIDs (G-185).
 */
defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ */
/* Categories                                                          */
/* ------------------------------------------------------------------ */

/** Comparable form of a category name: lower case, "&" read as "and", punctuation dropped. */
function bigspy_category_norm( $name ) {
	$name = strtolower( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
	$name = str_replace( '&', ' and ', $name );
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', $name ) );
}

/** True when new categories must come from the existing list (default). */
function bigspy_categories_locked() {
	return 'open' !== bsl_get( 'generator.category_mode', 'locked' );
}

/**
 * Index of product categories: full normalized path => term id, and normalized leaf name => term ids.
 * With $only_used, categories without listings are left out.
 */
function bigspy_category_index( $only_used = true ) {
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	$index = array( 'paths' => array(), 'leaves' => array(), 'by_id' => array() );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return $index;
	}
	foreach ( $terms as $term ) {
		$index['by_id'][ (int) $term->term_id ] = $term;
	}
	foreach ( $terms as $term ) {
		if ( $only_used && (int) $term->count < 1 && ! bigspy_category_has_used_child( (int) $term->term_id, $index['by_id'] ) ) {
			continue;
		}
		$names   = array();
		$current = $term;
		for ( $depth = 0; $current && $depth < 6; $depth++ ) {
			array_unshift( $names, bigspy_category_norm( $current->name ) );
			$current = $current->parent && isset( $index['by_id'][ $current->parent ] ) ? $index['by_id'][ $current->parent ] : null;
		}
		$index['paths'][ implode( ' > ', $names ) ]                    = (int) $term->term_id;
		$index['leaves'][ bigspy_category_norm( $term->name ) ][] = (int) $term->term_id;
	}
	return $index;
}

function bigspy_category_has_used_child( $term_id, array $by_id ) {
	foreach ( $by_id as $term ) {
		if ( (int) $term->parent === $term_id && ( (int) $term->count > 0 || bigspy_category_has_used_child( (int) $term->term_id, $by_id ) ) ) {
			return true;
		}
	}
	return false;
}

/** A term and all its ancestors. */
function bigspy_category_chain( $term_id ) {
	$ids = array( (int) $term_id );
	foreach ( get_ancestors( (int) $term_id, 'product_cat', 'taxonomy' ) as $ancestor ) {
		$ids[] = (int) $ancestor;
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Maps AI category paths onto existing categories only. A path matches by its full
 * path, or by a level whose name is unique on the site (deepest level first).
 * Returns array( 'term_ids' => [...], 'paths' => [...], 'unmatched' => [...] ).
 */
function bigspy_match_existing_categories( $raw ) {
	$index = bigspy_category_index( true );
	$out   = array( 'term_ids' => array(), 'paths' => array(), 'unmatched' => array() );
	foreach ( (array) $raw as $path ) {
		if ( is_array( $path ) ) {
			$path = implode( ' > ', array_map( 'strval', $path ) );
		}
		$path  = sanitize_text_field( (string) $path );
		$parts = array_values( array_filter( array_map( 'bigspy_category_norm', preg_split( '/\s*>\s*/u', $path ) ) ) );
		if ( ! $parts ) {
			continue;
		}
		$found = $index['paths'][ implode( ' > ', $parts ) ] ?? 0;
		for ( $i = count( $parts ) - 1; ! $found && $i >= 0; $i-- ) {
			$ids = $index['leaves'][ $parts[ $i ] ] ?? array();
			if ( 1 === count( $ids ) ) {
				$found = $ids[0];
			}
		}
		if ( $found ) {
			$out['term_ids'] = array_merge( $out['term_ids'], bigspy_category_chain( $found ) );
			$out['paths'][]  = $path;
		} else {
			$out['unmatched'][] = $path;
		}
	}
	$out['term_ids'] = array_values( array_unique( $out['term_ids'] ) );
	return $out;
}

/** Category ids chosen by the editor on the form, with their ancestors. */
function bigspy_editor_category_ids_from_request() {
	$raw = wp_unslash( $_POST['category_ids'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the generator's AJAX handler checks the nonce.
	$ids = array();
	foreach ( array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) ) as $id ) {
		$term = get_term( $id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$ids = array_merge( $ids, bigspy_category_chain( $id ) );
		}
	}
	return array_values( array_unique( $ids ) );
}

/** Prompt rule for categories. */
function bigspy_category_rule_prompt( array $editor_ids ) {
	if ( $editor_ids ) {
		$names = array();
		foreach ( $editor_ids as $id ) {
			$term = get_term( $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return 'CATEGORY RULE: the editor has fixed the categories (' . implode( ', ', $names ) . '). Return them as categories and do not add others.';
	}
	if ( bigspy_categories_locked() ) {
		return 'CATEGORY RULE: choose categories ONLY from the EXISTING CATEGORIES listed above, copied exactly. Never invent a new category or a new level. If none fits, return an empty categories array.';
	}
	return '';
}

/** Category select for the Create listing form. */
function bigspy_render_category_field() {
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}
	$children = array();
	foreach ( $terms as $term ) {
		$children[ (int) $term->parent ][] = $term;
	}
	$options = '';
	$walk    = function ( $parent, $depth ) use ( &$walk, &$options, $children ) {
		foreach ( $children[ $parent ] ?? array() as $term ) {
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}
			$options .= '<option value="' . esc_attr( $term->term_id ) . '">' . esc_html( str_repeat( '— ', $depth ) . $term->name . ' (' . (int) $term->count . ')' ) . '</option>';
			$walk( (int) $term->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	?>
	<p style="margin:0">
		<label for="bigspy-category-ids"><strong>8. Category</strong></label>
		<select id="bigspy-category-ids" multiple size="6" style="width:100%"><?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?></select>
		<span class="description"><?php echo bigspy_categories_locked() ? 'Optional. Leave empty and the AI picks from categories that already have listings; it never creates new ones.' : 'Optional. Leave empty and the AI picks or creates categories.'; ?> Ctrl+click to pick several.</span>
	</p>
	<?php
}

/* ------------------------------------------------------------------ */
/* Duplicate check                                                     */
/* ------------------------------------------------------------------ */

/** Registrable-ish host of a URL: lower case, no "www.". */
function bigspy_url_host( $url ) {
	return preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) ) );
}

/** Listings (any status except trash) whose website or affiliate URL is on the same host. */
function bigspy_find_listings_by_host( $host, $exclude_id = 0 ) {
	global $wpdb;
	$host = bigspy_url_host( 'https://' . preg_replace( '#^https?://#', '', (string) $host ) );
	if ( '' === $host ) {
		return array();
	}
	$keys = array( '_bigspy_tool_url', '_bigspy_home_url', '_bigspy_affiliate_url', '_product_url', 'official_url', 'homepage_url' );
	$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
	$like = '%' . $wpdb->esc_like( '//' . $host ) . '%';
	$like_www = '%' . $wpdb->esc_like( '//www.' . $host ) . '%';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in holds placeholders only.
	$sql = $wpdb->prepare( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = 'product' AND p.post_status NOT IN ('trash','auto-draft') AND m.meta_key IN ($in) AND (m.meta_value LIKE %s OR m.meta_value LIKE %s) AND p.ID <> %d LIMIT 5", array_merge( $keys, array( $like, $like_www, (int) $exclude_id ) ) );
	$out = array();
	foreach ( (array) $wpdb->get_col( $sql ) as $id ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$out[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ), 'status' => get_post_status( $id ), 'edit_url' => get_edit_post_link( $id, '' ) );
	}
	return $out;
}

add_action( 'wp_ajax_bigspy_check_listing_duplicate', function () {
	check_ajax_referer( 'bigspy_ai_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}
	$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
	wp_send_json_success( array( 'matches' => bigspy_find_listings_by_host( bigspy_url_host( $url ), absint( $_POST['post_id'] ?? 0 ) ) ) );
} );

/* ------------------------------------------------------------------ */
/* Listing facts and "last checked"                                    */
/* ------------------------------------------------------------------ */

/** Fixed facts every listing carries. */
function bigspy_listing_fact_fields() {
	return apply_filters( 'bigspy_listing_fact_fields', array(
		'starting_price'  => 'Starting price',
		'free_plan'       => 'Free plan',
		'free_trial'      => 'Free trial',
		'card_required'   => 'Card required to start',
		'refund_policy'   => 'Refund policy',
		'platforms'       => 'Platforms',
	) );
}

/** Prompt block asking the AI for the facts. */
function bigspy_facts_prompt() {
	return 'LISTING FACTS: also return "facts" as an object with exactly these keys: starting_price (lowest paid price with currency and period, e.g. "$19/month"), free_plan ("Yes", "No" or a short note), free_trial (length, e.g. "7 days", or "No"), card_required ("Yes" or "No" for starting the free plan or trial), refund_policy (short, e.g. "14-day money-back"), platforms (e.g. "Web, iOS, Android"). Use only facts found in the sources; leave a value empty when the sources do not state it.';
}

/** Cleans a facts array. */
function bigspy_clean_facts( $raw ) {
	$out = array();
	foreach ( bigspy_listing_fact_fields() as $key => $label ) {
		$out[ $key ] = sanitize_text_field( (string) ( ( (array) $raw )[ $key ] ?? '' ) );
	}
	return $out;
}

/** Saves facts and stamps the "last checked" date. */
function bigspy_save_facts( $post_id, array $facts ) {
	update_post_meta( $post_id, '_bigspy_facts', bigspy_clean_facts( $facts ) );
	update_post_meta( $post_id, '_bigspy_facts_checked', current_time( 'Y-m-d' ) );
}

/** Days since the facts were last checked, or -1 when never. */
function bigspy_facts_age_days( $post_id ) {
	$date = (string) get_post_meta( $post_id, '_bigspy_facts_checked', true );
	if ( '' === $date ) {
		return -1;
	}
	return (int) floor( ( current_time( 'timestamp' ) - strtotime( $date . ' 00:00:00' ) ) / DAY_IN_SECONDS );
}

function bigspy_facts_stale_days() {
	return max( 7, min( 365, absint( bsl_get( 'generator.facts_stale_days', 90 ) ) ) );
}

add_action( 'add_meta_boxes_product', function () {
	add_meta_box( 'bigspy-listing-facts', 'Listing facts & tracking', 'bigspy_render_facts_box', 'product', 'side', 'high' );
} );

function bigspy_render_facts_box( $post ) {
	wp_nonce_field( 'bigspy_facts_save', 'bigspy_facts_nonce' );
	$facts   = bigspy_clean_facts( get_post_meta( $post->ID, '_bigspy_facts', true ) );
	$checked = (string) get_post_meta( $post->ID, '_bigspy_facts_checked', true );
	$age     = bigspy_facts_age_days( $post->ID );
	$stale   = $age < 0 || $age > bigspy_facts_stale_days();
	echo '<p style="margin-top:0;color:' . ( $stale ? '#b32d2e' : '#008a20' ) . '"><strong>Last checked:</strong> ' . esc_html( $checked ? $checked : 'never' ) . '</p>';
	foreach ( bigspy_listing_fact_fields() as $key => $label ) {
		echo '<p style="margin:6px 0"><label for="bigspy-fact-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><input type="text" class="widefat" id="bigspy-fact-' . esc_attr( $key ) . '" name="bigspy_facts[' . esc_attr( $key ) . ']" value="' . esc_attr( $facts[ $key ] ) . '"></p>';
	}
	echo '<p style="margin:6px 0"><label><input type="checkbox" name="bigspy_facts_checked_now" value="1"> I checked these on the official site today</label></p>';
	echo '<p style="margin:6px 0"><label for="bigspy-pricing-url-box">Pricing page URL</label><br><input type="url" class="widefat" id="bigspy-pricing-url-box" name="bigspy_pricing_url" value="' . esc_attr( (string) get_post_meta( $post->ID, '_bigspy_pricing_url', true ) ) . '"></p>';
	echo '<p style="margin:6px 0"><button type="button" class="button" id="bigspy-recheck-facts">Re-check with AI</button> <span id="bigspy-recheck-status" class="description"></span></p>';
	echo '<p class="description" style="margin:0 0 10px">Reads the pricing page (or the website) and fills the fields above. Review, then update the listing.</p>';
	echo '<hr><p style="margin:6px 0"><label for="bigspy-subid-param">SubID parameter</label><br><input type="text" class="widefat" id="bigspy-subid-param" name="bigspy_subid_param" placeholder="e.g. subId1, sub_id, aff_sub" value="' . esc_attr( (string) get_post_meta( $post->ID, '_bigspy_subid_param', true ) ) . '"></p>';
	echo '<p class="description" style="margin:0">When set, clicks to this product from any page of the site get <code>param=page-slug</code> added, so the affiliate network reports which page made the sale. Use the name your network documents.</p>';
	?>
	<script>
	( function () {
		var btn = document.getElementById( 'bigspy-recheck-facts' ), out = document.getElementById( 'bigspy-recheck-status' );
		if ( ! btn ) return;
		btn.addEventListener( 'click', function () {
			var fd = new FormData();
			fd.append( 'action', 'bigspy_recheck_facts' );
			fd.append( 'nonce', <?php echo wp_json_encode( wp_create_nonce( 'bigspy_ai_nonce' ) ); ?> );
			fd.append( 'post_id', <?php echo (int) $post->ID; ?> );
			fd.append( 'pricing_url', document.getElementById( 'bigspy-pricing-url-box' ).value );
			btn.disabled = true; out.textContent = 'Reading the page and asking the AI…';
			fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( d ) {
				if ( ! d.success ) { out.textContent = String( d.data || 'Error' ); return; }
				Object.keys( d.data.facts ).forEach( function ( k ) { var el = document.getElementById( 'bigspy-fact-' + k ); if ( el && d.data.facts[ k ] ) el.value = d.data.facts[ k ]; } );
				out.textContent = 'Filled from ' + d.data.source + '. Review the values, tick "I checked", then update.';
			} ).catch( function ( e ) { out.textContent = e.message; } ).finally( function () { btn.disabled = false; } );
		} );
	} )();
	</script>
	<?php
}

add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['bigspy_facts_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bigspy_facts_nonce'] ) ), 'bigspy_facts_save' ) ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$facts = bigspy_clean_facts( wp_unslash( $_POST['bigspy_facts'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned field by field.
	update_post_meta( $post_id, '_bigspy_facts', $facts );
	if ( ! empty( $_POST['bigspy_facts_checked_now'] ) ) {
		update_post_meta( $post_id, '_bigspy_facts_checked', current_time( 'Y-m-d' ) );
	}
	update_post_meta( $post_id, '_bigspy_pricing_url', esc_url_raw( wp_unslash( $_POST['bigspy_pricing_url'] ?? '' ) ) );
	update_post_meta( $post_id, '_bigspy_subid_param', sanitize_key( wp_unslash( $_POST['bigspy_subid_param'] ?? '' ) ) );
	delete_transient( 'bigspy_outbound_map' );
}, 20 );

add_action( 'wp_ajax_bigspy_recheck_facts', function () {
	check_ajax_referer( 'bigspy_ai_nonce', 'nonce' );
	$post_id = absint( $_POST['post_id'] ?? 0 );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'Permission denied.' );
	}
	$url = esc_url_raw( wp_unslash( $_POST['pricing_url'] ?? '' ) );
	$url = $url ?: (string) get_post_meta( $post_id, '_bigspy_pricing_url', true );
	$url = $url ?: (string) get_post_meta( $post_id, '_bigspy_tool_url', true );
	$url = $url ?: (string) get_post_meta( $post_id, '_bigspy_home_url', true );
	if ( ! $url ) {
		wp_send_json_error( 'Add a pricing page URL first.' );
	}
	$text = bigspy_fetch_page_text( $url );
	if ( '' === $text ) {
		wp_send_json_error( 'The page could not be read (blocked or empty). Check the values by hand on ' . $url );
	}
	$model = (string) get_post_meta( $post_id, '_bigspy_ai_model', true );
	if ( ! $model || ! isset( bigspy_models()[ $model ] ) ) {
		$model = (string) array_key_first( array_filter( bigspy_models(), function ( $m ) { return ! empty( $m['verified'] ); } ) );
	}
	$prompt = bigspy_facts_prompt() . "\nReturn ONLY JSON: {\"facts\":{...}}.\n\nPRODUCT: " . get_the_title( $post_id ) . "\nPAGE (" . $url . "):\n" . $text;
	$reply  = bigspy_call_ai( array( array( 'role' => 'user', 'content' => $prompt ) ), $model );
	if ( is_wp_error( $reply ) ) {
		wp_send_json_error( $reply->get_error_message() );
	}
	$data = bigspy_parse_json( $reply );
	if ( is_wp_error( $data ) || ! is_array( $data ) ) {
		wp_send_json_error( 'The AI did not return the facts. Try again.' );
	}
	wp_send_json_success( array( 'facts' => bigspy_clean_facts( $data['facts'] ?? $data ), 'source' => $url ) );
} );

/* Products list: "Facts checked" column, red when older than the limit or never checked. */
add_filter( 'manage_edit-product_columns', function ( $columns ) {
	$columns['bigspy_checked'] = 'Facts checked';
	return $columns;
}, 30 );
add_action( 'manage_product_posts_custom_column', function ( $column, $post_id ) {
	if ( 'bigspy_checked' !== $column ) {
		return;
	}
	$age  = bigspy_facts_age_days( $post_id );
	$date = (string) get_post_meta( $post_id, '_bigspy_facts_checked', true );
	$late = $age < 0 || $age > bigspy_facts_stale_days();
	echo '<span style="color:' . ( $late ? '#b32d2e' : '#008a20' ) . '">' . esc_html( $date ? $date : 'never' ) . '</span>';
}, 10, 2 );

/* ------------------------------------------------------------------ */
/* Outbound affiliate clicks                                           */
/* ------------------------------------------------------------------ */

function bigspy_outbound_table() {
	global $wpdb;
	return $wpdb->prefix . 'bigspy_outbound_clicks';
}

function bigspy_outbound_maybe_install() {
	if ( '1' === get_option( 'bigspy_outbound_schema', '' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = bigspy_outbound_table();
	dbDelta( "CREATE TABLE {$table} (
		page_id bigint(20) unsigned NOT NULL,
		listing_id bigint(20) unsigned NOT NULL,
		click_date date NOT NULL,
		click_count bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (page_id,listing_id,click_date),
		KEY click_date (click_date)
	) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'bigspy_outbound_schema', '1', false );
}
add_action( 'plugins_loaded', 'bigspy_outbound_maybe_install', 21 );

/** host => array( id, param ) for every published listing with a website or affiliate URL. */
function bigspy_outbound_map() {
	$map = get_transient( 'bigspy_outbound_map' );
	if ( is_array( $map ) ) {
		return $map;
	}
	$map = array();
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1000, 'fields' => 'ids', 'no_found_rows' => true ) );
	foreach ( $ids as $id ) {
		$param = (string) get_post_meta( $id, '_bigspy_subid_param', true );
		foreach ( array( '_bigspy_affiliate_url', '_bigspy_tool_url', '_bigspy_home_url', '_product_url' ) as $key ) {
			$host = bigspy_url_host( get_post_meta( $id, $key, true ) );
			if ( '' !== $host && ! isset( $map[ $host ] ) && bigspy_url_host( home_url( '/' ) ) !== $host ) {
				$map[ $host ] = array( (int) $id, $param );
			}
		}
	}
	set_transient( 'bigspy_outbound_map', $map, DAY_IN_SECONDS );
	return $map;
}
add_action( 'save_post_product', function () {
	delete_transient( 'bigspy_outbound_map' );
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( array( 'post', 'product', 'page' ) ) ) {
		return;
	}
	$map = bigspy_outbound_map();
	if ( ! $map ) {
		return;
	}
	wp_register_script( 'bigspy-outbound', '', array(), BSL_VERSION, true );
	wp_enqueue_script( 'bigspy-outbound' );
	$cfg = array(
		'ajax' => admin_url( 'admin-ajax.php' ),
		'page' => (int) get_queried_object_id(),
		'slug' => substr( (string) get_post_field( 'post_name', get_queried_object_id() ), 0, 50 ),
		'map'  => $map,
	);
	wp_add_inline_script( 'bigspy-outbound', 'window.BIGSPY_OUTBOUND=' . wp_json_encode( $cfg ) . ';(function(c){function h(u){try{return new URL(u,location.href).hostname.toLowerCase().replace(/^www\\./,"")}catch(e){return""}}function f(host){var p=host.split(".");while(p.length>1){var k=p.join(".");if(c.map[k])return c.map[k];p.shift()}return null}function hit(a){var m=f(h(a.href));if(!m)return;if(m[1]&&c.slug){try{var u=new URL(a.href,location.href);if(!u.searchParams.has(m[1])){u.searchParams.set(m[1],c.slug);a.href=u.toString()}}catch(e){}}if(a.dataset.bigspyCounted)return;a.dataset.bigspyCounted="1";var b=new URLSearchParams({action:"bigspy_outbound_click",page_id:String(c.page),listing_id:String(m[0])});if(navigator.sendBeacon){navigator.sendBeacon(c.ajax,b)}else{fetch(c.ajax,{method:"POST",body:b,keepalive:true}).catch(function(){})}}document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href]");if(a)hit(a)},true);document.addEventListener("auxclick",function(e){var a=e.target.closest&&e.target.closest("a[href]");if(a)hit(a)},true)})(window.BIGSPY_OUTBOUND);' );
} );

/* Public click counter: no nonce (cached pages would make it stale); bots and repeats are dropped. */
function bigspy_outbound_click() {
	$page    = absint( $_POST['page_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public counter, see above.
	$listing = absint( $_POST['listing_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$agent   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
	if ( ! $page || ! $listing || 'product' !== get_post_type( $listing ) || 'publish' !== get_post_status( $page ) || ( function_exists( 'bigspy_popular_is_bot' ) && bigspy_popular_is_bot( $agent ) ) ) {
		wp_die( '', '', array( 'response' => 204 ) );
	}
	$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key = 'bsl_oc_' . md5( $ip . '|' . $agent . '|' . $page . '|' . $listing );
	if ( get_transient( $key ) ) {
		wp_die( '', '', array( 'response' => 204 ) );
	}
	set_transient( $key, 1, MINUTE_IN_SECONDS );
	global $wpdb;
	$table = bigspy_outbound_table();
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (page_id, listing_id, click_date, click_count) VALUES (%d, %d, %s, 1) ON DUPLICATE KEY UPDATE click_count = click_count + 1", $page, $listing, current_time( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
	wp_die( '', '', array( 'response' => 204 ) );
}
add_action( 'wp_ajax_bigspy_outbound_click', 'bigspy_outbound_click' );
add_action( 'wp_ajax_nopriv_bigspy_outbound_click', 'bigspy_outbound_click' );

/** Report on the suite settings page: affiliate clicks by page and by article type, last 30 days. */
add_action( 'bsl_settings_top', function () {
	global $wpdb;
	$table = bigspy_outbound_table();
	$from  = wp_date( 'Y-m-d', current_time( 'timestamp' ) - 29 * DAY_IN_SECONDS );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT page_id, SUM(click_count) clicks FROM {$table} WHERE click_date >= %s GROUP BY page_id ORDER BY clicks DESC LIMIT 200", $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
	echo '<div style="margin:16px 0;padding:12px 16px;background:#fff;border:1px solid #dcdcde;border-radius:6px"><h2 style="margin-top:0">Affiliate clicks, last 30 days</h2>';
	if ( ! $rows ) {
		echo '<p>No clicks recorded yet. Clicks are counted from any page to a listed product\'s website.</p></div>';
		return;
	}
	$routes = function_exists( 'bigspy_post_routes' ) ? bigspy_post_routes() : array();
	$by     = array();
	echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th>Page</th><th>Type</th><th>Clicks</th></tr></thead><tbody>';
	foreach ( array_slice( $rows, 0, 15 ) as $row ) {
		echo '<tr><td><a href="' . esc_url( get_permalink( $row->page_id ) ) . '">' . esc_html( get_the_title( $row->page_id ) ) . '</a></td><td>' . esc_html( bigspy_outbound_page_type( (int) $row->page_id, $routes ) ) . '</td><td>' . esc_html( number_format_i18n( $row->clicks ) ) . '</td></tr>';
	}
	foreach ( $rows as $row ) {
		$type        = bigspy_outbound_page_type( (int) $row->page_id, $routes );
		$by[ $type ] = ( $by[ $type ] ?? 0 ) + (int) $row->clicks;
	}
	echo '</tbody></table><p style="margin-bottom:0"><strong>By type:</strong> ';
	arsort( $by );
	$parts = array();
	foreach ( $by as $type => $clicks ) {
		$parts[] = esc_html( $type . ' ' . number_format_i18n( $clicks ) );
	}
	echo implode( ' · ', $parts ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
}, 20 );

function bigspy_outbound_page_type( $page_id, array $routes ) {
	$type = get_post_type( $page_id );
	if ( 'product' === $type ) {
		return 'Listing';
	}
	if ( 'post' === $type ) {
		$route = (string) get_post_meta( $page_id, '_bigspy_pw_route', true );
		return isset( $routes[ $route ] ) ? 'Post: ' . $routes[ $route ]['label'] : 'Post';
	}
	return ucfirst( (string) $type );
}
