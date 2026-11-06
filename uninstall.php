<?php
/**
 * Runs when the plugin is deleted. Data is kept unless "Delete all suite data" is on,
 * because the old plugins' data (shared table and meta names) lives on in the suite.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$bsl_settings = get_option( 'bsl_settings', array() );
if ( empty( $bsl_settings['delete_data'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'bsi_sites', 'bsi_checks', 'bigspy_listing_clicks_daily' ) as $bsl_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $bsl_table ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names.
}
foreach ( array( '_bsi_domain', '_bsi_resolved', '_bsi_manual' ) as $bsl_meta ) {
	delete_post_meta_by_key( $bsl_meta );
}
foreach ( array( 'bsl_settings', 'bsl_migrated', 'bsl_insights_started', 'bsi_db_version', 'bsi_purged_forbidden', 'bigspy_popular_schema_version', 'bigspy_popular_scores_updated_at', 'bigspy_internal_link_history' ) as $bsl_option ) {
	delete_option( $bsl_option );
}
delete_transient( 'bsi_rdap_bootstrap' );
delete_transient( 'bsl_github_release' );
