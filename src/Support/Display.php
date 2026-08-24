<?php
/**
 * Display
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

/**
 * Turning a stored value into something to read.
 *
 * Not the same question as rendering a control, which is what the types do —
 * this is for the places that show a value rather than collect one: an info
 * grid, a data table, a list-table column, a summary line.
 *
 * It is here because it had been answered twice, differently, and the two
 * disagreed on the case that matters most:
 *
 *     value    flyouts            tables
 *     0        a value            a value
 *     0.0      "—"                a value
 *     []       "—"                a value
 *     false    "—"                "—"
 *
 * A price of 0.00 read as an em-dash in a flyout and as 0.00 in the list table
 * beside it. The flyout's rule was PHP's `empty()` with two exceptions bolted
 * on, which is how `0.0` and `[]` got in — `empty()` is a question about
 * truthiness, and "did anyone put a value here" is a different question.
 *
 * So the rule is strict and short: nothing was put here if it is null, an
 * empty string, or false. Zero is a value, in every type it comes in. An empty
 * array is a value too — an empty list of things is a fact about the row, and
 * a caller wanting a dash for it can ask.
 *
 * `false` being nothing rather than "No" is the one part worth knowing. It is
 * what a lookup returns when it finds nothing — `get_post_meta` on a key that
 * was never saved, a `get_*` that failed — and showing "No" for that would be
 * asserting something nobody stored. A field that means a real no stores `0`,
 * which is a value and reads as one. The consequence: `text( true )` is "Yes"
 * and `text( false )` is a dash, which looks asymmetric until you remember
 * they arrive from different places.
 */
final class Display {

	/**
	 * Whether nothing was put here.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	public static function is_empty( mixed $value ): bool {
		return null === $value || '' === $value || false === $value;
	}

	/**
	 * What to show in place of a value that is not there.
	 *
	 * The dash is hidden from assistive technology and a word given instead:
	 * a bare em-dash is announced as "dash" by some screen readers and passed
	 * over in silence by others, and neither says what it means.
	 *
	 * @return string
	 */
	public static function placeholder(): string {
		return '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">'
			. esc_html__( 'Not set', 'arraypress' )
			. '</span>';
	}

	/**
	 * A value, as text.
	 *
	 * Escaped, because every caller is putting the result into a page and the
	 * value came out of somebody's database.
	 *
	 * A caller's own placeholder is escaped; the default one is markup this
	 * class wrote and is not. Which is why it is one parameter and not two —
	 * the caller cannot accidentally hand in HTML and have it rendered, and
	 * cannot accidentally have this class's markup escaped into visible tags.
	 * Both happened: an info grid escaped the result a second time and
	 * printed `&lt;em&gt;none&lt;/em&gt;` on the page.
	 *
	 * @param mixed  $value       The value.
	 * @param string $placeholder Plain text to show when there is nothing.
	 *                            The accessible dash when not given.
	 *
	 * @return string
	 */
	public static function text( mixed $value, string $placeholder = '' ): string {
		if ( self::is_empty( $value ) ) {
			return '' === $placeholder ? self::placeholder() : esc_html( $placeholder );
		}

		if ( true === $value ) {
			return esc_html__( 'Yes', 'arraypress' );
		}

		if ( is_array( $value ) ) {
			return esc_html( implode( ', ', array_map( 'strval', $value ) ) );
		}

		if ( is_object( $value ) ) {
			// Anything else is not a value to show — printing "Object" or a
			// class name tells a reader nothing, and casting throws.
			return method_exists( $value, '__toString' )
				? esc_html( (string) $value )
				: ( '' === $placeholder ? self::placeholder() : esc_html( $placeholder ) );
		}

		return esc_html( (string) $value );
	}
}
