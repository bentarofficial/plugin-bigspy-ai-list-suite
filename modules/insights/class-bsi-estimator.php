<?php
defined( 'ABSPATH' ) || exit;

/**
 * Turns a global popularity rank into a monthly-visits range.
 *
 * visits ≈ COEFFICIENT × rank^-EXPONENT, calibrated by hand against public figures
 * (rank ~300 ≈ 60–100M visits, rank ~20k ≈ 300k–900k). It is an estimate, always
 * shown as a range and labelled as such. Both constants are filterable so they can
 * be recalibrated once real analytics from connected sites are available.
 */
class BSI_Estimator {
	const COEFFICIENT = 5.0e10;
	const EXPONENT    = 1.15;
	const LOW         = 0.5;
	const HIGH        = 1.6;

	public static function monthly_visits( $rank ) {
		$rank = (int) $rank;
		if ( $rank < 1 ) {
			return null;
		}
		$coefficient = (float) apply_filters( 'bsi_estimator_coefficient', self::COEFFICIENT );
		$exponent    = (float) apply_filters( 'bsi_estimator_exponent', self::EXPONENT );
		$mid         = $coefficient * pow( $rank, -$exponent );
		return array(
			'low'  => self::round_nice( $mid * self::LOW ),
			'mid'  => self::round_nice( $mid ),
			'high' => self::round_nice( $mid * self::HIGH ),
		);
	}

	/** Keeps two significant digits so the range does not look falsely precise. */
	public static function round_nice( $value ) {
		if ( $value < 100 ) {
			return (int) round( $value );
		}
		$magnitude = pow( 10, floor( log10( $value ) ) - 1 );
		return (int) ( round( $value / $magnitude ) * $magnitude );
	}

	public static function short_number( $value ) {
		$value = (float) $value;
		if ( $value >= 1e9 ) {
			return rtrim( rtrim( number_format( $value / 1e9, 1 ), '0' ), '.' ) . 'B';
		}
		if ( $value >= 1e6 ) {
			return rtrim( rtrim( number_format( $value / 1e6, 1 ), '0' ), '.' ) . 'M';
		}
		if ( $value >= 1e3 ) {
			return rtrim( rtrim( number_format( $value / 1e3, 1 ), '0' ), '.' ) . 'K';
		}
		return number_format( $value );
	}
}
