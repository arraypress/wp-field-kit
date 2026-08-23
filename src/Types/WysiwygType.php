<?php
/**
 * WYSIWYG Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * The core visual editor.
 */
final class WysiwygType extends AbstractType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'rows'       => 10,
			'media_buttons' => true,
			'teeny'      => false,
		];
	}

	/**
	 * Render the editor.
	 *
	 * wp_editor() echoes and cannot be told not to, so it is buffered. The
	 * editor id must be a bare lowercase identifier — TinyMCE rejects the
	 * bracketed names nested fields produce — so the real name is carried on
	 * a separate hidden-safe attribute via textarea_name.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$editor_id = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field->input_id() ) ) ?: 'fieldkiteditor';

		ob_start();

		wp_editor(
			(string) $field->value(),
			$editor_id,
			[
				'textarea_name' => $field->input_name(),
				'textarea_rows' => (int) $field->get( 'rows', 10 ),
				'media_buttons' => (bool) $field->get( 'media_buttons', true ),
				'teeny'         => (bool) $field->get( 'teeny', false ),
				'editor_class'  => 'field-kit__wysiwyg',
			]
		);

		$editor = (string) ob_get_clean();

		// wp_editor() builds its own <textarea> and offers no way to put
		// attributes on it, so the accessibility associations the renderer
		// prepared would be lost. They are injected into the markup we
		// buffered ourselves rather than left off the control.
		$carry = new Attributes();

		foreach ( [ 'aria-describedby', 'aria-required', 'aria-invalid', 'required' ] as $name ) {
			$carry->set_if( $attributes->has( $name ), $name, $attributes->get( $name ) );
		}

		$extra = $carry->render();

		if ( '' !== $extra ) {
			$editor = (string) preg_replace(
				'/<textarea\b/',
				'<textarea' . $extra,
				$editor,
				1
			);
		}

		return sprintf(
			'<div class="field-kit__wysiwyg-wrap" data-field-id="%s">%s</div>',
			esc_attr( $field->input_id() ),
			$editor
		);
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		return wp_kses_post( (string) $value );
	}
}
