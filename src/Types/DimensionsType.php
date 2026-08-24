<?php
/**
 * Dimensions Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * Width, height and optionally depth, with a unit.
 *
 * Each box carries its own visible label. Placeholder-only labelling is the
 * usual shortcut here and it fails twice over: the placeholder disappears the
 * moment someone types, and it is not a label at all to assistive technology.
 */
final class DimensionsType extends AbstractType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'parts' => [ 'width', 'height' ],
			'unit'  => '',
			'min'   => 0,
			'step'  => 1,
		];
	}

	/**
	 * The label for each part.
	 *
	 * @return array<string, string>
	 */
	private function part_labels(): array {
		return [
			'width'  => __( 'Width', 'arraypress' ),
			'height' => __( 'Height', 'arraypress' ),
			'depth'  => __( 'Depth', 'arraypress' ),
			'length' => __( 'Length', 'arraypress' ),
		];
	}

	/**
	 * Render the boxes.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$value  = is_array( $field->value() ) ? $field->value() : [];
		$labels = $this->part_labels();
		$parts  = (array) $field->get( 'parts', [ 'width', 'height' ] );
		$unit   = (string) $field->get( 'unit', '' );
		$markup = '';
		$first  = true;

		foreach ( $parts as $part ) {
			$part = (string) $part;
			$box  = $this->part_attributes( $attributes, $part, $first );

			$box->set( 'type', 'number' );
			$box->set( 'value', (string) ( $value[ $part ] ?? '' ) );
			$box->add_class( 'small-text' );
			$box->set_if( $field->has( 'min' ), 'min', $field->get( 'min' ) );
			$box->set_if( $field->has( 'max' ), 'max', $field->get( 'max' ) );
			$box->set_if( $field->has( 'step' ), 'step', $field->get( 'step' ) );

			$markup .= sprintf(
				'<div class="field-kit__dimensions-part">%s<input%s /></div>',
				$this->sub_label( (string) $box->get( 'id' ), $labels[ $part ] ?? ucfirst( $part ) ),
				$box->render()
			);

			$first = false;
		}

		if ( '' !== $unit ) {
			$markup .= sprintf(
				'<span class="field-kit__dimensions-unit" aria-hidden="true">%s</span>',
				esc_html( $unit )
			);
		}

		return sprintf( '<div class="field-kit__dimensions">%s</div>', $markup );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return array<string, float|string>
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$value = is_array( $value ) ? $value : [];
		$clean = [];

		foreach ( (array) $field->get( 'parts', [ 'width', 'height' ] ) as $part ) {
			$part           = (string) $part;
			$raw            = $value[ $part ] ?? '';
			$clean[ $part ] = is_numeric( $raw ) ? (float) $raw : '';
		}

		return $clean;
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
	 * One object holding each part and the unit.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		$properties = [];

		foreach ( (array) $field->get( 'parts', [ 'width', 'height' ] ) as $part ) {
			$properties[ (string) $part ] = [ 'type' => [ 'number', 'string' ] ];
		}

		$properties['unit'] = [ 'type' => 'string' ];

		return [
			'type'       => 'object',
			'properties' => $properties,
		];
	}
}
