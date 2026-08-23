<?php
/**
 * Heading Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A section heading.
 *
 * The level is configurable and defaults to h3, because a heading that skips
 * levels breaks the document outline screen-reader users navigate by. It is
 * rendered as a real heading rather than a styled div for the same reason.
 */
final class HeadingType extends AbstractLayoutType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [ 'level' => 3 ];
	}

	/**
	 * Render the heading.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$level = (int) $field->get( 'level', 3 );
		$level = min( 6, max( 2, $level ) );

		// The description is not rendered here. The renderer appends it for
		// every field, and printing it as well is what showed it twice.
		return sprintf(
			'<h%1$d class="field-kit__heading">%2$s</h%1$d>',
			$level,
			esc_html( $field->label() )
		);
	}
}
