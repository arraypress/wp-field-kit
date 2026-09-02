<?php
/**
 * Tags Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Support\Rules;

/**
 * A free-form list of short strings.
 *
 * The stored value is a comma-separated string in a real text input, so the
 * field is fully usable — and fully accessible — before any enhancement runs.
 * The token UI, when it loads, mirrors that input rather than replacing it,
 * and the current tags are mirrored into a polite live region so adding and
 * removing one is announced.
 */
final class TagsType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'text';
	}

	/**
	 * Render the input and its live region.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$separator = (string) $field->get( 'separator', ',' );

		$attributes->add_class( 'field-kit__tags-input' );
		$attributes->set( 'data-separator', $separator );

		// A separated list in a text input looks exactly like a text input,
		// and the separator is the whole contract — get it wrong and one tag
		// is stored where three were meant. So it is said out loud, unless
		// the caller wrote something better.
		if ( '' === $field->placeholder() ) {
			$attributes->set(
				'placeholder',
				',' === $separator
					? __( 'Separate tags with commas', 'arraypress' )
					: sprintf(
						/* translators: %s: the character tags are separated with. */
						__( 'Separate tags with %s', 'arraypress' ),
						$separator
					)
			);
		}

		return sprintf(
			'<div class="field-kit__tags">%s<div class="field-kit__tags-list" aria-live="polite"></div></div>',
			parent::render( $field, $attributes )
		);
	}

	/**
	 * The value as a separated string, whichever way it was stored.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_value( Field $field ): string {
		$value = $field->value();

		return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}

	/**
	 * Coerce a submitted value to a list.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$separator = (string) $field->get( 'separator', ',' );
		$parts     = is_array( $value ) ? $value : explode( $separator, (string) $value );
		$parts     = array_map( static fn( $p ) => sanitize_text_field( trim( (string) $p ) ), $parts );

		return array_values( array_unique( array_filter( $parts, static fn( $p ) => '' !== $p ) ) );
	}

	/**
	 * Check each tag, not the list.
	 *
	 * A rule on a tags field is about what a tag is — `email` on a field of
	 * people to notify — and a list of strings is never an email address
	 * however good its members. One message names how many failed and the
	 * first few that did.
	 *
	 * @param mixed $value The sanitized value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function validate( mixed $value, Field $field ): string {
		$rule = $this->rule( $field );

		if ( null === $rule || ! is_array( $value ) ) {
			return parent::validate( $value, $field );
		}

		return Rules::check_each( $rule, $value, $field );
	}

	/**
	 * A list of strings, not the comma-separated string it is typed as.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'  => 'array',
			'items' => [ 'type' => 'string' ],
		];
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'separator' ]
		);
	}
}
