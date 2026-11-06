<?php
/**
 * Tools for the theme's AI Designer (BIGSPY AI Theme WP 'bst_aid_tools').
 *
 * The theme blocks unknown tools; 'bst_aid_extra_tool_groups' tells it which are read-only and which
 * create content, so its approval rules apply as for its own tools.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'bst_aid_extra_tool_groups', function ( $groups ) {
	$groups['suite_overview'] = 'read';
	if ( class_exists( 'BSI_Store' ) ) {
		$groups['listing_insights'] = 'read';
	}
	if ( function_exists( 'bigspy_set_external_product_url' ) ) {
		$groups['create_listing_drafts'] = 'content';
	}
	return $groups;
} );

add_filter( 'bst_aid_tools', function ( $tools ) {
	$tools['suite_overview'] = array(
		'description' => 'BIGSPY AI List Suite overview: published listings, listing drafts, websites without data or down, AI post drafts, and which suite modules are on.',
		'parameters'  => array( 'type' => 'object', 'properties' => new stdClass() ),
		'cap'         => 'edit_posts',
		'callback'    => 'bsl_tool_overview',
	);
	if ( class_exists( 'BSI_Store' ) ) {
		$tools['listing_insights'] = array(
			'description' => 'Website insights collected for one listing (product id): domain, uptime of the last checks, SSL, technology and other data, when it was last refreshed.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ), 'required' => array( 'id' ) ),
			'cap'         => 'edit_products',
			'callback'    => 'bsl_tool_listing_insights',
		);
	}
	if ( function_exists( 'bigspy_set_external_product_url' ) ) {
		$tools['create_listing_drafts'] = array(
			'description' => 'Create up to 10 listing drafts (WooCommerce external products) from a name and the official website URL. Drafts only; the website is looked up in the background. The full AI listing is then written in AI List Suite → Create listing. Use only URLs the user gave or confirmed.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'items' => array(
						'type'     => 'array',
						'maxItems' => 10,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'name' => array( 'type' => 'string' ),
								'url'  => array( 'type' => 'string', 'description' => 'Official website, https.' ),
								'note' => array( 'type' => 'string', 'description' => 'Optional one-line description.' ),
							),
							'required'   => array( 'name', 'url' ),
						),
					),
				),
				'required'   => array( 'items' ),
			),
			'cap'         => 'edit_products',
			'callback'    => 'bsl_tool_create_listing_drafts',
		);
	}
	return $tools;
} );

function bsl_tool_overview() {
	$modules = array();
	foreach ( array_keys( bsl_module_ids() ) as $id ) {
		$modules[ $id ] = in_array( $id, $GLOBALS['bsl_loaded_modules'] ?? array(), true );
	}
	return array( 'overview' => bsl_overview(), 'modules' => $modules, 'ai_connection' => bsl_ai_source() );
}

function bsl_tool_listing_insights( $a ) {
	$id   = absint( $a['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'product' !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
		return array( 'error' => 'Listing not found.' );
	}
	$domain = BSI_Domain::for_post( $id );
	if ( '' === $domain ) {
		return array( 'id' => $id, 'domain' => '', 'message' => 'No website link found on this listing yet.' );
	}
	$site = BSI_Store::get_site( $domain );
	if ( ! $site ) {
		return array( 'id' => $id, 'domain' => $domain, 'message' => 'Website registered; data is collected in the background.' );
	}
	$data = json_decode( (string) ( $site['data'] ?? '' ), true );
	return array(
		'id'         => $id,
		'domain'     => $domain,
		'fetched_at' => $site['fetched_at'] ?? null,
		'checked_at' => $site['checked_at'] ?? null,
		'data'       => is_array( $data ) ? $data : array(),
	);
}

function bsl_tool_create_listing_drafts( $a ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return array( 'error' => 'WooCommerce is not active.' );
	}
	$out = array();
	foreach ( array_slice( (array) ( $a['items'] ?? array() ), 0, 10 ) as $item ) {
		$name = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
		$url  = esc_url_raw( (string) ( $item['url'] ?? '' ) );
		if ( '' === $name || ! function_exists( 'bigspy_validate_external_url' ) || ! bigspy_validate_external_url( $url ) ) {
			$out[] = array( 'name' => $name, 'error' => 'A name and a valid website URL are required.' );
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'product',
				'post_status'  => 'draft',
				'post_title'   => $name,
				'post_excerpt' => sanitize_text_field( (string) ( $item['note'] ?? '' ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			$out[] = array( 'name' => $name, 'error' => $id->get_error_message() );
			continue;
		}
		bigspy_set_external_product_url( $id, $url );
		if ( class_exists( 'BSI_Cron' ) ) {
			BSI_Cron::queue_post( $id );
		}
		$out[] = array( 'id' => $id, 'name' => $name, 'edit_url' => get_edit_post_link( $id, 'raw' ) );
	}
	return array(
		'created' => $out,
		'next'    => 'Open AI List Suite → Create listing to write each listing with AI, or edit the drafts.',
		'url'     => admin_url( 'admin.php?page=bsl-create-listing' ),
	);
}
