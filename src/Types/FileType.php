<?php
/**
 * File Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

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
	 * Render the file name.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_preview( Field $field ): string {
		$id = absint( $field->value() );

		if ( 0 === $id ) {
			return '<div class="field-kit__media-preview" data-empty="true"></div>';
		}

		$url  = (string) wp_get_attachment_url( $id );
		$name = '' === $url ? '' : basename( $url );

		return sprintf(
			'<div class="field-kit__media-preview"><span class="field-kit__media-filename">%s</span></div>',
			esc_html( $name )
		);
	}
}
