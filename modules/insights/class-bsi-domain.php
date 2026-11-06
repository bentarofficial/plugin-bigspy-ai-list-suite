<?php
defined( 'ABSPATH' ) || exit;

/**
 * Finds the website behind a listing's "Visit Website" button.
 *
 * Post meta used:
 *   _bsi_domain   — manual override typed by an editor.
 *   _bsi_resolved — cache: array( 'src' => md5 of the visit URL, 'domain' => resolved domain ).
 */
class BSI_Domain {

	const USER_AGENT = 'Mozilla/5.0 (compatible; BIGSPY-AI-List-Suite/' . BSL_VERSION . '; +https://github.com/bentarofficial/plugin-bigspy-ai-list-suite)';

	/** Second-level labels that make a three-label registrable domain (example.co.uk). */
	private static $second_levels = array( 'co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'or', 'ne', 'go', 'gob', 'mil', 'nom', 'biz', 'info' );

	public static function visit_url( $post_id ) {
		foreach ( BSI_Settings::list_value( 'url_meta_keys' ) as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( is_string( $value ) && self::is_http_url( $value ) ) {
				return apply_filters( 'bsi_visit_url', trim( $value ), $post_id );
			}
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product && is_callable( array( $product, 'get_product_url' ) ) ) {
				$value = $product->get_product_url();
				if ( self::is_http_url( $value ) ) {
					return apply_filters( 'bsi_visit_url', $value, $post_id );
				}
			}
		}

		// Last resort: any meta key that looks like a website link.
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( ! preg_match( '/(website|site_url|visit|external|product_url|official)/i', $key ) ) {
				continue;
			}
			$value = is_array( $values ) ? reset( $values ) : $values;
			if ( is_string( $value ) && self::is_http_url( $value ) && ! preg_match( '/\.(png|jpe?g|gif|webp|svg|pdf)(\?|$)/i', $value ) ) {
				return apply_filters( 'bsi_visit_url', trim( $value ), $post_id );
			}
		}

		return apply_filters( 'bsi_visit_url', '', $post_id );
	}

	/**
	 * Domain for a listing. Without network access it answers from the cache or the
	 * link's own host; with network access it follows affiliate redirects and caches.
	 */
	public static function for_post( $post_id, $allow_network = false ) {
		$override = self::normalize( (string) get_post_meta( $post_id, '_bsi_domain', true ) );
		if ( '' !== $override ) {
			return $override;
		}

		$url = self::visit_url( $post_id );
		if ( '' === $url ) {
			return '';
		}

		$src    = md5( $url );
		$cached = get_post_meta( $post_id, '_bsi_resolved', true );
		if ( is_array( $cached ) && isset( $cached['src'], $cached['domain'] ) && $cached['src'] === $src ) {
			return $cached['domain'];
		}

		$domain = self::normalize( $url );
		if ( ! $allow_network ) {
			return $domain;
		}

		$final = self::follow_redirects( $url );
		if ( '' !== $final ) {
			$resolved = self::normalize( $final );
			if ( '' !== $resolved ) {
				$domain = $resolved;
			}
		}
		update_post_meta( $post_id, '_bsi_resolved', array( 'src' => $src, 'domain' => $domain ) );
		return $domain;
	}

	public static function is_resolved( $post_id ) {
		if ( '' !== (string) get_post_meta( $post_id, '_bsi_domain', true ) ) {
			return true;
		}
		$cached = get_post_meta( $post_id, '_bsi_resolved', true );
		$url    = self::visit_url( $post_id );
		return is_array( $cached ) && isset( $cached['src'] ) && '' !== $url && $cached['src'] === md5( $url );
	}

	/** Final URL after HTTP and meta-refresh redirects, or '' when the request fails. */
	public static function follow_redirects( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 10,
				'user-agent'          => self::USER_AGENT,
				'limit_response_size' => 65536,
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$final = $url;
		if ( isset( $response['http_response'] ) && is_object( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
			$object = $response['http_response']->get_response_object();
			if ( is_object( $object ) && ! empty( $object->url ) ) {
				$final = $object->url;
			}
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( preg_match( '/<meta[^>]+http-equiv=["\']?refresh["\']?[^>]*content=["\']?\d+\s*;\s*url=([^"\'>\s]+)/i', $body, $m ) ) {
			$next = html_entity_decode( $m[1], ENT_QUOTES );
			if ( self::is_http_url( $next ) ) {
				$final = $next;
			}
		}
		return $final;
	}

	/** "https://www.Example.co.uk/path?x" → "example.co.uk". Accepts bare hosts too. */
	public static function normalize( $value ) {
		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#', $value ) ) {
			$value = 'http://' . $value;
		}
		$host = wp_parse_url( $value, PHP_URL_HOST );
		if ( ! is_string( $host ) || false === strpos( $host, '.' ) ) {
			return '';
		}
		$host = rtrim( $host, '.' );
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
			return '';
		}
		return self::registrable( $host );
	}

	public static function registrable( $host ) {
		$labels = explode( '.', $host );
		$count  = count( $labels );
		if ( $count <= 2 ) {
			return $host;
		}
		$tld    = $labels[ $count - 1 ];
		$second = $labels[ $count - 2 ];
		$keep   = ( 2 === strlen( $tld ) && in_array( $second, self::$second_levels, true ) ) ? 3 : 2;
		return implode( '.', array_slice( $labels, -$keep ) );
	}

	public static function is_http_url( $value ) {
		return (bool) preg_match( '#^https?://[^\s/$.?\#].[^\s]*$#i', trim( (string) $value ) );
	}
}
