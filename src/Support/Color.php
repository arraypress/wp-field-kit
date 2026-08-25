<?php
/**
 * Reading a colour a person picked.
 *
 * @package   ArrayPress\FieldKit
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.4.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

/**
 * Class Color
 *
 * A colour field stores a hex value, and something then has to render with it
 * -- a badge, a swatch, a category label. That thing needs text on top, and
 * whether the text should be black or white is not a decision the person
 * picking the colour made.
 *
 * Core sanitizes a hex value (`sanitize_hex_color()`) and stops there, which
 * is why this is here and why it is only this. The rest of what a colour
 * library usually offers -- lighten, darken, mix, complementary -- is theming,
 * and nothing in this set was using it.
 *
 * @since 1.4.0
 */
final class Color {

	/**
	 * Black or white, whichever is readable on this background.
	 *
	 * By WCAG contrast ratio rather than the YIQ approximation most pickers
	 * use. They disagree: YIQ calls #767676 light and picks black, where the
	 * real ratios are 4.54 against white and 4.62 against black -- close, and
	 * on the other side of the line. WCAG is the one an accessibility audit
	 * will use, so it is the one to answer with.
	 *
	 * @since 1.4.0
	 *
	 * @param string $hex A hex colour, with or without the hash.
	 *
	 * @return string `#000000` or `#ffffff`.
	 */
	public static function contrast( string $hex ): string {
		$rgb = self::rgb( $hex );

		if ( null === $rgb ) {
			return '#000000';
		}

		$luminance = self::luminance( $rgb );

		// The ratio against white, and against black.
		$against_white = 1.05 / ( $luminance + 0.05 );
		$against_black = ( $luminance + 0.05 ) / 0.05;

		return $against_black >= $against_white ? '#000000' : '#ffffff';
	}

	/**
	 * The contrast ratio between two colours, 1 to 21.
	 *
	 * @since 1.4.0
	 *
	 * @param string $one A hex colour.
	 * @param string $two A hex colour.
	 *
	 * @return float|null Null when either is not a colour.
	 */
	public static function ratio( string $one, string $two ): ?float {
		$first  = self::rgb( $one );
		$second = self::rgb( $two );

		if ( null === $first || null === $second ) {
			return null;
		}

		$lighter = max( self::luminance( $first ), self::luminance( $second ) );
		$darker  = min( self::luminance( $first ), self::luminance( $second ) );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Whether two colours meet WCAG AA.
	 *
	 * 4.5:1 for body text, 3:1 for large text -- 18pt, or 14pt bold.
	 *
	 * @since 1.4.0
	 *
	 * @param string $one   A hex colour.
	 * @param string $two   A hex colour.
	 * @param bool   $large Whether the text is large.
	 *
	 * @return bool
	 */
	public static function readable( string $one, string $two, bool $large = false ): bool {
		$ratio = self::ratio( $one, $two );

		return null !== $ratio && $ratio >= ( $large ? 3.0 : 4.5 );
	}

	/**
	 * A hex colour as red, green and blue.
	 *
	 * @since 1.4.0
	 *
	 * @param string $hex A hex colour, with or without the hash, three or six
	 *                    digits.
	 *
	 * @return array{0: int, 1: int, 2: int}|null
	 */
	public static function rgb( string $hex ): ?array {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			// #abc is #aabbcc.
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 1 !== preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return null;
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Relative luminance, as WCAG defines it.
	 *
	 * Gamma-corrected, which is the part the YIQ approximation leaves out and
	 * the reason the two disagree near the middle of the range.
	 *
	 * @param array{0: int, 1: int, 2: int} $rgb The colour.
	 *
	 * @return float 0 for black, 1 for white.
	 */
	private static function luminance( array $rgb ): float {
		$channels = array();

		foreach ( $rgb as $value ) {
			$channel = $value / 255;

			$channels[] = $channel <= 0.04045
				? $channel / 12.92
				: ( ( $channel + 0.055 ) / 1.055 ) ** 2.4;
		}

		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}
}
