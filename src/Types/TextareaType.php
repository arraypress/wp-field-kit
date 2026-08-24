<?php
/**
 * Textarea Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A multi-line text control.
 */
class TextareaType extends AbstractType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [ 'rows' => 5 ];
	}

	/**
	 * Render the textarea.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->add_class( 'large-text', 'field-kit__textarea' );
		$attributes->set( 'rows', (int) $field->get( 'rows', 5 ) );
		$attributes->set_if( $field->has( 'cols' ), 'cols', $field->get( 'cols' ) );
		$attributes->set_if( $field->has( 'maxlength' ), 'maxlength', $field->get( 'maxlength' ) );

		return sprintf(
			'<textarea%s>%s</textarea>',
			$attributes->render(),
			esc_textarea( (string) $field->value() )
		);
	}

	/**
	 * Coerce a submitted value.
	 *
	 * sanitize_textarea_field() rather than sanitize_text_field(), which
	 * collapses the newlines the control exists to accept.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * A textarea takes a placeholder.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return true;
	}

	/**
	 * Fits an inline row.
	 *
	 * A plain textarea, which needs nothing started.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return true;
	}
}
