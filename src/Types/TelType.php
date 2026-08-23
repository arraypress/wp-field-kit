<?php
/**
 * Tel Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A telephone input.
 */
final class TelType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'tel';
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		return sanitize_text_field( (string) $value );
	}
}
