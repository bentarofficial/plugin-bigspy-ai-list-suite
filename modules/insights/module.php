<?php
/**
 * Site Insights module (from BigSpy Site Insights 0.3.0).
 *
 * Class, table, meta, shortcode and cron names are kept, so data collected by the old plugin is used as is.
 */
defined( 'ABSPATH' ) || exit;

define( 'BSI_VERSION', '0.3.0' );
define( 'BSI_DB_VERSION', '1' );
define( 'BSI_FILE', BSL_FILE );
define( 'BSI_DIR', __DIR__ . '/' );
define( 'BSI_URL', BSL_URL . 'modules/insights/' );

foreach ( array( 'settings', 'store', 'domain', 'estimator', 'collector', 'monitor', 'charts', 'render', 'admin', 'columns', 'cron', 'traffic' ) as $bsl_file ) {
	require_once __DIR__ . '/class-bsi-' . $bsl_file . '.php';
}
unset( $bsl_file );

BSI_Store::maybe_upgrade();
BSI_Store::purge_forbidden();
BSI_Cron::init();
BSI_Traffic::init();
if ( ! get_option( 'bsi_traffic_synced_at' ) ) {
	BSI_Traffic::schedule_sync();
}
BSI_Render::init();
if ( is_admin() ) {
	BSI_Admin::init();
	BSI_Columns::init();
}

// First run on this site (no data from the old plugin): find the website of every listing.
if ( ! get_option( 'bsl_insights_started' ) ) {
	update_option( 'bsl_insights_started', 1, false );
	if ( ! BSI_Store::counts()['sites'] ) {
		BSI_Cron::start_queue_all();
	}
}

add_action( 'bsl_settings_sections', function () {
	BSI_Admin::page();
} );

add_filter( 'bsl_sanitize_settings', function ( $s, $in, $old ) {
	if ( ! isset( $in['insights'] ) || ! is_array( $in['insights'] ) ) {
		return $s;
	}
	$clean = BSI_Settings::sanitize( $in['insights'] );
	// Empty key fields keep the saved key; the page never prints keys.
	foreach ( array( 'google_api_key', 'openpagerank_key' ) as $key ) {
		if ( '' === $clean[ $key ] && empty( $in['insights'][ $key . '_clear' ] ) ) {
			$clean[ $key ] = (string) ( $old['insights'][ $key ] ?? '' );
		}
	}
	$s['insights'] = $clean;
	return $s;
}, 10, 3 );

/** Lists the theme mode as off only when BIGSPY AI Theme WP is active and its List mode is off. */
function bsl_listing_mode_off() {
	return function_exists( 'bst_feature_on' ) && ! bst_feature_on( 'listing' );
}

add_action( 'init', function () {
	wp_register_script(
		'bsl-site-insights-block',
		BSI_URL . 'block.js',
		array_merge(
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			// The theme's lock filter must be added before this block registers.
			wp_script_is( 'bst-blocks-listing', 'registered' ) ? array( 'bst-blocks-listing' ) : array()
		),
		BSL_VERSION,
		true
	);
	register_block_type(
		'bsl/site-insights',
		array(
			'api_version'     => 3,
			'title'           => __( 'Site Insights', 'bigspy-ai-list-suite' ),
			'category'        => 'bigspy-list',
			'editor_script'   => 'bsl-site-insights-block',
			'style'           => 'bigspy-site-insights',
			'attributes'      => array( 'postId' => array( 'type' => 'number', 'default' => 0 ) ),
			'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
			'render_callback' => function ( $attributes ) {
				if ( bsl_listing_mode_off() ) {
					return '';
				}
				$post_id = absint( $attributes['postId'] ?? 0 );
				return BSI_Render::shortcode( array( 'post_id' => $post_id ? $post_id : get_the_ID() ) );
			},
		)
	);
	if ( ! wp_style_is( 'bigspy-site-insights', 'registered' ) ) {
		BSI_Render::register_assets();
	}
}, 30 );

// Other themes have no BIGSPY AI List category; add it so the block is easy to find.
add_filter( 'block_categories_all', function ( $categories ) {
	foreach ( $categories as $category ) {
		if ( 'bigspy-list' === ( $category['slug'] ?? '' ) ) {
			return $categories;
		}
	}
	$categories[] = array( 'slug' => 'bigspy-list', 'title' => __( 'BIGSPY AI List', 'bigspy-ai-list-suite' ), 'icon' => null );
	return $categories;
}, 30 );
