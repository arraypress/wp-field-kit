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

/**
 * Fragments the renderer and self-labelling types both need.
 *
 * A self-labelling control writes its own `<label>`, so the renderer cannot
 * put the required marker inside it. Sharing the fragment here is what stops
 * the two producing different markup — and stops a required checkbox
 * shipping with no marker at all, which is what happened before this existed.
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
}
