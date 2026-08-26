<?php
/**
 * File Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A single file from the media library, stored as an attachment id.
 */
class FileType extends AbstractMediaType {

	/**
	 * The media frame's title.
	 *
	 * @return string
	 */
	protected function frame_title(): string {
		return __( 'Choose a file', 'arraypress' );
	}

	/**
	 * The button that opens the picker.
	 *
	 * @return string
	 */
	protected function choose_label(): string {
		return __( 'Choose file', 'arraypress' );
	}

	/**
	 * Render the stored id, the chosen file's name, and the controls.
	 *
	 * The value is an attachment id and an id is not worth showing anyone,
	 * so it stays in a hidden input and a readonly text input carries the
	 * file name. That gives this field the same shape as `file_url` — one
	 * input with its controls inside the right-hand end — instead of a bare
	 * line of text above two full-width buttons.
	 *
	 * Readonly rather than disabled: a disabled input is skipped by the
	 * keyboard entirely, so the file name could not be read or copied by
	 * anyone tabbing through the form.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'hidden' );
		$attributes->set( 'value', (string) $field->value() );
		$attributes->add_class( 'field-kit__media-value' );

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__media', 'field-kit__media--' . $this->id() );
		$wrapper->set( 'data-frame-title', $this->frame_title() );
		$wrapper->set_if( '' !== $this->mime_type(), 'data-mime-type', $this->mime_type() );
		$wrapper->set_if( $field->has( 'library' ), 'data-library', $field->get( 'library' ) );

		$has_value = $this->has_selection( $field );

		$name = new Attributes();
		$name->set( 'type', 'text' );
		$name->add_class( 'regular-text', 'field-kit__media-filename' );
		$name->set( 'value', $this->filename( $field ) );
		$name->set( 'readonly', true );
		$name->set( 'tabindex', '-1' );
		$name->set(
			'placeholder',
			'' !== $field->placeholder() ? $field->placeholder() : __( 'No file chosen', 'arraypress' )
		);
		// It has no <label> of its own — the field's label points at the
		// hidden input that actually holds the value.
		$name->set(
			'aria-label',
			sprintf(
				/* translators: %s: field label */
				__( 'Chosen file for %s', 'arraypress' ),
				$field->label()
			)
		);

		return sprintf(
			'<div%s><input%s />%s</div>',
			$wrapper->render(),
			$attributes->render(),
			$this->input_group(
				sprintf( '<input%s />', $name->render() ),
				$this->choose_button( $field ) . $this->clear_button( $field, $has_value ),
				2
			)
		);
	}

	/**
	 * The chosen file's name, or the empty string.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function filename( Field $field ): string {
		$id = absint( $field->value() );

		if ( 0 === $id ) {
			return '';
		}

		$url = (string) wp_get_attachment_url( $id );

		return '' === $url ? '' : basename( $url );
	}

	/**
	 * Nothing above the input.
	 *
	 * The file name is in the input itself, so a preview block would be a
	 * second copy of it.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_preview( Field $field ): string {
		return '';
	}

	/**
	 * A file field takes a placeholder, shown when nothing is chosen.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return true;
	}
}
