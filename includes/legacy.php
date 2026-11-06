<?php
/** The three plugins this suite replaces. */
defined( 'ABSPATH' ) || exit;

/** Module id => plugin name and the constant the old plugin defines when loaded. */
function bsl_legacy_plugins() {
	return array(
		'generator' => array( 'name' => 'BIGSPY AI LIST Generator', 'constant' => 'BIGSPY_AI_VERSION' ),
		'writer'    => array( 'name' => 'BIGSPY AI Post Writer', 'constant' => 'BIGSPY_POST_WRITER_VERSION' ),
		'insights'  => array( 'name' => 'BigSpy Site Insights', 'constant' => 'BSI_VERSION' ),
	);
}

/** Active plugin files of the old plugins, found by name so any folder name works. */
function bsl_legacy_active_files() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$names = wp_list_pluck( bsl_legacy_plugins(), 'name' );
	$out   = array();
	foreach ( get_plugins() as $file => $data ) {
		$id = array_search( $data['Name'] ?? '', $names, true );
		if ( false !== $id && is_plugin_active( $file ) ) {
			$out[ $id ] = $file;
		}
	}
	return $out;
}

function bsl_legacy_deactivate() {
	$files = bsl_legacy_active_files();
	if ( $files ) {
		deactivate_plugins( array_values( $files ), true );
		set_transient( 'bsl_legacy_deactivated', array_keys( $files ), MINUTE_IN_SECONDS * 10 );
	}
}

/** True when an old plugin's code is loaded in this request, so its module must not load. */
function bsl_legacy_loaded( $id ) {
	$plugins = bsl_legacy_plugins();
	return isset( $plugins[ $id ] ) && defined( $plugins[ $id ]['constant'] );
}

// An old plugin activated again while the suite is active: turn it off on the next admin page.
add_action( 'admin_init', function () {
	if ( current_user_can( 'activate_plugins' ) && bsl_legacy_active_files() ) {
		bsl_legacy_deactivate();
	}
} );

add_action( 'admin_notices', function () {
	$ids = get_transient( 'bsl_legacy_deactivated' );
	if ( ! $ids || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_transient( 'bsl_legacy_deactivated' );
	$plugins = bsl_legacy_plugins();
	$names   = array();
	foreach ( (array) $ids as $id ) {
		if ( isset( $plugins[ $id ] ) ) {
			$names[] = $plugins[ $id ]['name'];
		}
	}
	echo '<div class="notice notice-info is-dismissible"><p>';
	echo esc_html( sprintf(
		/* translators: %s: plugin names. */
		__( 'BIGSPY AI List Suite replaces and has turned off: %s. Their settings were copied.', 'bigspy-ai-list-suite' ),
		implode( ', ', $names )
	) );
	if ( in_array( 'insights', (array) $ids, true ) ) {
		echo ' <strong>' . esc_html__( 'Do not use Delete on BigSpy Site Insights: its uninstall removes the website data the suite keeps using. Leave it deactivated, or remove its folder by FTP.', 'bigspy-ai-list-suite' ) . '</strong>';
	}
	echo '</p></div>';
} );
