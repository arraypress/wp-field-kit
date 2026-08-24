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
		$attributes->add_class( 'field-kit__tags-input' );
		$attributes->set( 'data-separator', (string) $field->get( 'separator', ',' ) );

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
}
