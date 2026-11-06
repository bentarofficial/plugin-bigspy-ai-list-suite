<?php
/** Updates from GitHub Releases through the plugin's Update URI (hostname github.com). */
defined( 'ABSPATH' ) || exit;

const BSL_UPDATE_REPO  = 'bentarofficial/plugin-bigspy-ai-list-suite';
const BSL_UPDATE_SLUG  = 'bigspy-ai-list-suite';
const BSL_UPDATE_CACHE = 'bsl_github_release';

/** WordPress's "Check again" on Updates also bypasses our cache. */
add_action( 'admin_init', function () {
	global $pagenow;
	if ( 'update-core.php' === $pagenow && ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress core's read-only Check again link.
		delete_transient( BSL_UPDATE_CACHE );
	}
} );

function bsl_update_release() {
	$cached = get_transient( BSL_UPDATE_CACHE );
	if ( false !== $cached ) {
		return $cached;
	}
	$response = wp_remote_get(
		'https://api.github.com/repos/' . BSL_UPDATE_REPO . '/releases/latest',
		array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'BIGSPY-List-Suite-Updater/' . BSL_VERSION ) )
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		set_transient( BSL_UPDATE_CACHE, array(), HOUR_IN_SECONDS );
		return array();
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) || empty( $data['tag_name'] ) || ! preg_match( '/^v?(\d+\.\d+\.\d+)$/', $data['tag_name'], $match ) ) {
		set_transient( BSL_UPDATE_CACHE, array(), HOUR_IN_SECONDS );
		return array();
	}
	$version  = $match[1];
	$filename = BSL_UPDATE_SLUG . '-' . $version . '.zip';
	$prefix   = 'https://github.com/' . BSL_UPDATE_REPO . '/releases/download/' . rawurlencode( $data['tag_name'] ) . '/';
	$package  = '';
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === $filename && ( $asset['browser_download_url'] ?? '' ) === $prefix . $filename ) {
			$package = $asset['browser_download_url'];
			break;
		}
	}
	if ( ! $package ) {
		set_transient( BSL_UPDATE_CACHE, array(), HOUR_IN_SECONDS );
		return array();
	}
	$release = array( 'version' => $version, 'package' => $package, 'url' => esc_url_raw( $data['html_url'] ?? '' ), 'body' => (string) ( $data['body'] ?? '' ) );
	set_transient( BSL_UPDATE_CACHE, $release, 12 * HOUR_IN_SECONDS );
	return $release;
}

add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( BSL_FILE ) !== $plugin_file ) {
		return $update;
	}
	$release = bsl_update_release();
	if ( ! $release ) {
		return $update;
	}
	return array(
		'slug'    => BSL_UPDATE_SLUG,
		'version' => $release['version'],
		'url'     => $release['url'],
		'package' => $release['package'],
	);
}, 10, 3 );

add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || BSL_UPDATE_SLUG !== $args->slug ) {
		return $result;
	}
	$release = bsl_update_release();
	if ( ! $release ) {
		return $result;
	}
	return (object) array(
		'name'          => 'BIGSPY AI List Suite',
		'slug'          => BSL_UPDATE_SLUG,
		'version'       => $release['version'],
		'author'        => 'Bentar',
		'homepage'      => $release['url'],
		'download_link' => $release['package'],
		'sections'      => array( 'changelog' => wp_kses_post( wpautop( esc_html( $release['body'] ) ) ) ),
	);
}, 10, 3 );

/** Keeps the folder name when a package unpacks under another name. */
add_filter( 'upgrader_source_selection', function ( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['plugin'] ) || plugin_basename( BSL_FILE ) !== $hook_extra['plugin'] || ! is_dir( $source ) ) {
		return $source;
	}
	$target = trailingslashit( $remote_source ) . BSL_UPDATE_SLUG;
	if ( untrailingslashit( $source ) === $target ) {
		return $source;
	}
	if ( ! file_exists( trailingslashit( $source ) . 'bigspy-ai-list-suite.php' ) || file_exists( $target ) ) {
		return new WP_Error( 'bsl_update_package', __( 'Invalid BIGSPY AI List Suite update package.', 'bigspy-ai-list-suite' ) );
	}
	global $wp_filesystem;
	if ( ! $wp_filesystem || ! $wp_filesystem->move( untrailingslashit( $source ), $target ) ) {
		return new WP_Error( 'bsl_update_move', __( 'Could not prepare the BIGSPY AI List Suite update package.', 'bigspy-ai-list-suite' ) );
	}
	return trailingslashit( $target );
}, 10, 4 );
