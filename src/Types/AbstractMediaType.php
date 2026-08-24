<?php
/**
 * Base Media Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * Anything backed by the media library.
 *
 * The stored value is an attachment id, held in a hidden input, with a real
 * button to open the picker and a real button to clear it. Buttons rather
 * than clickable divs or anchors with `href="#"`: they are focusable and
 * respond to Enter and Space without any of that being rebuilt.
 */
abstract class AbstractMediaType extends AbstractType {

	/**
	 * The media frame's title.
	 *
	 * @return string
	 */
	abstract protected function frame_title(): string;

	/**
	 * The button that opens the picker.
	 *
	 * @return string
	 */
	abstract protected function choose_label(): string;

	/**
	 * Mime types the picker offers, or an empty string for all.
	 *
	 * @return string
	 */
	protected function mime_type(): string {
		return '';
	}

	/**
	 * Render the preview, the hidden value and the controls.
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
		$wrapper->set( 'data-choose-label', $this->choose_label() );
		$wrapper->set_if( '' !== $this->mime_type(), 'data-mime-type', $this->mime_type() );
		$wrapper->set_if( $field->has( 'library' ), 'data-library', $field->get( 'library' ) );

		return sprintf(
			'<div%s>%s<input%s />%s</div>',
			$wrapper->render(),
			$this->render_preview( $field ),
			$attributes->render(),
			$this->render_controls( $field )
		);
	}

	/**
	 * The current selection, shown above the controls.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	abstract protected function render_preview( Field $field ): string;

	/**
	 * The choose and clear buttons.
	 *
	 * Each button names the field it acts on for assistive technology, since
	 * a screen holding several media fields would otherwise present a list of
	 * identical "Remove" buttons.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_controls( Field $field ): string {
		// absint rather than a string comparison, so an id of 0 left behind
		// by an older save is treated as nothing selected.
		$has_value = $this->has_selection( $field );

		$clear = new Attributes();
		$clear->set( 'type', 'button' );
		$clear->add_class( 'button', 'button-link-delete', 'field-kit__media-clear' );
		$clear->set_if( ! $has_value, 'hidden', true );
		$clear->set(
			'aria-label',
			sprintf(
				/* translators: %s: field label */
				__( 'Remove the selection for %s', 'arraypress' ),
				$field->label()
			)
		);

		$choose = new Attributes();
		$choose->set( 'type', 'button' );
		$choose->add_class( 'button', 'field-kit__media-choose' );
		$choose->set(
			'aria-label',
			sprintf(
				/* translators: %s: field label */
				__( 'Choose a file for %s', 'arraypress' ),
				$field->label()
			)
		);

		return sprintf(
			'<p class="field-kit__media-actions"><button%s>%s</button> <button%s>%s</button></p>',
			$choose->render(),
			esc_html( $this->choose_label() ),
			$clear->render(),
			esc_html__( 'Remove', 'arraypress' )
		);
	}

	/**
	 * Whether anything is actually selected.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function has_selection( Field $field ): bool {
		return absint( $field->value() ) > 0;
	}

	/**
	 * Coerce a submitted value to an attachment id.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * Wide on purpose: most media fields store an attachment id, but
	 * file_url stores a URL and gallery stores a list, and a narrowed type
	 * here would stop either subclass declaring what it actually stores.
	 *
	 * @return mixed
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		$id = absint( $value );

		// An emptied field must come back as '' rather than 0. Zero is a
		// value as far as the field set is concerned — an unticked checkbox
		// deliberately stores it — so returning it here persisted a
		// meaningless attachment id, and the Remove button reappeared on the
		// next load because '0' is not ''.
		return 0 === $id ? '' : $id;
	}

	/**
	 * Scripts the media frame needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		return [
			'scripts' => [ 'media-upload', 'media-views' ],
			'styles'  => [],
		];
	}

	/**
	 * An attachment id, or the empty string when nothing is chosen.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [ 'type' => [ 'integer', 'string' ] ];
	}
}
