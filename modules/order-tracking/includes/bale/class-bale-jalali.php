<?php
/**
 * Jalali date utilities for Hamidreza Order Notifier.
 *
 * @package Hamidreza
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts Gregorian and Jalali dates safely without modifying global PHP timezone.
 */
class DOT_Bale_Jalali {

	/**
	 * Converts Latin digits to Persian digits.
	 *
	 * @param string|int|float $value Input value.
	 * @return string
	 */
	public static function persian_digits( $value ) {
		return str_replace(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
			(string) $value
		);
	}

	/**
	 * Converts Gregorian DateTime to YYYY/MM/DD Jalali format.
	 *
	 * @param DateTimeInterface $date Date instance.
	 * @return string
	 */
	public static function from_datetime( DateTimeInterface $date ) {
		$jalali = self::gregorian_to_jalali(
			(int) $date->format( 'Y' ),
			(int) $date->format( 'n' ),
			(int) $date->format( 'j' )
		);

		return sprintf( '%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2] );
	}

	/**
	 * Formats a DateTime into a readable Jalali date & time string.
	 *
	 * @param DateTimeInterface $date Date instance.
	 * @param bool              $include_time Whether to include time.
	 * @param bool              $persian_nums Whether to use Persian digits.
	 * @return string
	 */
	public static function format_datetime( DateTimeInterface $date, $include_time = true, $persian_nums = false ) {
		$jdate = self::from_datetime( $date );

		if ( $include_time ) {
			$jdate .= ' ' . $date->format( 'H:i' );
		}

		return $persian_nums ? self::persian_digits( $jdate ) : $jdate;
	}

	/**
	 * Core conversion from Gregorian to Jalali.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month.
	 * @param int $gd Gregorian day.
	 * @return int[] array(jy, jm, jd)
	 */
	public static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) floor( ( $gy2 + 3 ) / 4 ) - (int) floor( ( $gy2 + 99 ) / 100 ) + (int) floor( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * (int) floor( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * (int) floor( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += (int) floor( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + (int) floor( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) floor( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}
}
