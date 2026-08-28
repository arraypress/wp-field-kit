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
 * Images by default, because that is what a gallery usually is, but `mime_type`
 * widens it: a set of audio previews is the same control with a different
 * picker and nothing to show a thumbnail of. An item that is not an image is
 * drawn as its name, since wp_get_attachment_image() returns an empty string
 * for an MP3 and the alternative is a row with nothing in it.
 *
 * Reordering is a drag handle, which is also what operates it from a keyboard:
 * focus it and press the arrow keys. It replaced a pair of chevrons that did
 * the same job twice and cost a click per position.
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
	 * The media frame's title, for a field that may not hold images.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function frame_title_for( Field $field ): string {
		$title = (string) $field->get( 'frame_title', '' );

		if ( '' !== $title ) {
			return $title;
		}

		return 'image' === $this->mime_type_for( $field )
			? $this->frame_title()
			: __( 'Choose files', 'arraypress' );
	}

	/**
	 * What this field actually accepts.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function mime_type_for( Field $field ): string {
		return (string) $field->get( 'mime_type', $this->mime_type() );
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
	 * The button that opens the picker, for a field that may not hold images.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function choose_label_for( Field $field ): string {
		$label = (string) $field->get( 'add_label', '' );

		if ( '' !== $label ) {
			return $label;
		}

		return 'image' === $this->mime_type_for( $field )
			? $this->choose_label()
			: __( 'Add files', 'arraypress' );
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
		$wrapper->set( 'data-frame-title', $this->frame_title_for( $field ) );

		// From the field, not a literal. This was hardcoded to `image` while
		// mime_type() sat beside it unread, so a subclass could change what
		// the field accepted everywhere except in the picker it opens.
		$wrapper->set( 'data-mime-type', $this->mime_type_for( $field ) );
		$wrapper->set( 'data-max-items', (int) $field->get( 'max_items', 0 ) );

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

		$image = wp_get_attachment_image( $id, (string) $field->get( 'preview_size', 'medium' ), false, [ 'alt' => '' ] );

		// An MP3 has no thumbnail, and wp_get_attachment_image() says so by
		// returning an empty string -- which drew an item with nothing in it
		// but its buttons.
		$body = '' !== $image
			? $image
			: sprintf(
				'<span class="field-kit__gallery-name">%s</span>',
				esc_html( $name )
			);

		return sprintf(
			'<li class="field-kit__gallery-item%1$s" data-id="%2$d">%3$s%4$s' .
			'<span class="field-kit__gallery-position screen-reader-text">%5$s</span>' .
			'<span class="field-kit__gallery-actions">%6$s</span></li>',
			'' !== $image ? '' : ' field-kit__gallery-item--file',
			$id,
			$this->handle( $name, $position, $total ),
			$body,
			esc_html(
				sprintf(
					/* translators: 1: item position, 2: total items, 3: item name */
					__( 'Item %1$d of %2$d: %3$s', 'arraypress' ),
					$position + 1,
					$total,
					$name
				)
			),
			$this->remove_button( $name )
		);
	}

	/**
	 * The grab handle, which is also the keyboard's way to reorder.
	 *
	 * One control rather than the pair of chevrons this replaced: those were
	 * two buttons doing one job, took a click per position, and left a
	 * permanently disabled one on the first and last items.
	 *
	 * A button and not a decorative span, because a drag cannot be performed
	 * from a keyboard at all. The position is in the accessible name so a
	 * move announces where the item landed, kept as a template the script
	 * rewrites rather than a second string to translate.
	 *
	 * @param string $name     The item's name.
	 * @param int    $position Zero-based position.
	 * @param int    $total    How many there are.
	 *
	 * @return string
	 */
	private function handle( string $name, int $position, int $total ): string {
		$handle = new Attributes();
		$handle->set( 'type', 'button' );
		$handle->add_class( 'field-kit__drag-handle', 'field-kit__gallery-handle' );
		$handle->set( 'aria-roledescription', __( 'Sortable', 'arraypress' ) );
		$handle->set( 'aria-keyshortcuts', 'ArrowUp ArrowDown' );

		$template = sprintf(
			/* translators: 1: item name, 2: its position, 3: how many there are */
			__( 'Reorder %1$s, %2$s of %3$s', 'arraypress' ),
			$name,
			'{position}',
			'{total}'
		);

		$handle->set( 'data-label-template', $template );
		$handle->set(
			'aria-label',
			strtr(
				$template,
				[
					'{position}' => (string) ( $position + 1 ),
					'{total}'    => (string) $total,
				]
			)
		);

		return sprintf(
			'<button%s><span class="dashicons dashicons-menu" aria-hidden="true"></span></button>',
			$handle->render()
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
		$ids   = array_values( array_filter( array_map( 'absint', $value ) ) );
		$max   = (int) $field->get( 'max_items', 0 );

		// Enforced here as well as in the browser. A limit the script alone
		// keeps is not a limit — the value posts as a plain comma-separated
		// list, and anything can post one.
		return $max > 0 ? array_slice( $ids, 0, $max ) : $ids;
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

	/**
	 * Each tile removes itself, so there is nothing for a clear button to do.
	 *
	 * @return bool
	 */
	protected function shows_clear(): bool {
		return false;
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'add_label', 'frame_title', 'max_items', 'mime_type', 'preview_size' ]
		);
	}
}
