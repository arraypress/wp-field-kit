<?php
/**
 * Base Range Pair Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A "from" and "to" pair sharing one key.
 *
 * Both halves get a real visible label rather than being distinguished only
 * by position, and the pair is a fieldset so the two are announced as
 * belonging to one question.
 */
abstract class AbstractRangePairType extends AbstractType {

	/**
	 * The HTML input type for both halves.
	 *
	 * @return string
	 */
	abstract protected function input_type(): string;

	/**
	 * Render both halves.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$value = is_array( $field->value() ) ? $field->value() : [];

		$from = $this->part_attributes( $attributes, 'from', true );
		$from->set( 'type', $this->input_type() );
		$from->set( 'value', (string) ( $value['from'] ?? '' ) );
		$from->add_class( 'field-kit__range-pair-input' );

		$to = $this->part_attributes( $attributes, 'to' );
		$to->set( 'type', $this->input_type() );
		$to->set( 'value', (string) ( $value['to'] ?? '' ) );
		$to->add_class( 'field-kit__range-pair-input' );

		return sprintf(
			'<div class="field-kit__range-pair">' .
			'<div class="field-kit__range-pair-part">%s<input%s /></div>' .
			'<div class="field-kit__range-pair-part">%s<input%s /></div>' .
			'</div>',
			$this->sub_label( (string) $from->get( 'id' ), __( 'From', 'arraypress' ) ),
			$from->render(),
			$this->sub_label( (string) $to->get( 'id' ), __( 'To', 'arraypress' ) ),
			$to->render()
		);
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return array{from: string, to: string}
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$value = is_array( $value ) ? $value : [];

		return [
			'from' => sanitize_text_field( (string) ( $value['from'] ?? '' ) ),
			'to'   => sanitize_text_field( (string) ( $value['to'] ?? '' ) ),
		];
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
	 * Two values under one key.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'       => 'object',
			'properties' => [
				'start' => [ 'type' => 'string' ],
				'end'   => [ 'type' => 'string' ],
			],
		];
	}
}
