<?php
/**
 * Link Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A URL, its link text, and whether it opens in a new tab.
 *
 * The two predecessor libraries stored this differently — setting-fields used
 * `text` and defaulted the target to `_self`, post-fields used `title` and
 * defaulted it to an empty string — so the same field type produced
 * incompatible data depending on which library registered it. `text` wins as
 * the canonical key, and `title` is read on the way in so existing data keeps
 * resolving.
 *
 * The three inputs are wrapped in a fieldset: a single `<label for>` cannot
 * describe three controls, and without it a screen reader announces "URL"
 * with no idea which link it belongs to.
 */
final class LinkType extends AbstractType {

	/**
	 * Render the three parts.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$value = $this->normalize( $field->value() );

		$url = $this->part_attributes( $attributes, 'url', true );
		$url->set( 'type', 'url' );
		$url->set( 'value', $value['url'] );
		$url->add_class( 'regular-text' );
		$url->set( 'placeholder', 'https://' );

		$text = $this->part_attributes( $attributes, 'text' );
		$text->set( 'type', 'text' );
		$text->set( 'value', $value['text'] );
		$text->add_class( 'regular-text' );

		$target = $this->part_attributes( $attributes, 'target' );
		$target->set( 'type', 'checkbox' );
		$target->set( 'value', '_blank' );
		$target->set_if( '_blank' === $value['target'], 'checked', true );

		// The shape core's own link dialog uses: each input under its own
		// small label, then the new-tab checkbox on its own line. Divs rather
		// than paragraphs, because a <p> here inherited the description's
		// spacing and left the three parts further apart than the fields
		// around them.
		return sprintf(
			'<div class="field-kit__link">' .
			'<div class="field-kit__link-part">%s<input%s /></div>' .
			'<div class="field-kit__link-part">%s<input%s /></div>' .
			'<div class="field-kit__link-part">' .
			'<label class="field-kit__checkbox-label" for="%s"><input%s /> %s</label>' .
			'</div></div>',
			$this->sub_label( (string) $url->get( 'id' ), __( 'URL', 'arraypress' ) ),
			$url->render(),
			$this->sub_label( (string) $text->get( 'id' ), __( 'Link text', 'arraypress' ) ),
			$text->render(),
			esc_attr( (string) $target->get( 'id' ) ),
			$target->render(),
			esc_html__( 'Open in a new tab', 'arraypress' )
		);
	}

	/**
	 * Read a stored value in either library's shape.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return array{url: string, text: string, target: string}
	 */
	private function normalize( mixed $value ): array {
		$value = is_array( $value ) ? $value : [];

		return [
			'url'    => (string) ( $value['url'] ?? '' ),
			// `title` is post-fields' spelling of the same thing.
			'text'   => (string) ( $value['text'] ?? $value['title'] ?? '' ),
			'target' => '_blank' === ( $value['target'] ?? '' ) ? '_blank' : '',
		];
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return array{url: string, text: string, target: string}
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$value = $this->normalize( is_array( $value ) ? $value : [] );

		return [
			'url'    => esc_url_raw( $value['url'] ),
			'text'   => sanitize_text_field( $value['text'] ),
			'target' => '_blank' === $value['target'] ? '_blank' : '',
		];
	}

	/**
	 * Needs a fieldset and legend.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return true;
	}

	/**
	 * A url, its text and its target, under one key.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'       => 'object',
			'properties' => [
				'url'    => [
					'type'   => 'string',
					'format' => 'uri',
				],
				'text'   => [ 'type' => 'string' ],
				'target' => [
					'type' => 'string',
					'enum' => [ '', '_blank' ],
				],
			],
		];
	}
}
