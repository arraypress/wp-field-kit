<?php
/**
 * Gallery Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * An ordered set of attachments.
 *
 * Reordering is offered two ways. Dragging is the fast path, but a drag-only
 * control cannot be operated from a keyboard at all, so every item also
 * carries move-back and move-forward buttons. That is not a fallback for when
 * the script fails — it is the only way some people can reorder anything, and
 * it is the half that reordering UIs usually omit.
 *
 * Each item is a list item inside an ordered list, so position is conveyed
 * structurally rather than only by where it sits on screen.
 */
final class GalleryType extends AbstractMediaType {

	/**
	 * The media frame's title.
	 *
	 * @return string
	 */
	protected function frame_title(): string {
		return __( 'Choose images', 'arraypress' );
	}

	/**
	 * The button that opens the picker.
	 *
	 * @return string
	 */
	protected function choose_label(): string {
		return __( 'Add images', 'arraypress' );
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
	 * Render the list, the hidden value and the controls.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$ids = $this->ids( $field );

		$attributes->set( 'type', 'hidden' );
		$attributes->set( 'value', implode( ',', $ids ) );
		$attributes->add_class( 'field-kit__media-value' );

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__media', 'field-kit__gallery' );
		$wrapper->set( 'data-frame-title', $this->frame_title() );
		$wrapper->set( 'data-mime-type', 'image' );

		return sprintf(
			'<div%s>%s<input%s />%s</div>',
			$wrapper->render(),
			$this->render_preview( $field ),
			$attributes->render(),
			$this->render_controls( $field )
		);
	}

	/**
	 * Whether anything is selected.
	 *
	 * A gallery holds a list, so a single absint would only ever see the
	 * first item.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function has_selection( Field $field ): bool {
		return [] !== $this->ids( $field );
	}

	/**
	 * Render the ordered list of items.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	protected function render_preview( Field $field ): string {
		$ids   = $this->ids( $field );
		$total = count( $ids );
		$items = '';

		foreach ( $ids as $position => $id ) {
			$items .= $this->render_item( $field, $id, $position, $total );
		}

		return sprintf(
			'<ol class="field-kit__gallery-items" data-empty="%s">%s</ol>',
			$total > 0 ? 'false' : 'true',
			$items
		);
	}

	/**
	 * Render one item and its reorder controls.
	 *
	 * @param Field $field    The field.
	 * @param int   $id       Attachment id.
	 * @param int   $position Zero-based position.
	 * @param int   $total    Total items.
	 *
	 * @return string
	 */
	private function render_item( Field $field, int $id, int $position, int $total ): string {
		$name = (string) get_the_title( $id );
		$name = '' === $name ? (string) $id : $name;

		return sprintf(
			'<li class="field-kit__gallery-item" data-id="%d">%s' .
			'<span class="field-kit__drag-handle dashicons dashicons-menu" aria-hidden="true"></span>' .
			'<span class="field-kit__gallery-position screen-reader-text">%s</span>' .
			'<span class="field-kit__gallery-actions">%s%s%s</span></li>',
			$id,
			wp_get_attachment_image( $id, 'thumbnail', false, [ 'alt' => '' ] ),
			esc_html(
				sprintf(
					/* translators: 1: item position, 2: total items, 3: item name */
					__( 'Item %1$d of %2$d: %3$s', 'arraypress' ),
					$position + 1,
					$total,
					$name
				)
			),
			$this->move_button( $name, 'up', $position < 1 ),
			$this->move_button( $name, 'down', $position >= $total - 1 ),
			$this->remove_button( $name )
		);
	}

	/**
	 * A reorder button.
	 *
	 * @param string $name     Item name, for the accessible label.
	 * @param string $direction Either "up" or "down".
	 * @param bool   $disabled Whether the move is possible.
	 *
	 * @return string
	 */
	private function move_button( string $name, string $direction, bool $disabled ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button-link', 'field-kit__gallery-move' );
		$button->set( 'data-direction', $direction );
		$button->set_if( $disabled, 'disabled', true );
		$button->set(
			'aria-label',
			'up' === $direction
				/* translators: %s: item name */
				? sprintf( __( 'Move %s earlier', 'arraypress' ), $name )
				/* translators: %s: item name */
				: sprintf( __( 'Move %s later', 'arraypress' ), $name )
		);

		return sprintf(
			'<button%s><span class="dashicons dashicons-arrow-%s-alt2" aria-hidden="true"></span></button>',
			$button->render(),
			'up' === $direction ? 'up' : 'down'
		);
	}

	/**
	 * The remove button for one item.
	 *
	 * @param string $name Item name, for the accessible label.
	 *
	 * @return string
	 */
	private function remove_button( string $name ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button-link', 'field-kit__gallery-remove' );
		/* translators: %s: item name */
		$button->set( 'aria-label', sprintf( __( 'Remove %s', 'arraypress' ), $name ) );

		return sprintf(
			'<button%s><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>',
			$button->render()
		);
	}

	/**
	 * The current ids, however they were stored.
	 *
	 * Accepts both an array and the comma-separated string the hidden input
	 * posts, because config written for either predecessor should keep
	 * working.
	 *
	 * @param Field $field The field.
	 *
	 * @return int[]
	 */
	private function ids( Field $field ): array {
		$value = $field->value();
		$value = is_array( $value ) ? $value : explode( ',', (string) $value );

		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/**
	 * Coerce a submitted value to a list of attachment ids.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return int[]
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$value = is_array( $value ) ? $value : explode( ',', (string) $value );

		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/**
	 * Scripts and styles this needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		return [
			'scripts' => [ 'media-upload', 'media-views' ],
			'styles'  => [ 'dashicons' ],
		];
	}

	/**
	 * A list of attachment ids.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'  => 'array',
			'items' => [ 'type' => 'integer' ],
		];
	}
}
