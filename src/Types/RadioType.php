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

		foreach ( $field->options() as $value => $label ) {
			$option = new Attributes();

			$option->set( 'type', $this->input_type() );
			$option->set( 'name', $name );
			$option->set( 'value', (string) $value );
			$option->add_class( 'field-kit__' . $this->input_type() );
			$option->set_if( (string) $value === $current, 'checked', true );
			$option->set_if( $required, 'required', true );
			$option->set_if( (bool) $field->get( 'disabled' ), 'disabled', true );

			$markup .= $this->render_option( $option, (string) $label, $field );
		}

		return sprintf(
			'<div class="field-kit__%s">%s</div>',
			esc_attr( $this->wrapper_class() ),
			$markup
		);
	}

	/**
	 * Render one option.
	 *
	 * The label wraps its input rather than pointing at it by id, which is
	 * how core writes radios on options-general.php. It also means the option
	 * needs no id at all — and an id it does not have cannot collide with the
	 * same option in another repeater row, which is a real failure mode: a
	 * duplicate id silently points every label after the first at the wrong
	 * control.
	 *
	 * @param Attributes $option The option's attributes.
	 * @param string     $label  The option's label.
	 * @param Field      $field  The field.
	 *
	 * @return string
	 */
	protected function render_option( Attributes $option, string $label, Field $field ): string {
		return sprintf(
			'<label class="field-kit__option"><input%s /> <span>%s</span></label>',
			$option->render(),
			esc_html( $label )
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
