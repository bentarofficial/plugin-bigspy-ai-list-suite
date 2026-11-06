<?php
/** Module registry and loading. Each module lives in modules/<id>/module.php. */
defined( 'ABSPATH' ) || exit;

/** Module id => cron hooks it schedules. No translated text, so it is safe before `init`. */
function bsl_module_ids() {
	return array(
		'generator' => array( 'bigspy_popular_daily_maintenance' ),
		'writer'    => array(),
		'insights'  => array( 'bsi_refresh', 'bsi_monitor', 'bsi_daily', 'bsi_queue_all' ),
	);
}

/** Labels for the settings page. */
function bsl_modules() {
	return array(
		'generator' => array(
			'label'       => __( 'Listing Generator', 'bigspy-ai-list-suite' ),
			'description' => __( 'Create and refresh listings with AI: listing fields, SEO metadata, web research, internal links and Popular ranking.', 'bigspy-ai-list-suite' ),
		),
		'writer'    => array(
			'label'       => __( 'Post Writer', 'bigspy-ai-list-suite' ),
			'description' => __( 'Write researched blog drafts that link to your listings.', 'bigspy-ai-list-suite' ),
		),
		'insights'  => array(
			'label'       => __( 'Site Insights', 'bigspy-ai-list-suite' ),
			'description' => __( 'Uptime, SSL, technology and Core Web Vitals of the website behind each listing, plus admin columns.', 'bigspy-ai-list-suite' ),
		),
	);
}

function bsl_module_enabled( $id ) {
	return ! empty( bsl_get( 'modules.' . $id ) );
}

function bsl_module_clear_cron( $id ) {
	$modules = bsl_module_ids();
	foreach ( $modules[ $id ] ?? array() as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
}

/** Called when a module is switched off in settings. */
function bsl_module_disabled( $id ) {
	bsl_module_clear_cron( $id );
	do_action( 'bsl_module_disabled', $id );
}

/** Loads enabled modules after all plugins, skipping any whose old plugin is still loaded. */
add_action( 'plugins_loaded', function () {
	foreach ( array_keys( bsl_module_ids() ) as $id ) {
		$file = BSL_DIR . 'modules/' . $id . '/module.php';
		if ( bsl_module_enabled( $id ) && ! bsl_legacy_loaded( $id ) && is_readable( $file ) ) {
			require_once $file;
			$GLOBALS['bsl_loaded_modules'][] = $id;
		}
	}
	do_action( 'bsl_modules_loaded' );
}, 20 );
