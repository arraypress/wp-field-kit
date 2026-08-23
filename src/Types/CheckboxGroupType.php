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
		$index   = 0;

		foreach ( $field->options() as $value => $label ) {
			$option_id = $field->input_id() . '_' . $index;
			$option    = new Attributes();

			$option->set( 'type', 'checkbox' );
			$option->set( 'id', $option_id );
			$option->set( 'name', $name . '[]' );
			$option->set( 'value', (string) $value );
			$option->add_class( 'field-kit__checkbox' );
			$option->set_if( in_array( (string) $value, $current, true ), 'checked', true );
			$option->set_if( (bool) $field->get( 'disabled' ), 'disabled', true );

			$markup .= sprintf(
				'<div class="field-kit__checkbox-group-option"><input%s /><label for="%s">%s</label></div>',
				$option->render(),
				esc_attr( $option_id ),
				esc_html( (string) $label )
			);

			++$index;
		}

		// An empty array is never posted, so nothing would distinguish
		// "cleared every box" from "the group was not on the form".
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
}
