<?php
/** One option, `bsl_settings`, holds every setting of the suite. */
defined( 'ABSPATH' ) || exit;

const BSL_OPTION = 'bsl_settings';

function bsl_settings_defaults() {
	return array(
		'modules'    => array( 'generator' => 1, 'writer' => 1, 'insights' => 1 ),
		// Own AI connection, used only when the theme has no key (see connection.php).
		'api_url'    => 'https://kymaapi.com/v1/chat/completions',
		'api_key'    => '',
		'serper_key' => '',
		// Delete tables, listing data and settings when the plugin is deleted.
		'delete_data' => 0,
		'generator'  => array(
			'system_prompt'        => '',
			'web_research_enabled' => 0,
			'web_research_results' => 5,
			'max_dynamic_labels'   => 6,
			'popular_window_days'  => 30,
			'popular_retention'    => 90,
		),
		'insights'   => array(
			'post_types'       => 'product',
			'url_meta_keys'    => '_product_url, _bigspy_website_url, _website_url, website_url, _website, website, _url',
			'auto_insert'      => 1,
			'refresh_days'     => 7,
			'monitor_minutes'  => 60,
			'google_api_key'   => '',
			'openpagerank_key' => '',
		),
	);
}

/** All settings merged over the defaults, one level deep for the groups. */
function bsl_settings() {
	$saved = get_option( BSL_OPTION, array() );
	$saved = is_array( $saved ) ? $saved : array();
	$out   = bsl_settings_defaults();
	foreach ( $out as $key => $default ) {
		if ( ! array_key_exists( $key, $saved ) ) {
			continue;
		}
		$out[ $key ] = is_array( $default ) ? array_merge( $default, (array) $saved[ $key ] ) : $saved[ $key ];
	}
	return $out;
}

/** Reads `group.key` or `key`. */
function bsl_get( $path, $fallback = null ) {
	$value = bsl_settings();
	foreach ( explode( '.', $path ) as $part ) {
		if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
			return $fallback;
		}
		$value = $value[ $part ];
	}
	return $value;
}

function bsl_update_settings( array $settings ) {
	update_option( BSL_OPTION, $settings, false );
}
