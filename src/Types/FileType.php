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
	protected function frame_title( ?Field $field = null ): string {
		return __( 'Choose a file', 'arraypress' );
	}

	/**
	 * The button that opens the picker.
	 *
	 * @return string
	 */
	protected function choose_label( ?Field $field = null ): string {
		return __( 'Choose file', 'arraypress' );
	}

	/**
	 * Render the field.
	 *
	 * Standalone, this is what every other media field is: the file name,
	 * then a labelled "Choose file" and a labelled "Remove" beneath it. On a
	 * settings or term screen there is a whole column of space for that, and
	 * two words are easier to act on than two glyphs.
	 *
	 * Inside a repeater row there is one line and no column of space, and
	 * the labelled pair wrapped below the input and doubled the height of
	 * every row. There the picker moves inside the input, the way EDD draws
	 * a download's file row.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		if ( ! $field->get( 'inline', false ) ) {
			return parent::render( $field, $attributes );
		}

		return $this->render_inline( $field, $attributes );
	}

	/**
	 * The one-line form: an id in a hidden input, a name in a readonly one.
	 *
	 * An attachment id is nothing to show anyone, so the visible input
	 * carries the file name. Readonly rather than disabled: a disabled input
	 * is skipped by the keyboard entirely, so the name could not be read or
	 * copied by anyone tabbing through the form.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	protected function render_inline( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'hidden' );
		$attributes->set( 'value', (string) $field->value() );
		$attributes->add_class( 'field-kit__media-value' );

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__media', 'field-kit__media--' . $this->id() );
		$wrapper->set( 'data-frame-title', $this->frame_title() );
		$wrapper->set_if( '' !== $this->mime_type(), 'data-mime-type', $this->mime_type() );
		$wrapper->set_if( $field->has( 'library' ), 'data-library', $field->get( 'library' ) );

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
				$this->choose_button( $field ) . $this->clear_button( $field, $this->has_selection( $field ) ),
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
	 * The file name, above the controls.
	 *
	 * Only the standalone form draws this: the inline one puts the name in
	 * the input itself, where a preview block would be a second copy of it.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_preview( Field $field ): string {
		$name = $this->filename( $field );

		if ( '' === $name ) {
			return '<div class="field-kit__media-preview" data-empty="true"></div>';
		}

		return sprintf(
			'<div class="field-kit__media-preview"><span class="field-kit__media-filename">%s</span></div>',
			esc_html( $name )
		);
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
