<?php
defined( 'ABSPATH' ) || exit;

/**
 * Front end: [bigspy_insights] shortcode and automatic placement after single listings.
 *
 * Shortcode attributes:
 *   post_id — listing to describe (default: current post).
 *   domain  — describe this domain instead of the listing's "Visit Website" link.
 *   title   — name shown in the heading (default: listing title or domain).
 */
class BSI_Render {

	private static $rendered = array();

	private static $countries = array(
		'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'IN' => 'India',
		'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'BR' => 'Brazil',
		'MX' => 'Mexico', 'AR' => 'Argentina', 'CN' => 'China', 'JP' => 'Japan', 'KR' => 'South Korea', 'VN' => 'Vietnam',
		'ID' => 'Indonesia', 'TH' => 'Thailand', 'PH' => 'Philippines', 'MY' => 'Malaysia', 'SG' => 'Singapore',
		'TW' => 'Taiwan', 'HK' => 'Hong Kong', 'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'TR' => 'Turkey', 'RU' => 'Russia',
		'UA' => 'Ukraine', 'PL' => 'Poland', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
		'CH' => 'Switzerland', 'AT' => 'Austria', 'BE' => 'Belgium', 'PT' => 'Portugal', 'IE' => 'Ireland', 'IL' => 'Israel',
		'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'EG' => 'Egypt', 'NG' => 'Nigeria', 'ZA' => 'South Africa',
		'KE' => 'Kenya', 'CO' => 'Colombia', 'CL' => 'Chile', 'PE' => 'Peru', 'NZ' => 'New Zealand',
	);

	public static function init() {
		add_shortcode( 'bigspy_insights', array( __CLASS__, 'shortcode' ) );
		add_action( 'bigspy_insights', array( __CLASS__, 'action' ) );
		add_filter( 'the_content', array( __CLASS__, 'auto_insert' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'bigspy-site-insights', BSI_URL . 'assets/insights.css', array(), BSI_VERSION );
	}

	/** For themes: do_action( 'bigspy_insights' ) prints the block for the current post. */
	public static function action( $post_id = 0 ) {
		echo self::shortcode( array( 'post_id' => $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in render().
	}

	public static function auto_insert( $content ) {
		if ( ! BSI_Settings::get( 'auto_insert' ) || is_admin() || ! is_singular( BSI_Cron::post_types() ) ) {
			return $content;
		}
		// Excerpts are built by running the_content and stripping tags; stay out of them.
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'wp_trim_excerpt' ) || doing_filter( 'get_the_excerpt_block' ) ) {
			return $content;
		}
		// Only for the listing being viewed (block themes render content outside the classic loop).
		if ( (int) get_the_ID() !== (int) get_queried_object_id() || has_shortcode( $content, 'bigspy_insights' ) ) {
			return $content;
		}
		return $content . self::shortcode( array() );
	}

	public static function shortcode( $atts ) {
		$atts    = shortcode_atts(
			array(
				'post_id' => 0,
				'domain'  => '',
				'title'   => '',
			),
			$atts,
			'bigspy_insights'
		);
		$post_id = absint( $atts['post_id'] );
		if ( ! $post_id && '' === $atts['domain'] ) {
			$post_id = (int) get_the_ID();
		}

		if ( '' !== $atts['domain'] ) {
			$domain = BSI_Domain::normalize( $atts['domain'] );
		} elseif ( $post_id ) {
			$domain = BSI_Domain::for_post( $post_id, false );
			if ( '' !== $domain && ! BSI_Domain::is_resolved( $post_id ) ) {
				BSI_Cron::queue_post( $post_id );
			}
		} else {
			$domain = '';
		}
		if ( '' === $domain ) {
			return '';
		}

		// Only once per listing per page, even if both the shortcode and auto placement run.
		$key = $post_id . '|' . $domain;
		if ( isset( self::$rendered[ $key ] ) ) {
			return '';
		}
		self::$rendered[ $key ] = true;

		$site = BSI_Store::get_site( $domain );
		if ( ! $site ) {
			BSI_Store::ensure_site( $domain );
		}
		$manual = $post_id ? get_post_meta( $post_id, '_bsi_manual', true ) : array();
		$name   = '' !== $atts['title'] ? $atts['title'] : ( $post_id ? get_the_title( $post_id ) : $domain );
		$visit  = $post_id ? BSI_Domain::visit_url( $post_id ) : '';

		wp_enqueue_style( 'bigspy-site-insights' );
		return self::render( $domain, $name, $site, is_array( $manual ) ? $manual : array(), $visit );
	}

	/* ------------------------------------------------------------ Rendering */

	public static function render( $domain, $name, $site, array $manual, $visit_url ) {
		// Never show data from sources without a commercial licence, even if an older version stored it.
		$data   = BSI_Store::public_data( $site && is_array( $site['data'] ) ? $site['data'] : array() );
		$status = BSI_Monitor::summary( $domain );

		$out  = '<section class="bsi" data-domain="' . esc_attr( $domain ) . '">';
		// One heading for the whole block; inner titles are labels so the article outline stays clean.
		$out .= '<h2 class="bsi-heading">' . esc_html( sprintf( '%s Website Analytics', $name ) ) . '</h2>';
		$out .= '<div class="bsi-card">';
		$out .= '<p class="bsi-title"><span class="bsi-dot-mark"></span>' . esc_html( sprintf( '%s Website Insights', $name ) ) . '</p>';

		if ( empty( $site['fetched_at'] ) && ! $status ) {
			$out .= '<p class="bsi-empty">' . esc_html( sprintf( 'We are collecting data for %s. Check back soon.', $domain ) ) . '</p>';
			return $out . '</div></section>';
		}

		$out .= self::section_overview( $data, $manual );
		$pair = self::section_geography( $data, $manual ) . self::section_sources( $manual );
		if ( '' !== $pair ) {
			$out .= '<div class="bsi-pair">' . $pair . '</div>';
		}
		$out .= self::section_vitals( $data );
		$out .= self::section_status( $status, $name, $visit_url );
		$out .= self::section_domain( $data, $domain );
		$out .= self::footer( $site, $data );

		return $out . '</div></section>';
	}

	private static function section( $title, $body, $side = '', $side_caption = '' ) {
		$html  = '<div class="bsi-section">';
		$html .= '<div class="bsi-col"><p class="bsi-label">' . esc_html( $title ) . '</p>' . $body . '</div>';
		if ( '' !== $side ) {
			$html .= '<div class="bsi-col bsi-col--chart">';
			if ( '' !== $side_caption ) {
				$html .= '<p class="bsi-caption">' . esc_html( $side_caption ) . '</p>';
			}
			$html .= $side . '</div>';
		}
		return $html . '</div>';
	}

	private static function rows( array $rows ) {
		$html = '<table class="bsi-table"><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . $row[1] . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/** Traffic figures come only from the editor's manual metrics, which the site has the right to publish. */
	private static function section_overview( array $data, array $manual ) {
		$rows = array();
		if ( ! empty( $manual['visits'] ) ) {
			$rows[] = array( 'Monthly Visits', esc_html( BSI_Estimator::short_number( $manual['visits'] ) ) );
		}
		if ( ! empty( $manual['duration'] ) ) {
			$rows[] = array( 'Avg. Visit Duration', esc_html( $manual['duration'] ) );
		}
		if ( ! empty( $manual['pages'] ) ) {
			$rows[] = array( 'Pages per Visit', esc_html( $manual['pages'] ) );
		}
		if ( ! empty( $manual['bounce'] ) ) {
			$rows[] = array( 'Bounce Rate', esc_html( rtrim( $manual['bounce'], '%' ) . '%' ) );
		}
		return $rows ? self::section( 'Traffic Overview', self::rows( $rows ) ) : '';
	}

	private static function section_geography( array $data, array $manual ) {
		$items = self::parse_pairs( isset( $manual['countries'] ) ? $manual['countries'] : '' );
		if ( $items ) {
			$html = '<ul class="bsi-bars">';
			foreach ( array_slice( $items, 0, 5 ) as $code => $share ) {
				$html .= self::bar( self::flag( $code ) . ' ' . esc_html( self::country( $code ) ), $share );
			}
			return self::section( 'Geography', $html . '</ul>', '', '' );
		}

		return '';
	}

	private static function section_sources( array $manual ) {
		$items = self::parse_pairs( isset( $manual['sources'] ) ? $manual['sources'] : '', false );
		if ( ! $items ) {
			return '';
		}
		$html = '<ul class="bsi-bars">';
		foreach ( $items as $label => $share ) {
			$html .= self::bar( esc_html( $label ), $share );
		}
		return self::section( 'Traffic Sources', $html . '</ul>' );
	}

	private static function section_vitals( array $data ) {
		if ( empty( $data['vitals'] ) || ! is_array( $data['vitals'] ) ) {
			return '';
		}
		$v      = $data['vitals'];
		$tiles  = '';
		$config = array(
			'lcp'  => array( 'Largest Contentful Paint', 2500, 4000, 'ms_to_s' ),
			'inp'  => array( 'Interaction to Next Paint', 200, 500, 'ms' ),
			'cls'  => array( 'Cumulative Layout Shift', 0.1, 0.25, 'plain' ),
			'ttfb' => array( 'Time to First Byte', 800, 1800, 'ms_to_s' ),
		);
		foreach ( $config as $key => $c ) {
			if ( empty( $v[ $key ] ) ) {
				continue;
			}
			$p75   = (float) $v[ $key ]['p75'];
			$grade = $p75 <= $c[1] ? 'good' : ( $p75 <= $c[2] ? 'fair' : 'poor' );
			if ( 'ms_to_s' === $c[3] ) {
				$shown = number_format( $p75 / 1000, 2 ) . ' s';
			} elseif ( 'ms' === $c[3] ) {
				$shown = number_format( $p75 ) . ' ms';
			} else {
				$shown = number_format( $p75, 2 );
			}
			$label  = array(
				'good' => 'Good',
				'fair' => 'Needs improvement',
				'poor' => 'Poor',
			);
			$tiles .= '<div class="bsi-tile bsi-tile--' . $grade . '"><span class="bsi-tile__name">' . esc_html( $c[0] ) . '</span><strong>' . esc_html( $shown ) . '</strong><span class="bsi-tile__grade">' . esc_html( $label[ $grade ] ) . '</span></div>';
		}
		if ( '' === $tiles ) {
			return '';
		}
		$note = 'Real-user experience from Chrome users, 75th percentile' . ( ! empty( $v['period'] ) ? ', 28 days to ' . $v['period'] : '' ) . '.';
		return '<div class="bsi-section bsi-section--full"><div class="bsi-col"><p class="bsi-label">Performance (Core Web Vitals)</p><div class="bsi-tiles">' . $tiles . '</div><p class="bsi-note">' . esc_html( $note ) . '</p></div></div>';
	}

	private static function section_status( $status, $name, $visit_url ) {
		if ( ! $status ) {
			return '';
		}
		$rows = array(
			array(
				'Status',
				$status['last_ok']
					? '<span class="bsi-state bsi-state--up">Online</span>'
					: '<span class="bsi-state bsi-state--down">' . esc_html( $status['last_code'] ? 'Error ' . $status['last_code'] : 'Unreachable' ) . '</span>',
			),
			array( 'Uptime (30 days)', esc_html( number_format( $status['uptime'], 2 ) . '%' ) ),
		);
		if ( null !== $status['avg_ms'] ) {
			$rows[] = array( 'Avg. Response Time', esc_html( number_format( $status['avg_ms'] ) . ' ms' ) );
		}
		$rows[] = array( 'Last Checked', esc_html( sprintf( '%s ago', human_time_diff( strtotime( $status['last_at'] . ' UTC' ) ) ) ) );

		$chart = BSI_Charts::line(
			$status['series'],
			array(
				'title'  => 'Response time (ms)',
				'format' => array( __CLASS__, 'ms_label' ),
				'area'   => false,
			)
		);
		$body = self::rows( $rows );
		if ( $visit_url ) {
			$body .= '<p><a class="bsi-button" href="' . esc_url( $visit_url ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( sprintf( 'Visit %s', $name ) ) . ' ›</a></p>';
		}
		return self::section( sprintf( '%s Status', $name ), $body, $chart, $chart ? 'Response time over the last 30 days · red lines = downtime' : '' );
	}

	private static function section_domain( array $data, $domain ) {
		$rows = array();
		$site = isset( $data['site'] ) ? (array) $data['site'] : array();
		if ( ! empty( $data['ssl']['issuer'] ) ) {
			$rows[] = array( 'SSL Certificate', esc_html( $data['ssl']['issuer'] . ( ! empty( $data['ssl']['valid_to'] ) ? ', valid until ' . $data['ssl']['valid_to'] : '' ) ) );
		}
		if ( isset( $site['https'] ) ) {
			$rows[] = array( 'Security', esc_html( ( $site['https'] ? 'HTTPS' : 'No HTTPS' ) . ( ! empty( $site['hsts'] ) ? ' + HSTS' : '' ) ) );
		}
		if ( ! empty( $site['server'] ) ) {
			$rows[] = array( 'Server', esc_html( $site['server'] ) );
		}
		if ( ! empty( $site['lang'] ) ) {
			$rows[] = array( 'Language', esc_html( $site['lang'] ) );
		}

		$tech = '';
		if ( ! empty( $site['tech'] ) && is_array( $site['tech'] ) ) {
			foreach ( $site['tech'] as $category => $names ) {
				$tech .= '<div class="bsi-tech"><span class="bsi-tech__cat">' . esc_html( $category ) . '</span>';
				foreach ( (array) $names as $tool ) {
					$tech .= '<span class="bsi-chip">' . esc_html( $tool ) . '</span>';
				}
				$tech .= '</div>';
			}
		}
		$social = '';
		if ( ! empty( $site['social'] ) && is_array( $site['social'] ) ) {
			$social = '<div class="bsi-social">';
			foreach ( $site['social'] as $network => $url ) {
				$social .= '<a class="bsi-chip bsi-chip--link" href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">' . esc_html( 'x' === $network ? 'X (Twitter)' : ucfirst( $network ) ) . '</a>';
			}
			$social .= '</div>';
		}
		if ( ! $rows && '' === $tech && '' === $social ) {
			return '';
		}

		$side = '';
		if ( '' !== $tech ) {
			$side .= '<p class="bsi-label">Technology</p>' . $tech;
		}
		if ( '' !== $social ) {
			$side .= '<p class="bsi-label">Social Profiles</p>' . $social;
		}
		return self::section( sprintf( 'Domain: %s', $domain ), self::rows( $rows ), $side );
	}

	private static function footer( $site, array $data ) {
		$text = 'Sources: the website itself and uptime monitoring by this site.';
		if ( ! empty( $site['fetched_at'] ) ) {
			$text .= ' ' . sprintf( 'Updated %s ago.', human_time_diff( strtotime( $site['fetched_at'] . ' UTC' ) ) );
		}
		$html = esc_html( $text );
		if ( ! empty( $data['vitals'] ) ) {
			// CC BY 4.0 requires credit to Google and a link to the licence.
			$html .= ' Core Web Vitals: <a href="https://developer.chrome.com/docs/crux" target="_blank" rel="noopener">Chrome UX Report</a> data by Google, licensed under <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>.';
		}
		return '<p class="bsi-footer">' . $html . '</p>';
	}

	/* --------------------------------------------------------------- Helpers */

	/** "US 12.3\nIN: 9%" → array( 'US' => 12.3, 'IN' => 9.0 ). */
	public static function parse_pairs( $text, $country_codes = true ) {
		$items = array();
		foreach ( preg_split( '/[\r\n]+/', (string) $text ) as $line ) {
			if ( ! preg_match( '/^\s*(.+?)\s*[:=|,]?\s*([0-9]+(?:[.,][0-9]+)?)\s*%?\s*$/u', $line, $m ) ) {
				continue;
			}
			$label = trim( $m[1] );
			if ( $country_codes ) {
				$label = strtoupper( $label );
				if ( ! preg_match( '/^[A-Z]{2}$/', $label ) ) {
					continue;
				}
			}
			$items[ $label ] = (float) str_replace( ',', '.', $m[2] );
		}
		arsort( $items );
		return $items;
	}

	private static function bar( $label_html, $share ) {
		$width = max( 1, min( 100, (float) $share ) );
		return '<li class="bsi-bar"><span class="bsi-bar__label">' . $label_html . '</span><span class="bsi-bar__value">' . esc_html( number_format( (float) $share, 2 ) . '%' ) . '</span><span class="bsi-bar__track"><span style="width:' . esc_attr( $width ) . '%"></span></span></li>';
	}

	private static function flag( $code ) {
		return '<span class="bsi-flag" aria-hidden="true">' . esc_html( self::flag_text( $code ) ) . '</span>';
	}

	private static function flag_text( $code ) {
		$code = strtoupper( (string) $code );
		if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return '';
		}
		return html_entity_decode( sprintf( '&#x%X;&#x%X;', 0x1F1E6 + ord( $code[0] ) - 65, 0x1F1E6 + ord( $code[1] ) - 65 ), ENT_QUOTES, 'UTF-8' );
	}

	private static function country( $code ) {
		return isset( self::$countries[ $code ] ) ? self::$countries[ $code ] : $code;
	}

	public static function ms_label( $value ) {
		return number_format( round( $value ) ) . 'ms';
	}
}
