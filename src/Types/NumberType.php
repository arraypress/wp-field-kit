<?php
/**
 * Number Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A numeric input, clamped to any configured min and max.
 */
class NumberType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'number';
	}

	/**
	 * A number rarely wants a full-width input.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function width_class( Field $field ): string {
		return $field->has( 'size' ) ? parent::width_class( $field ) : 'small-text';
	}

	/**
	 * Coerce a submitted value.
	 *
	 * Reconciles the two predecessor implementations, which disagreed on both
	 * halves. Non-numeric input becomes the minimum rather than silently
	 * zero, since zero can be outside the allowed range.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return int|float
	 */
	public function sanitize( mixed $value, Field $field ): int|float {
		$min = $field->get( 'min' );
		$max = $field->get( 'max' );

		if ( ! is_numeric( $value ) ) {
			return $this->is_fractional( $field ) ? (float) ( $min ?? 0 ) : (int) ( $min ?? 0 );
		}

		$number = $this->is_fractional( $field ) ? (float) $value : (int) $value;

		if ( null !== $min && $number < (float) $min ) {
			$number = $this->is_fractional( $field ) ? (float) $min : (int) $min;
		}

		if ( null !== $max && $number > (float) $max ) {
			$number = $this->is_fractional( $field ) ? (float) $max : (int) $max;
		}

		return $number;
	}

	/**
	 * Whether the configured step admits fractions.
	 *
	 * `is_numeric( $step ) && floor( $step ) != $step` is deliberately loose:
	 * is_numeric() admits the string "0.5", and floor() returns a float, so a
	 * strict comparison would be true for every string step and force every
	 * field to float. The string form is also why str_contains on '.' alone
	 * is not enough — a step of "1e-2" has no dot.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function is_fractional( Field $field ): bool {
		$step = $field->get( 'step', 1 );

		if ( 'any' === $step ) {
			return true;
		}

		if ( ! is_numeric( $step ) ) {
			return false;
		}

		return abs( (float) $step - floor( (float) $step ) ) > PHP_FLOAT_EPSILON;
	}

	/**
	 * An integer, or a number when the step is fractional.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		$schema = [ 'type' => $this->is_fractional( $field ) ? 'number' : 'integer' ];

		if ( null !== $field->get( 'min' ) ) {
			$schema['minimum'] = $this->is_fractional( $field ) ? (float) $field->get( 'min' ) : (int) $field->get( 'min' );
		}

		if ( null !== $field->get( 'max' ) ) {
			$schema['maximum'] = $this->is_fractional( $field ) ? (float) $field->get( 'max' ) : (int) $field->get( 'max' );
		}

		return $schema;
	}
}
