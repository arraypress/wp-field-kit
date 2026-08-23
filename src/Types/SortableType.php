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
 * A fixed set of options the user puts in order.
 *
 * Each item is a checkbox — so it can be included or excluded — inside an
 * ordered list, with move buttons for keyboard reordering alongside dragging.
 * Order is carried by a hidden input rather than by DOM position alone, so it
 * survives a form submission without relying on the script having run.
 */
final class SortableType extends AbstractType {

	/**
	 * Render the ordered options.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$options = $field->options();
		$order   = $this->ordered_keys( $field, $options );
		$total   = count( $order );
		$name    = (string) $attributes->get( 'name' );
		$active  = array_map( 'strval', (array) $field->value() );
		$markup  = '';

		foreach ( $order as $position => $key ) {
			$item_id = $field->input_id() . '_' . $position;

			$box = new Attributes();
			$box->set( 'type', 'checkbox' );
			$box->set( 'id', $item_id );
			$box->set( 'name', $name . '[]' );
			$box->set( 'value', (string) $key );
			$box->set_if( [] === $active || in_array( (string) $key, $active, true ), 'checked', true );

			$markup .= sprintf(
				'<li class="field-kit__sortable-item" data-key="%s"><input%s /><label for="%s">%s</label>%s%s</li>',
				esc_attr( (string) $key ),
				$box->render(),
				esc_attr( $item_id ),
				esc_html( (string) ( $options[ $key ] ?? $key ) ),
				$this->move_button( (string) ( $options[ $key ] ?? $key ), 'up', $position < 1 ),
				$this->move_button( (string) ( $options[ $key ] ?? $key ), 'down', $position >= $total - 1 )
			);
		}

		return sprintf(
			'<ol class="field-kit__sortable">%s</ol>',
			$markup
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
	 * appear, or it becomes invisible and unselectable.
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
			'scripts' => [ 'jquery-ui-sortable' ],
			'styles'  => [ 'dashicons' ],
		];
	}
}
