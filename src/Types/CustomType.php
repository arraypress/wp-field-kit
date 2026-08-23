<?php
/**
 * Custom Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * An escape hatch: the consumer renders and sanitizes the control itself.
 *
 * The renderer still supplies the wrapper, label and accessibility
 * associations, and hands the prepared attributes to the callback — so even a
 * hand-written control inherits the labelling rather than starting from
 * nothing. A callback that ignores them is on its own, which is why they are
 * passed rather than merely available.
 */
final class CustomType extends AbstractType {

	/**
	 * Render through the configured callback.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$callback = $field->get( 'render_callback' );

		if ( ! is_callable( $callback ) ) {
			return '';
		}

		ob_start();

		$returned = $callback( $field, $attributes );
		$echoed   = (string) ob_get_clean();

		// A callback may echo or return; supporting both means neither
		// convention silently renders nothing.
		return '' !== $echoed ? $echoed : (string) $returned;
	}

	/**
	 * Coerce a submitted value through the configured callback.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		$callback = $field->get( 'sanitize_callback' );

		if ( is_callable( $callback ) ) {
			return $callback( $value, $field );
		}

		// No callback means no idea what shape this is, so the conservative
		// default applies rather than storing it untouched.
		return is_array( $value )
			? array_map( 'sanitize_text_field', $value )
			: sanitize_text_field( (string) $value );
	}
}
