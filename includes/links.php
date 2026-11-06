<?php
/**
 * Links between the modules and with the theme's AI Designer.
 *
 * Every hook here checks that the other side is loaded, so any module can be off.
 */
defined( 'ABSPATH' ) || exit;

/* Generator → Site Insights: a new listing draft gets its website looked up right away. */
add_action( 'save_post_product', function ( $post_id, $post ) {
	if ( ! class_exists( 'BSI_Cron' ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
		return;
	}
	// Published listings are already queued by Site Insights itself.
	if ( wp_doing_ajax() && 'bigspy_generate_listing' === ( $_POST['action'] ?? '' ) && 'publish' !== $post->post_status ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the generator's AJAX handler checks the nonce.
		BSI_Cron::queue_post( $post_id );
	}
}, 30, 2 );

/* Post Writer → listings: listings named in the article description become internal links. */
function bsl_listings_named_in( $text, $limit = 8 ) {
	$text = ' ' . strtolower( wp_strip_all_tags( (string) $text ) ) . ' ';
	if ( strlen( $text ) < 4 || ! post_type_exists( 'product' ) ) {
		return array();
	}
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'orderby' => 'modified', 'no_found_rows' => true ) );
	$out = array();
	foreach ( $ids as $id ) {
		$title = strtolower( trim( wp_strip_all_tags( get_the_title( $id ) ) ) );
		// Whole-word match: "Copy" does not match "Copy.ai" or "Copywriter" and short titles do not match by chance.
		if ( strlen( $title ) >= 3 && preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $title, '/' ) . '(?![\p{L}\p{N}]|\.[\p{L}\p{N}])/u', $text ) ) {
			$out[] = array( 'name' => get_the_title( $id ), 'url' => get_permalink( $id ) );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}
	return $out;
}

add_filter( 'bigspy_pw_internal_anchors', function ( $internal, $description ) {
	$internal = (array) $internal;
	$urls     = wp_list_pluck( $internal, 'url' );
	foreach ( bsl_listings_named_in( $description ) as $row ) {
		if ( count( $internal ) >= 8 ) {
			break;
		}
		if ( ! in_array( $row['url'], $urls, true ) ) {
			$internal[] = $row;
			$urls[]     = $row['url'];
		}
	}
	return $internal;
}, 10, 2 );

/* Shared models: with the theme's connection, the AI Designer model list is offered too. */
add_filter( 'bigspy_models', function ( $models ) {
	if ( 'theme' !== bsl_ai_source() || ! function_exists( 'bst_aid_chat_models' ) ) {
		return $models;
	}
	foreach ( (array) bst_aid_chat_models() as $m ) {
		$id = (string) ( $m['id'] ?? '' );
		if ( '' !== $id && ! isset( $models[ $id ] ) ) {
			$models[ $id ] = array( 'label' => (string) ( $m['name'] ?? $id ), 'verified' => true );
		}
	}
	return $models;
} );

/* Overview at the top of the settings page. */
function bsl_overview() {
	global $wpdb;
	$out = array();
	if ( post_type_exists( 'product' ) ) {
		$counts            = wp_count_posts( 'product' );
		$out['listings']   = (int) ( $counts->publish ?? 0 );
		$out['drafts']     = (int) ( $counts->draft ?? 0 );
	}
	if ( class_exists( 'BSI_Store' ) ) {
		$checks = BSI_Store::checks_table();
		$c      = BSI_Store::counts();
		$out['sites']         = (int) $c['sites'];
		$out['sites_no_data'] = (int) $c['sites'] - (int) $c['fetched'];
		// Websites whose latest uptime check failed.
		$out['sites_down'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$checks} c JOIN (SELECT domain, MAX(checked_at) t FROM {$checks} GROUP BY domain) l ON l.domain = c.domain AND l.t = c.checked_at WHERE c.ok = 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names, no input.
	}
	if ( function_exists( 'bigspy_pw_ready' ) ) {
		$out['post_drafts'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_bigspy_pw_description' WHERE p.post_type = 'post' AND p.post_status = 'draft'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixed query.
	}
	return $out;
}

add_action( 'bsl_settings_top', function () {
	$o = bsl_overview();
	if ( ! $o ) {
		return;
	}
	$labels = array(
		'listings'      => array( __( 'Published listings', 'bigspy-ai-list-suite' ), admin_url( 'edit.php?post_type=product' ) ),
		'drafts'        => array( __( 'Listing drafts', 'bigspy-ai-list-suite' ), admin_url( 'edit.php?post_type=product&post_status=draft' ) ),
		'sites_no_data' => array( __( 'Websites without data yet', 'bigspy-ai-list-suite' ), '' ),
		'sites_down'    => array( __( 'Websites down at last check', 'bigspy-ai-list-suite' ), '' ),
		'post_drafts'   => array( __( 'AI post drafts to review', 'bigspy-ai-list-suite' ), admin_url( 'edit.php?post_status=draft' ) ),
	);
	echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">';
	foreach ( $labels as $key => $label ) {
		if ( ! isset( $o[ $key ] ) ) {
			continue;
		}
		$alert = in_array( $key, array( 'sites_down' ), true ) && $o[ $key ] > 0;
		echo '<div style="min-width:150px;padding:12px 16px;background:#fff;border:1px solid ' . ( $alert ? '#d63638' : '#dcdcde' ) . ';border-radius:6px">';
		echo '<div style="font-size:24px;font-weight:600;color:' . ( $alert ? '#d63638' : '#1d2327' ) . '">' . esc_html( number_format_i18n( $o[ $key ] ) ) . '</div>';
		echo $label[1] ? '<a href="' . esc_url( $label[1] ) . '">' . esc_html( $label[0] ) . '</a>' : '<span>' . esc_html( $label[0] ) . '</span>';
		echo '</div>';
	}
	echo '</div>';
} );
