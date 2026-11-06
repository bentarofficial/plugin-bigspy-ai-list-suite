<?php
defined( 'ABSPATH' ) || exit;

/**
 * Server-rendered SVG charts: no JavaScript library, fast, and readable by search engines.
 */
class BSI_Charts {

	const W = 640;
	const H = 200;

	/**
	 * @param array $points  label => number|null (null breaks the line, e.g. downtime).
	 * @param array $opts    invert (bool, smaller is better at the top), format (callable), title (string), unit (string).
	 */
	public static function line( array $points, array $opts = array() ) {
		$opts   = wp_parse_args(
			$opts,
			array(
				'invert' => false,
				'format' => array( 'BSI_Estimator', 'short_number' ),
				'title'  => '',
				'area'   => true,
			)
		);
		$points = self::downsample( $points, 240 );
		$values = array_filter( $points, 'is_numeric' );
		if ( count( $values ) < 2 ) {
			return '';
		}

		$pad_l = 66;
		$pad_r = 14;
		$pad_t = 12;
		$pad_b = 30;
		$plot_w = self::W - $pad_l - $pad_r;
		$plot_h = self::H - $pad_t - $pad_b;

		$min = min( $values );
		$max = max( $values );
		if ( $min === $max ) {
			$min = $min * 0.9;
			$max = $max * 1.1 + 1;
		}
		$span  = $max - $min;
		$min   = max( 0, $min - $span * 0.08 );
		$max   = $max + $span * 0.08;
		$count = count( $points );
		$step  = $count > 1 ? $plot_w / ( $count - 1 ) : 0;

		$y_of = function ( $v ) use ( $min, $max, $plot_h, $pad_t, $opts ) {
			$ratio = ( $v - $min ) / ( $max - $min );
			if ( $opts['invert'] ) {
				$ratio = 1 - $ratio;
			}
			return $pad_t + $plot_h - $ratio * $plot_h;
		};

		$segments = array();
		$current  = array();
		$down     = array();
		$i        = 0;
		foreach ( $points as $value ) {
			$x = $pad_l + $i * $step;
			if ( is_numeric( $value ) ) {
				$current[] = array( $x, $y_of( $value ) );
			} else {
				$down[] = $x;
				if ( $current ) {
					$segments[] = $current;
				}
				$current = array();
			}
			$i++;
		}
		if ( $current ) {
			$segments[] = $current;
		}

		$svg  = '<svg class="bsi-chart" viewBox="0 0 ' . self::W . ' ' . self::H . '" role="img" aria-label="' . esc_attr( $opts['title'] ) . '">';
		$svg .= '<title>' . esc_html( $opts['title'] ) . '</title>';

		// Grid and y labels.
		for ( $t = 0; $t <= 4; $t++ ) {
			$v   = $min + ( $max - $min ) * $t / 4;
			$y   = $y_of( $v );
			$svg .= '<line class="bsi-grid" x1="' . $pad_l . '" x2="' . ( self::W - $pad_r ) . '" y1="' . self::n( $y ) . '" y2="' . self::n( $y ) . '"/>';
			$svg .= '<text class="bsi-axis" x="' . ( $pad_l - 8 ) . '" y="' . self::n( $y + 4 ) . '" text-anchor="end">' . esc_html( call_user_func( $opts['format'], $v ) ) . '</text>';
		}

		// X labels: first, middle, last. Show the time when the series covers less than two days.
		$labels = array_keys( $points );
		$span_s = abs( strtotime( (string) end( $labels ) ) - strtotime( (string) reset( $labels ) ) );
		$format = $span_s < 2 * DAY_IN_SECONDS ? 'M j, H:i' : 'M j, Y';
		foreach ( array( 0, (int) floor( ( $count - 1 ) / 2 ), $count - 1 ) as $idx ) {
			$anchor = 0 === $idx ? 'start' : ( $count - 1 === $idx ? 'end' : 'middle' );
			$svg   .= '<text class="bsi-axis" x="' . self::n( $pad_l + $idx * $step ) . '" y="' . ( self::H - 8 ) . '" text-anchor="' . $anchor . '">' . esc_html( self::short_label( $labels[ $idx ], $format ) ) . '</text>';
		}

		foreach ( $segments as $segment ) {
			$path = '';
			foreach ( $segment as $n => $p ) {
				$path .= ( 0 === $n ? 'M' : 'L' ) . self::n( $p[0] ) . ' ' . self::n( $p[1] ) . ' ';
			}
			if ( $opts['area'] && count( $segment ) > 1 ) {
				$first = reset( $segment );
				$last  = end( $segment );
				$base  = $pad_t + $plot_h;
				$svg  .= '<path class="bsi-area" d="' . $path . 'L' . self::n( $last[0] ) . ' ' . $base . ' L' . self::n( $first[0] ) . ' ' . $base . ' Z"/>';
			}
			$svg .= '<path class="bsi-line" d="' . trim( $path ) . '"/>';
			if ( 1 === count( $segment ) ) {
				$svg .= '<circle class="bsi-dot" cx="' . self::n( $segment[0][0] ) . '" cy="' . self::n( $segment[0][1] ) . '" r="2.5"/>';
			}
		}
		foreach ( $down as $x ) {
			$svg .= '<line class="bsi-down" x1="' . self::n( $x ) . '" x2="' . self::n( $x ) . '" y1="' . $pad_t . '" y2="' . ( $pad_t + $plot_h ) . '"/>';
		}
		return $svg . '</svg>';
	}

	/** Averages neighbouring points so long series stay light; a null in a bucket keeps the gap visible. */
	private static function downsample( array $points, $max ) {
		$count = count( $points );
		if ( $count <= $max ) {
			return $points;
		}
		$size   = (int) ceil( $count / $max );
		$result = array();
		foreach ( array_chunk( $points, $size, true ) as $chunk ) {
			$labels  = array_keys( $chunk );
			$numbers = array_filter( $chunk, 'is_numeric' );
			$result[ end( $labels ) ] = count( $numbers ) === count( $chunk ) ? array_sum( $numbers ) / count( $numbers ) : null;
		}
		return $result;
	}

	private static function short_label( $label, $format ) {
		$time = strtotime( (string) $label );
		return $time ? gmdate( $format, $time ) : (string) $label;
	}

	private static function n( $number ) {
		return rtrim( rtrim( number_format( (float) $number, 1, '.', '' ), '0' ), '.' );
	}
}
