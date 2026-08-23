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
	 * Render the slider and its readout.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$unit  = (string) $field->get( 'unit', '' );
		$value = $this->render_value( $field );

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
