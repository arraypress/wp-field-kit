<?php
/**
 * Checkbox Group Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * Several independent choices sharing one label.
 */
final class CheckboxGroupType extends AbstractType {

	/**
	 * Render the option list.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$name    = (string) $attributes->get( 'name' );
		$current = array_map( 'strval', (array) $field->value() );
		$markup  = '';

		foreach ( $field->options() as $value => $label ) {
			$option = new Attributes();

			$option->set( 'type', 'checkbox' );
			$option->set( 'name', $name . '[]' );
			$option->set( 'value', (string) $value );
			$option->add_class( 'field-kit__checkbox' );
			$option->set_if( in_array( (string) $value, $current, true ), 'checked', true );
			$option->set_if( (bool) $field->get( 'disabled' ), 'disabled', true );

			// Wrapping label, as core writes checkbox groups on
			// options-discussion.php — and so no per-option id to collide
			// with the same option in another repeater row.
			$markup .= sprintf(
				'<label class="field-kit__option"><input%s /> <span>%s</span></label>',
				$option->render(),
				esc_html( (string) $label )
			);
		}

		// An empty array is never posted, so without this nothing
		// distinguishes "every box cleared" from "the group was not shown".
		return sprintf(
			'<input type="hidden" name="%s" value="" /><div class="field-kit__checkbox-group">%s</div>',
			esc_attr( $name . '[]' ),
			$markup
		);
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$allowed = array_map( 'strval', array_keys( $field->options() ) );
		$values  = array_filter( array_map( 'strval', (array) $value ), static fn( $v ) => '' !== $v );

		return array_values( array_intersect( $values, $allowed ) );
	}

	/**
	 * Needs a fieldset and legend.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return true;
	}

	/**
	 * The ticked values.
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
