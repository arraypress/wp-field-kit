<?php
/**
 * Amount + Unit Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A number paired with a unit, most often a percentage or a flat amount.
 *
 * The unit is written to a second key, named by `type_meta_key`, because
 * consumers query and sort on it independently of the amount. Both controls
 * are labelled: a bare unit select beside a number is announced as an
 * unlabelled combo box.
 */
final class AmountType extends AbstractType {

	/**
	 * The config spelling keeps the `_type` suffix both predecessors used.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'amount_type';
	}

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'type_options' => [
				'percent' => '%',
				'flat'    => '$',
			],
			'type_default' => 'percent',
			'min'          => 0,
			'step'         => 0.01,
		];
	}

	/**
	 * Render the amount and its unit.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'number' );
		$attributes->set( 'value', (string) $field->value() );
		$attributes->add_class( 'small-text', 'field-kit__amount-value' );
		$attributes->set_if( $field->has( 'min' ), 'min', $field->get( 'min' ) );
		$attributes->set_if( $field->has( 'max' ), 'max', $field->get( 'max' ) );
		$attributes->set_if( $field->has( 'step' ), 'step', $field->get( 'step' ) );
		$attributes->set_if( '' !== $field->placeholder(), 'placeholder', $field->placeholder() );

		$unit_id = $field->input_id() . '_unit';
		$current = (string) $field->get( 'current_type', $field->get( 'type_default', 'percent' ) );
		$options = '';

		foreach ( $this->unit_options( $field ) as $value => $label ) {
			$options .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $value ),
				(string) $value === $current ? ' selected' : '',
				esc_html( (string) $label )
			);
		}

		return sprintf(
			'<div class="field-kit__amount"><input%s />%s<select id="%s" name="%s" class="field-kit__amount-unit">%s</select></div>',
			$attributes->render(),
			$this->sub_label( $unit_id, __( 'Unit', 'arraypress' ), false ),
			esc_attr( $unit_id ),
			esc_attr( (string) $field->get( 'type_meta_key', $field->key() . '_type' ) ),
			$options
		);
	}

	/**
	 * The unit options, resolved if supplied as a callable.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, string>
	 */
	private function unit_options( Field $field ): array {
		$options = $field->get( 'type_options', [] );

		if ( is_callable( $options ) ) {
			$options = $options( $field );
		}

		return is_array( $options ) && [] !== $options
			? $options
			: [
				'percent' => '%',
				'flat' => '$',
			];
	}

	/**
	 * Coerce the amount.
	 *
	 * The unit is a separate key and is sanitized by whoever writes it, since
	 * only the context knows where it goes.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return float|string
	 */
	public function sanitize( mixed $value, Field $field ): float|string {
		if ( ! is_numeric( $value ) ) {
			return '';
		}

		$amount = (float) $value;
		$min    = $field->get( 'min' );
		$max    = $field->get( 'max' );

		if ( null !== $min ) {
			$amount = max( (float) $min, $amount );
		}

		if ( null !== $max ) {
			$amount = min( (float) $max, $amount );
		}

		return $amount;
	}

	/**
	 * Takes a placeholder on the amount box.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return true;
	}

	/**
	 * A number, or the empty string when nothing was entered.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [ 'type' => [ 'number', 'string' ] ];
	}

	/**
	 * Fits an inline row.
	 *
	 * A number and a unit: two controls on one line.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return true;
	}
}
