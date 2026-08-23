<?php
/**
 * Colour Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A colour picker backed by WordPress's own control.
 */
final class ColorType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * The picker script upgrades a text input; a native `color` input would
	 * not give the alpha channel or the palette.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'text';
	}

	/**
	 * Render the input.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->add_class( 'field-kit__color' );
		$attributes->set_if( $field->has( 'palette' ), 'data-palette', $field->get( 'palette' ) );
		$attributes->set_if( (bool) $field->get( 'alpha' ), 'data-alpha-enabled', 'true' );

		return parent::render( $field, $attributes );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * sanitize_hex_color() returns null for anything that is not a hex
	 * colour, including the rgba() a palette with alpha produces, so that
	 * form is checked before falling back.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( $field->get( 'alpha' ) && preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/i', $value ) ) {
			return $value;
		}

		return (string) sanitize_hex_color( $value );
	}

	/**
	 * Scripts and styles the picker needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		// jquery is explicit: wp-color-picker is a jQuery plugin, and
		// enqueueing it without jQuery present leaves the field a plain text
		// input with no error anywhere.
		return [
			'scripts' => [ 'jquery', 'wp-color-picker' ],
			'styles'  => [ 'wp-color-picker' ],
		];
	}
}
