<?php
/**
 * Password Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A password input. The value is never echoed back into the markup.
 *
 * It can be revealed while it is typed. A key pasted into a box of dots
 * cannot be checked against the one it was copied from, and the usual result
 * is a saved key with a character missing and an integration that fails for
 * no visible reason. The button is the one core draws on the profile screen,
 * with the same classes, so it is the control people have already met.
 *
 * Revealing shows what has been *typed*. The stored value is still never
 * sent to the page, so there is nothing to reveal until something is
 * entered -- which is the point: a reveal button is not a way to read a
 * secret back out of the database.
 */
final class PasswordType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'password';
	}

	/**
	 * The input, with a button to show what has been typed into it.
	 *
	 * Wrapped here rather than in render() so the count a `maxlength` adds
	 * lands after the whole control, not between the input and its button.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	protected function control( Field $field, Attributes $attributes ): string {
		$input = parent::control( $field, $attributes );

		// Off unless asked for. Core offers the button where somebody is
		// setting a password they will have to type again; an API key pasted
		// once and never shown back is not that.
		if ( ! (bool) $field->get( 'reveal', false ) ) {
			return $input;
		}

		// hide-if-no-js: without the script the button does nothing, and a
		// button that does nothing is worse than none. aria-pressed carries
		// the state; the label is swapped as well because that is what
		// core's own toggle does, and matching it is the reason for using
		// its classes.
		return sprintf(
			'<span class="field-kit__password">%s<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js field-kit__password-toggle" aria-label="%s" aria-pressed="false" data-toggle="0"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button></span>',
			$input,
			esc_attr__( 'Show password', 'arraypress' )
		);
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		return sanitize_text_field( $this->scalar( $value ) );
	}

	/**
	 * Never render the stored value back into the page.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_value( Field $field ): string {
		return '';
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'reveal' ]
		);
	}
}
