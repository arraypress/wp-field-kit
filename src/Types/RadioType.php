<?php
/**
 * Radio Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A set of mutually exclusive choices.
 *
 * Reported as grouped, so the renderer wraps it in a fieldset with a legend:
 * there is no single element for a `<label for>` to point at, and without the
 * fieldset a screen reader announces each option with no idea what question
 * it answers.
 */
class RadioType extends AbstractType {

	/**
	 * Render the option list.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$name     = (string) $attributes->get( 'name' );
		$current  = (string) $field->value();
		$required = $field->is_required();
		$markup   = '';
		$index    = 0;

		foreach ( $field->options() as $value => $label ) {
			$option_id = $field->input_id() . '_' . $index;
			$option    = new Attributes();

			$option->set( 'type', $this->input_type() );
			$option->set( 'id', $option_id );
			$option->set( 'name', $name );
			$option->set( 'value', (string) $value );
			$option->add_class( 'field-kit__' . $this->input_type() );
			// Loose: an option key of 1 matches a stored "1".
			$option->set_if( (string) $value === $current, 'checked', true );
			$option->set_if( $required, 'required', true );
			$option->set_if( (bool) $field->get( 'disabled' ), 'disabled', true );

			$markup .= sprintf(
				'<div class="%s"><input%s /><label for="%s">%s</label></div>',
				esc_attr( 'field-kit__' . $this->wrapper_class() . '-option' ),
				$option->render(),
				esc_attr( $option_id ),
				esc_html( (string) $label )
			);

			++$index;
		}

		return sprintf(
			'<div class="field-kit__%s">%s</div>',
			esc_attr( $this->wrapper_class() ),
			$markup
		);
	}

	/**
	 * The input type each option renders as.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'radio';
	}

	/**
	 * Class stem for the wrapper.
	 *
	 * @return string
	 */
	protected function wrapper_class(): string {
		return 'radio-group';
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		$value   = sanitize_text_field( (string) $value );
		$allowed = array_map( 'strval', array_keys( $field->options() ) );

		return in_array( $value, $allowed, true ) ? $value : '';
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
