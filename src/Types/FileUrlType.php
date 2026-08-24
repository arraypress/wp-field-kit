<?php
/**
 * File URL Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A file chosen from the media library but stored as its URL.
 *
 * Distinct from `file`, which stores an attachment id. The input stays a
 * visible, editable text field so a URL can also be typed or pasted — the
 * picker is an affordance, not the only way in.
 */
final class FileUrlType extends FileType {

	/**
	 * Render an editable URL input alongside the picker.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->set( 'type', 'url' );
		$attributes->set( 'value', (string) $field->value() );
		$attributes->add_class( 'regular-text', 'field-kit__media-value' );
		$attributes->set_if( '' !== $field->placeholder(), 'placeholder', $field->placeholder() );

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__media', 'field-kit__media--file_url' );
		$wrapper->set( 'data-frame-title', $this->frame_title() );
		$wrapper->set( 'data-returns', 'url' );

		return sprintf(
			'<div%s><input%s />%s</div>',
			$wrapper->render(),
			$attributes->render(),
			$this->render_controls( $field )
		);
	}

	/**
	 * Whether a URL is set.
	 *
	 * This one stores a URL rather than an attachment id, so the id check
	 * the other media types use would call every URL empty.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function has_selection( Field $field ): bool {
		return '' !== trim( (string) $field->value() );
	}

	/**
	 * Coerce a submitted value to a URL.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public function sanitize( mixed $value, Field $field ): string {
		return esc_url_raw( (string) $value );
	}

	/**
	 * A URL field takes a placeholder.
	 *
	 * @return bool
	 */
	public function supports_placeholder(): bool {
		return true;
	}

	/**
	 * A URL rather than an attachment id.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'   => 'string',
			'format' => 'uri',
		];
	}
}
