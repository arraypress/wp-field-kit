<?php
/**
 * Raw HTML Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * Markup supplied by the consumer.
 *
 * Passed through wp_kses_post() rather than echoed raw: the content comes
 * from plugin code rather than a request, but a field type that prints
 * whatever it is handed is the kind of thing that later gets pointed at a
 * database value.
 */
final class HtmlType extends AbstractLayoutType {

	/**
	 * Render the markup.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$content = $field->get( 'html', $field->get( 'content', '' ) );

		if ( is_callable( $content ) ) {
			$content = $content( $field );
		}

		return sprintf(
			'<div class="field-kit__html">%s</div>',
			wp_kses_post( (string) $content )
		);
	}
}
