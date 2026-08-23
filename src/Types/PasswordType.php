<?php
/**
 * Password Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A password input. The value is never echoed back into the markup.
 */
final class PasswordType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'password';
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

	/**
	 * Never render the stored value back into the page.
	 *
	 * @param \ArrayPress\FieldKit\Field $field The field.
	 *
	 * @return string
	 */
	protected function render_value( \ArrayPress\FieldKit\Field $field ): string {
		return '';
	}
}
