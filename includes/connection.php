<?php
/**
 * One AI connection shared with the theme's AI Designer.
 *
 * With BIGSPY AI Theme WP and a saved AI Designer key, the suite uses that key and endpoint,
 * so the key is entered once. With another theme it uses its own key.
 */
defined( 'ABSPATH' ) || exit;

function bsl_theme_connection_available() {
	return function_exists( 'bst_aid_key' ) && '' !== (string) bst_aid_key();
}

/** 'theme', 'own' or '' when no key is set anywhere. */
function bsl_ai_source() {
	if ( bsl_theme_connection_available() ) {
		return 'theme';
	}
	return '' !== bsl_own_key() ? 'own' : '';
}

function bsl_own_key() {
	return trim( (string) bsl_get( 'api_key', '' ) );
}

/** The API key. Never printed or sent to the browser. */
function bsl_ai_key() {
	return 'theme' === bsl_ai_source() ? (string) bst_aid_key() : bsl_own_key();
}

/** Chat completions endpoint. */
function bsl_ai_url() {
	if ( 'theme' === bsl_ai_source() ) {
		$base = function_exists( 'bst_aid_api_base' ) ? bst_aid_api_base() : 'https://kymaapi.com/v1';
		return untrailingslashit( $base ) . '/chat/completions';
	}
	return trim( (string) bsl_get( 'api_url', '' ) );
}

/** Masked form for display, e.g. sk-…3f9a. */
function bsl_mask_key( $key ) {
	$key = (string) $key;
	if ( '' === $key ) {
		return '';
	}
	return strlen( $key ) > 8 ? substr( $key, 0, 3 ) . '…' . substr( $key, -4 ) : '••••';
}
