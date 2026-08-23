<?php
/**
 * Image Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Field;

/**
 * A single image from the media library.
 */
final class ImageType extends AbstractMediaType {

	/**
	 * The media frame's title.
	 *
	 * @return string
	 */
	protected function frame_title(): string {
		return __( 'Choose an image', 'arraypress' );
	}

	/**
	 * The button that opens the picker.
	 *
	 * @return string
	 */
	protected function choose_label(): string {
		return __( 'Choose image', 'arraypress' );
	}

	/**
	 * Only images.
	 *
	 * @return string
	 */
	protected function mime_type(): string {
		return 'image';
	}

	/**
	 * Render the thumbnail.
	 *
	 * The alt text comes from the attachment's own alt field, so a decorative
	 * image stays decorative and a described one keeps its description. An
	 * empty preview is not announced at all rather than as "image".
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

		$size = (string) $field->get( 'preview_size', 'medium' );
		$alt  = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

		return sprintf(
			'<div class="field-kit__media-preview">%s</div>',
			wp_get_attachment_image( $id, $size, false, [ 'alt' => $alt ] )
		);
	}
}
