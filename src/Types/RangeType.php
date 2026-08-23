<?php
/**
 * Range Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A slider, with the current value shown beside it.
 *
 * The readout uses `<output for>` and `aria-live="polite"`, so the value is
 * announced as it changes rather than being a number only sighted users can
 * see. A slider with no visible value is a common accessibility failure.
 */
final class RangeType extends NumberType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'range';
	}

	/**
	 * A slider has no placeholder to show.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return false;
	}

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'min'  => 0,
			'max'  => 100,
			'step' => 1,
		];
	}

	/**
	 * The value the slider actually sits at.
	 *
	 * A range input with no value does not sit at its minimum: the browser
	 * puts the thumb at the midpoint of the range. Printing an empty readout
	 * beside a thumb that is visibly somewhere is the bug this avoids — the
	 * number only appeared once the slider had been moved.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function current_value( Field $field ): string {
		$value = $field->value();

		if ( is_scalar( $value ) && '' !== (string) $value ) {
			return (string) $value;
		}

		$min = (float) $field->get( 'min', 0 );
		$max = (float) $field->get( 'max', 100 );

		// What the browser does with no value, per the HTML specification.
		$default = $min + ( ( $max - $min ) / 2 );
		$step    = $field->get( 'step', 1 );

		// Snapped to the step, so the readout matches a value the control can
		// actually report rather than a midpoint between two stops.
		if ( is_numeric( $step ) && (float) $step > 0 ) {
			$default = $min + ( round( ( $default - $min ) / (float) $step ) * (float) $step );
		}

		return 0.0 === fmod( $default, 1.0 ) ? (string) (int) $default : (string) $default;
	}

	/**
	 * Render the slider and its readout.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$unit  = (string) $field->get( 'unit', '' );
		$value = $this->current_value( $field );

		// The input has to carry the same value the readout prints, or the
		// two disagree until the slider is first moved.
		$attributes->set( 'value', $value );

		$input = parent::render( $field, $attributes );

		$output = sprintf(
			'<output class="field-kit__range-output" for="%s" aria-live="polite">%s</output>',
			esc_attr( $field->input_id() ),
			esc_html( $value . $unit )
		);

		return sprintf(
			'<div class="field-kit__range" data-unit="%s">%s%s</div>',
			esc_attr( $unit ),
			$input,
			$output
		);
	}
}
