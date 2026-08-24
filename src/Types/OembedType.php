<?php
/**
 * oEmbed Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A URL that resolves to an embed, with a live preview beside it.
 *
 * The preview region is `aria-live="polite"`, so resolving an embed is
 * announced rather than being a change only sighted users notice.
 */
final class OembedType extends AbstractInputType {

	/**
	 * The HTML input type.
	 *
	 * @return string
	 */
	protected function input_type(): string {
		return 'url';
	}

	/**
	 * Render the input and its preview.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->add_class( 'field-kit__oembed-input' );

		return sprintf(
			'<div class="field-kit__oembed">%s<div class="field-kit__oembed-preview" aria-live="polite">%s</div></div>',
			parent::render( $field, $attributes ),
			$this->render_preview( $field )
		);
	}

	/**
	 * The current embed, if the URL resolves to one.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function render_preview( Field $field ): string {
		$url = (string) $field->value();

		if ( '' === $url ) {
			return '';
		}

		$embed = wp_oembed_get( $url );

		return false === $embed
			? sprintf( '<p class="description">%s</p>', esc_html__( 'That URL could not be embedded.', 'arraypress' ) )
			: (string) $embed;
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
		return esc_url_raw( (string) $value );
	}

	/**
	 * Does not fit an inline row.
	 *
	 * The preview fetches on change, which is a request per row of a list table.
	 *
	 * @return bool
	 */
	public function supports_inline(): bool {
		return false;
	}
}
