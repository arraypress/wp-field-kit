<?php
/**
 * Sortable Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A list the user puts in order.
 *
 * Ordering and choosing are separate jobs, and only the first is always
 * wanted. A checkbox beside every row invites the reader to work out what
 * unticking one would do, and in a list that is only ever reordered the
 * answer is nothing. `selectable` turns the choosing half on for the fields
 * that want both.
 *
 * Each row is a list item in an ordered list, so its position is structural
 * rather than only visual. Reordering is by drag handle, with move buttons
 * that are hidden until focused — a drag cannot be performed from a keyboard,
 * so those buttons are the only way to reorder without a pointer.
 */
final class SortableType extends AbstractType {

	/**
	 * Render the ordered list.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$options    = $field->options();
		$order      = $this->ordered_keys( $field, $options );
		$total      = count( $order );
		$name       = (string) $attributes->get( 'name' );
		$active     = array_map( 'strval', (array) $field->value() );
		$selectable = (bool) $field->get( 'selectable', false );
		$markup     = '';

		foreach ( $order as $position => $key ) {
			$label    = (string) ( $options[ $key ] ?? $key );
			$included = ! $selectable || [] === $active || in_array( (string) $key, $active, true );

			$markup .= sprintf(
				'<li class="field-kit__sortable-item" data-key="%s">%s' .
				'<span class="field-kit__sortable-actions">%s%s' .
				'<span class="field-kit__drag-handle dashicons dashicons-menu" aria-hidden="true"></span>' .
				'</span></li>',
				esc_attr( (string) $key ),
				$this->render_control( $name, (string) $key, $label, $included, $selectable ),
				$this->move_button( $label, 'up', $position < 1 ),
				$this->move_button( $label, 'down', $position >= $total - 1 )
			);
		}

		return sprintf( '<ol class="field-kit__sortable">%s</ol>', $markup );
	}

	/**
	 * Render one row's value and label.
	 *
	 * @param string $name       The input name.
	 * @param string $key        The option key.
	 * @param string $label      The option label.
	 * @param bool   $included   Whether the item is selected.
	 * @param bool   $selectable Whether items can be excluded.
	 *
	 * @return string
	 */
	private function render_control( string $name, string $key, string $label, bool $included, bool $selectable ): string {
		if ( ! $selectable ) {
			// Order alone: the value rides in a hidden input, since the
			// order of the posted values is the whole answer.
			return sprintf(
				'<input type="hidden" name="%s" value="%s" /><span class="field-kit__sortable-label">%s</span>',
				esc_attr( $name . '[]' ),
				esc_attr( $key ),
				esc_html( $label )
			);
		}

		$box = new Attributes();
		$box->set( 'type', 'checkbox' );
		$box->set( 'name', $name . '[]' );
		$box->set( 'value', $key );
		$box->set_if( $included, 'checked', true );

		return sprintf(
			'<label class="field-kit__sortable-label"><input%s /> <span>%s</span></label>',
			$box->render(),
			esc_html( $label )
		);
	}

	/**
	 * A reorder button.
	 *
	 * @param string $label     The item's label, for the accessible name.
	 * @param string $direction Either "up" or "down".
	 * @param bool   $disabled  Whether the move is possible.
	 *
	 * @return string
	 */
	private function move_button( string $label, string $direction, bool $disabled ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button-link', 'field-kit__sortable-move' );
		$button->set( 'data-direction', $direction );
		$button->set_if( $disabled, 'disabled', true );
		$button->set(
			'aria-label',
			'up' === $direction
				/* translators: %s: option label */
				? sprintf( __( 'Move %s earlier', 'arraypress' ), $label )
				/* translators: %s: option label */
				: sprintf( __( 'Move %s later', 'arraypress' ), $label )
		);

		return sprintf(
			'<button%s><span class="dashicons dashicons-arrow-%s-alt2" aria-hidden="true"></span></button>',
			$button->render(),
			'up' === $direction ? 'up' : 'down'
		);
	}

	/**
	 * The option keys in their stored order, with any new options appended.
	 *
	 * An option added to the config after a value was stored must still
	 * appear, or it becomes invisible and unreachable.
	 *
	 * @param Field                 $field   The field.
	 * @param array<string, string> $options Configured options.
	 *
	 * @return string[]
	 */
	private function ordered_keys( Field $field, array $options ): array {
		$stored = array_map( 'strval', (array) $field->value() );
		$keys   = array_map( 'strval', array_keys( $options ) );
		$order  = array_values( array_intersect( $stored, $keys ) );

		return array_merge( $order, array_values( array_diff( $keys, $order ) ) );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return string[]
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$allowed = array_map( 'strval', array_keys( $field->options() ) );
		$values  = array_map( 'strval', (array) $value );

		return array_values( array_intersect( $values, $allowed ) );
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
	 * Scripts and styles this needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		return [
			'scripts' => [],
			'styles'  => [ 'dashicons' ],
		];
	}

	/**
	 * The chosen keys, in the order they were arranged.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'  => 'array',
			'items' => [ 'type' => 'string' ],
		];
	}
}
