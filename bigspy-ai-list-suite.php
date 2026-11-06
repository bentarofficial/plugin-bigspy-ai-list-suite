<?php
/**
 * Plugin Name:       BIGSPY AI List Suite
 * Plugin URI:        https://github.com/bentarofficial/plugin-bigspy-ai-list-suite
 * Description:       AI listing generator, post writer and site insights for directory websites, in one plugin with modules you can turn on or off.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Bentar
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bigspy-ai-list-suite
 * Update URI:        https://github.com/bentarofficial/plugin-bigspy-ai-list-suite
 */

defined( 'ABSPATH' ) || exit;

define( 'BSL_VERSION', '0.1.0' );
define( 'BSL_FILE', __FILE__ );
define( 'BSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'BSL_URL', plugin_dir_url( __FILE__ ) );
define( 'BSL_PAGE', 'bigspy-ai-list-suite' );

require_once BSL_DIR . 'includes/settings.php';
require_once BSL_DIR . 'includes/connection.php';
require_once BSL_DIR . 'includes/legacy.php';
require_once BSL_DIR . 'includes/migrate.php';
require_once BSL_DIR . 'includes/modules.php';
require_once BSL_DIR . 'includes/updater.php';
if ( is_admin() ) {
	require_once BSL_DIR . 'includes/admin.php';
}

register_activation_hook( __FILE__, 'bsl_activate' );
register_deactivation_hook( __FILE__, 'bsl_deactivate' );

/** Turns off the three plugins this one replaces and copies their settings. */
function bsl_activate() {
	bsl_legacy_deactivate();
	bsl_migrate();
}

/** Removes every module schedule; data and settings stay. */
function bsl_deactivate() {
	foreach ( array_keys( bsl_module_ids() ) as $id ) {
		bsl_module_clear_cron( $id );
	}
}
