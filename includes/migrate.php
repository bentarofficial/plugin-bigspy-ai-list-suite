<?php
/**
 * Copies settings from the old plugins into `bsl_settings` once.
 *
 * Old options are kept so the old plugins still work if the suite is removed.
 * Options that hold data rather than settings (bigspy_internal_link_history,
 * bigspy_popular_*_version/updated_at, bsi_db_version, bsi_purged_forbidden) keep their names.
 */
defined( 'ABSPATH' ) || exit;

const BSL_MIGRATED = 'bsl_migrated';

function bsl_migrate() {
	if ( get_option( BSL_MIGRATED ) ) {
		return;
	}
	$saved = get_option( BSL_OPTION, array() );
	$s     = is_array( $saved ) ? $saved : array();

	$top = array(
		'bigspy_api_url'        => 'api_url',
		'bigspy_api_key'        => 'api_key',
		'bigspy_serper_api_key' => 'serper_key',
	);
	foreach ( $top as $old => $new ) {
		$value = get_option( $old, null );
		if ( null !== $value && '' !== trim( (string) $value ) && ! isset( $s[ $new ] ) ) {
			$s[ $new ] = trim( (string) $value );
		}
	}

	$generator = array(
		'bigspy_system_prompt'          => 'system_prompt',
		'bigspy_web_research_enabled'   => 'web_research_enabled',
		'bigspy_web_research_results'   => 'web_research_results',
		'bigspy_max_dynamic_labels'     => 'max_dynamic_labels',
		'bigspy_popular_window_days'    => 'popular_window_days',
		'bigspy_popular_retention_days' => 'popular_retention',
	);
	$g = isset( $s['generator'] ) ? (array) $s['generator'] : array();
	foreach ( $generator as $old => $new ) {
		$value = get_option( $old, null );
		if ( null !== $value && ! isset( $g[ $new ] ) ) {
			$g[ $new ] = 'system_prompt' === $new ? (string) $value : absint( $value );
		}
	}
	if ( $g ) {
		$s['generator'] = $g;
	}

	$bsi = get_option( 'bsi_settings', null );
	if ( is_array( $bsi ) && ! isset( $s['insights'] ) ) {
		$s['insights'] = array_intersect_key( $bsi, bsl_settings_defaults()['insights'] );
	}

	bsl_update_settings( $s );
	update_option( BSL_MIGRATED, BSL_VERSION, false );
}

// Also runs for sites that update by replacing files without reactivating.
add_action( 'admin_init', 'bsl_migrate', 5 );
