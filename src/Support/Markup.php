<?php
/**
 * Shared Markup Helpers
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\FieldKit\Attributes;

/**
 * Fragments more than one renderer needs.
 *
 * A self-labelling control writes its own `<label>`, so the renderer cannot
 * put the required marker inside it. Sharing the fragment here is what stops
 * the two producing different markup — and stops a required checkbox
 * shipping with no marker at all, which is what happened before this existed.
 * The character count is shared for the same reason: a text input and a
 * textarea both draw one, and one script drives both only while they agree.
 */
final class Markup {

	/**
	 * The visual and announced marker for a required field.
	 *
	 * The asterisk is decorative: `aria-required` on the control is what
	 * conveys the state, so the asterisk is hidden from assistive technology
	 * and the word is supplied for it instead.
	 *
	 * @param bool $required Whether the field is required.
	 *
	 * @return string
	 */
	public static function required_marker( bool $required ): string {
		if ( ! $required ) {
			return '';
		}

		return sprintf(
			'<span class="field-kit__required" aria-hidden="true">*</span><span class="screen-reader-text">%s</span>',
			esc_html__( '(required)', 'arraypress' )
		);
	}

	/**
	 * A running count of characters against a limit.
	 *
	 * Rendered with the count already in it, so the field reads right before
	 * the script runs and if it never does. A polite live region, because
	 * the number changes as someone types and someone who cannot see it
	 * still needs to know when they are near the limit. `data-counter` is
	 * the script's hook.
	 *
	 * The width class is the control's own -- core's `regular-text` -- so
	 * the count is as wide as the control and its number sits under the
	 * control's end rather than at the far edge of a settings-page cell.
	 *
	 * @param int    $length Characters in the current value.
	 * @param int    $max    The limit. Nothing is drawn without one.
	 * @param bool   $wanted Whether the field wants a count at all.
	 * @param string $width  Core's width class of the control it sits under.
	 *
	 * @return string
	 */
	public static function counter( int $length, int $max, bool $wanted = true, string $width = '' ): string {
		if ( ! $wanted || $max < 1 ) {
			return '';
		}

		$attributes = new Attributes();
		$attributes->add_class( 'field-kit__count', 'description', $width );

		// A value saved before the limit was lowered is already over it. The
		// browser stops anyone typing past a maxlength; it does not truncate
		// what was there, and the person editing has to be told why the
		// form will not submit.
		if ( $length > $max ) {
			$attributes->add_class( 'field-kit__count--over' );
		}

		$attributes->set( 'data-counter', true );
		$attributes->set( 'aria-live', 'polite' );

		return sprintf(
			'<span%s>%s</span>',
			$attributes->render(),
			esc_html( sprintf( '%d / %d', $length, $max ) )
		);
	}
}
