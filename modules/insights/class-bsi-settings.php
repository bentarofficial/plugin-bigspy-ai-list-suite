<?php
defined( 'ABSPATH' ) || exit;

/**
 * Module options, stored under `insights` in the suite's `bsl_settings` option.
 * OPTION stays for the one-time cleanup in BSI_Store::purge_forbidden().
 */
class BSI_Settings {
	const OPTION = 'bsi_settings';

	public static function defaults() {
		return array(
			'post_types'        => 'product',
			// Meta keys that may hold the "Visit Website" link, checked in order.
			'url_meta_keys'     => '_product_url, _bigspy_website_url, _website_url, website_url, _website, website, _url',
			'auto_insert'       => 1,
			'refresh_days'      => 7,
			'monitor_minutes'   => 60,
			'google_api_key'    => '',
			'openpagerank_key'  => '',
		);
	}

	public static function all() {
		$saved = bsl_get( 'insights', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function list_value( $key ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) self::get( $key ) ) ) ) );
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = self::defaults();

		$clean['post_types']       = sanitize_text_field( isset( $input['post_types'] ) ? $input['post_types'] : $clean['post_types'] );
		$clean['url_meta_keys']    = sanitize_text_field( isset( $input['url_meta_keys'] ) ? $input['url_meta_keys'] : $clean['url_meta_keys'] );
		$clean['auto_insert']      = empty( $input['auto_insert'] ) ? 0 : 1;
		$clean['refresh_days']     = max( 1, min( 60, absint( isset( $input['refresh_days'] ) ? $input['refresh_days'] : 7 ) ) );
		$clean['monitor_minutes']  = max( 15, min( 1440, absint( isset( $input['monitor_minutes'] ) ? $input['monitor_minutes'] : 60 ) ) );
		$clean['google_api_key']   = sanitize_text_field( isset( $input['google_api_key'] ) ? $input['google_api_key'] : '' );
		$clean['openpagerank_key'] = sanitize_text_field( isset( $input['openpagerank_key'] ) ? $input['openpagerank_key'] : '' );

		return $clean;
	}
}
