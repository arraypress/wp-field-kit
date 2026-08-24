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
 * A number with a unit attached to it.
 *
 * Two shapes, because they are the same control with the unit fixed or not.
 *
 * A *chosen* unit — a percentage or a flat amount, a currency, a weight — is a
 * select beside the number, and is written to a second key named by
 * `type_meta_key`, because consumers query and sort on it independently of the
 * amount. Both controls are labelled: a bare unit select beside a number is
 * announced as an unlabelled combo box.
 *
 * A *fixed* unit — `'unit' => '%'` — is static text rather than a control,
 * marked aria-hidden because it is decoration on a field whose label already
 * says what it measures. Nothing is written for it; there is nothing to write.
 *
 * `unit_position` puts either on the left instead, which is what a currency
 * wants in most of the world's locales.
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
			'type_options'  => [
				'percent' => '%',
				'flat'    => '$',
			],
			'type_default'  => 'percent',
			'unit'          => '',
			'unit_position' => 'suffix',
			'min'           => 0,
			'step'          => 0.01,
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

		$prefix = 'prefix' === (string) $field->get( 'unit_position', 'suffix' );
		$fixed  = (string) $field->get( 'unit', '' );

		$unit = '' === $fixed ? $this->unit_select( $field ) : sprintf(
			'<span class="field-kit__amount-unit field-kit__amount-unit--fixed" aria-hidden="true">%s</span>',
			esc_html( $fixed )
		);

		$input = sprintf( '<input%s />', $attributes->render() );

		return sprintf(
			'<div class="field-kit__amount%s">%s</div>',
			$prefix ? ' field-kit__amount--prefix' : '',
			$prefix ? $unit . $input : $input . $unit
		);
	}

	/**
	 * The unit as a select, for a unit the person chooses.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function unit_select( Field $field ): string {
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
			'%s<select id="%s" name="%s" class="field-kit__amount-unit">%s</select>',
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

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'current_type', 'max', 'min', 'step', 'type_default', 'type_meta_key', 'type_options', 'unit', 'unit_position' ]
		);
	}
}
