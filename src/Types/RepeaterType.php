<?php
/**
 * Repeater Field Type
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Renderer;

/**
 * A repeating set of fields.
 *
 * Rows are an ordered list, so their position is structural rather than only
 * visual, and each row carries move and remove buttons naming the row they
 * act on — a screen with several repeaters would otherwise present a column
 * of identical "Remove" buttons.
 *
 * Reordering is available from the keyboard as well as by dragging. A
 * drag-only repeater cannot be reordered without a mouse at all, which is the
 * half these controls usually skip.
 *
 * The row template is emitted once, disabled and hidden, rather than built by
 * string concatenation in the script: the markup then comes from the same
 * renderer as a real row, so a new row is accessible for the same reason an
 * existing one is.
 */
final class RepeaterType extends AbstractNestedType {

	/**
	 * Config defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'add_label' => __( 'Add row', 'arraypress' ),
			'min_rows'  => 0,
			'max_rows'  => 0,
		];
	}

	/**
	 * Render the rows, the template and the add button.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$rows  = $this->rows( $field );
		$total = count( $rows );

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__repeater' );
		$wrapper->set( 'data-field-name', $field->input_name() );
		$wrapper->set( 'data-min-rows', (int) $field->get( 'min_rows', 0 ) );
		$wrapper->set( 'data-max-rows', (int) $field->get( 'max_rows', 0 ) );

		$markup = '';

		foreach ( $rows as $index => $row ) {
			$markup .= $this->render_row( $field, (int) $index, $row, $total );
		}

		if ( $this->is_table( $field ) ) {
			$wrapper->add_class( 'field-kit__repeater--table' );

			// The template goes inside the table. A <tr> inside a <template>
			// that sits in a <div> is dropped by the HTML parser — template
			// content is parsed in the context the template appears in — so
			// the row to clone would simply not be there.
			return sprintf(
				'<div%s><table class="wp-list-table widefat striped field-kit__repeater-table">%s' .
				'<tbody class="field-kit__repeater-rows" data-empty="%s">%s</tbody>%s</table>%s%s</div>',
				$wrapper->render(),
				$this->render_head( $field ),
				$total > 0 ? 'false' : 'true',
				$markup,
				$this->render_template( $field ),
				$this->render_empty_message( $total ),
				$this->render_add_button( $field )
			);
		}

		return sprintf(
			'<div%s><ol class="field-kit__repeater-rows" data-empty="%s">%s</ol>%s%s%s</div>',
			$wrapper->render(),
			$total > 0 ? 'false' : 'true',
			$markup,
			$this->render_empty_message( $total ),
			$this->render_template( $field ),
			$this->render_add_button( $field )
		);
	}

	/**
	 * Render one row.
	 *
	 * @param Field                $field The field.
	 * @param int                  $index Row index.
	 * @param array<string, mixed> $row   Row values.
	 * @param int                  $total Total rows.
	 *
	 * @return string
	 */
	private function render_row( Field $field, int $index, array $row, int $total ): string {
		$position = sprintf(
			/* translators: 1: row number, 2: total rows */
			__( 'Row %1$d of %2$d', 'arraypress' ),
			$index + 1,
			$total
		);

		if ( $this->is_table( $field ) ) {
			return $this->render_table_row( $field, $index, $row, $position );
		}

		return sprintf(
			'<li class="field-kit__repeater-row" data-index="%d">' .
			'<span class="field-kit__repeater-position screen-reader-text">%s</span>' .
			'<div class="field-kit__repeater-fields">%s</div>' .
			'<div class="field-kit__repeater-actions">%s%s%s</div></li>',
			$index,
			esc_html( $position ),
			// Scoped by row: without it every row reuses the same child ids
			// and each label after the first points at the wrong control.
			$this->render_children( $field, $row, $field->input_name() . '[' . $index . ']', 'row' . $index ),
			$this->row_button( 'move-up', $position, __( 'Move up', 'arraypress' ), 'arrow-up-alt2', $index < 1 ),
			$this->row_button( 'move-down', $position, __( 'Move down', 'arraypress' ), 'arrow-down-alt2', $index >= $total - 1 ),
			$this->row_button( 'remove', $position, __( 'Remove', 'arraypress' ), 'no-alt', false )
		);
	}

	/**
	 * Whether this repeater is drawn as a table.
	 *
	 * A stack of rows is right when a row is a handful of fields with long
	 * labels. It is wrong when a row is three short columns repeated twenty
	 * times — a tax rate, a price tier, a redirect — where the labels belong
	 * once at the top and the rows want to line up under them.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	private function is_table( Field $field ): bool {
		return 'table' === (string) $field->get( 'layout', 'stacked' );
	}

	/**
	 * The column headers, from the row's own fields.
	 *
	 * Derived rather than configured, so the columns cannot drift from the
	 * fields underneath them.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function render_head( Field $field ): string {
		$cells = '';

		foreach ( $field->sub_fields() as $key => $config ) {
			$width = (string) ( $config['column_width'] ?? '' );

			$cells .= sprintf(
				'<th scope="col"%s>%s</th>',
				'' === $width ? '' : sprintf( ' style="width:%s"', esc_attr( $width ) ),
				esc_html( (string) ( $config['label'] ?? $key ) )
			);
		}

		// The actions column has no heading to give it, and an empty <th> is
		// announced as a blank column header rather than skipped.
		$cells .= sprintf(
			'<td class="field-kit__repeater-actions-head"><span class="screen-reader-text">%s</span></td>',
			esc_html__( 'Actions', 'arraypress' )
		);

		return sprintf( '<thead><tr>%s</tr></thead>', $cells );
	}

	/**
	 * One row of a table repeater.
	 *
	 * @param Field                $field    The field.
	 * @param int                  $index    Row index.
	 * @param array<string, mixed> $row      Row values.
	 * @param string               $position Human row position.
	 *
	 * @return string
	 */
	private function render_table_row( Field $field, int $index, array $row, string $position ): string {
		$total = count( $this->rows( $field ) );
		$cells = '';

		foreach ( array_keys( $field->sub_fields() ) as $key ) {
			$child = $this->child(
				$field,
				(string) $key,
				(array) $field->sub_fields()[ $key ],
				$row[ $key ] ?? null,
				$field->input_name() . '[' . $index . ']',
				'row' . $index
			);

			$cells .= sprintf(
				'<td data-colname="%s">%s</td>',
				esc_attr( (string) ( $field->sub_fields()[ $key ]['label'] ?? $key ) ),
				// The column header is the label, so the renderer draws none
				// — but the control keeps its id and the header cell is not a
				// <label>, so each control carries its own hidden one.
				null === $child ? '' : ( new Renderer() )->render( $child, '', false )
			);
		}

		// No position cell: it would be a column the header does not have, and
		// every row action already carries the position in its own label.
		return sprintf(
			'<tr class="field-kit__repeater-row" data-index="%d">' .
			'%s' .
			'<td class="field-kit__repeater-actions">%s%s%s</td></tr>',
			$index,
			$cells,
			$this->row_button( 'move-up', $position, __( 'Move up', 'arraypress' ), 'arrow-up-alt2', $index < 1 ),
			$this->row_button( 'move-down', $position, __( 'Move down', 'arraypress' ), 'arrow-down-alt2', $index >= $total - 1 ),
			$this->row_button( 'remove', $position, __( 'Remove', 'arraypress' ), 'no-alt', false )
		);
	}

	/**
	 * A per-row action button.
	 *
	 * @param string $action   Action name for the script.
	 * @param string $position Human row position, for the accessible label.
	 * @param string $label    Action label.
	 * @param string $icon     Dashicon suffix.
	 * @param bool   $disabled Whether the action is available.
	 *
	 * @return string
	 */
	private function row_button( string $action, string $position, string $label, string $icon, bool $disabled ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button-link', 'field-kit__repeater-' . $action );
		$button->set( 'data-action', $action );
		$button->set_if( $disabled, 'disabled', true );
		$button->set(
			'aria-label',
			sprintf(
				/* translators: 1: action such as "Remove", 2: row position such as "Row 2 of 5" */
				__( '%1$s — %2$s', 'arraypress' ),
				$label,
				$position
			)
		);

		return sprintf(
			'<button%s><span class="dashicons dashicons-%s" aria-hidden="true"></span></button>',
			$button->render(),
			esc_attr( $icon )
		);
	}

	/**
	 * The message shown when there are no rows.
	 *
	 * Announced politely so adding and removing the last row is not silent
	 * for someone who cannot see the list empty.
	 *
	 * @param int $total Total rows.
	 *
	 * @return string
	 */
	private function render_empty_message( int $total ): string {
		return sprintf(
			'<p class="field-kit__repeater-empty description" aria-live="polite"%s>%s</p>',
			$total > 0 ? ' hidden' : '',
			esc_html__( 'No rows yet.', 'arraypress' )
		);
	}

	/**
	 * The template a new row is cloned from.
	 *
	 * Inside a `<template>`, so its inputs are never submitted and never
	 * reachable by tab, and rendered through the ordinary renderer so a new
	 * row is built the same way an existing one is.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function render_template( Field $field ): string {
		// Rendered at an index no real row can occupy, so the template's own
		// ids never collide with a rendered row's. The script renumbers them
		// when it clones the row.
		return sprintf(
			'<template class="field-kit__repeater-template">%s</template>',
			$this->render_row( $field, -1, [], 0 )
		);
	}

	/**
	 * The add button.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function render_add_button( Field $field ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button', 'field-kit__repeater-add' );

		return sprintf(
			'<p><button%s>%s</button></p>',
			$button->render(),
			esc_html( (string) $field->get( 'add_label', __( 'Add row', 'arraypress' ) ) )
		);
	}

	/**
	 * The current rows, always a list.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( Field $field ): array {
		$value = $field->value();

		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_filter( $value, 'is_array' ) );
	}

	/**
	 * Coerce a submitted value.
	 *
	 * Rows are reindexed, since removing a middle row leaves a gap in the
	 * posted keys, and a row whose every field is empty is dropped rather
	 * than stored as a blank record.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize( mixed $value, Field $field ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$rows = [];

		foreach ( $value as $row ) {
			$clean = $this->sanitize_children( $field, $row );

			if ( $this->has_content( $clean ) ) {
				$rows[] = $clean;
			}
		}

		return $rows;
	}

	/**
	 * Whether a sanitized row holds anything at all.
	 *
	 * @param array<string, mixed> $row Sanitized row.
	 *
	 * @return bool
	 */
	private function has_content( array $row ): bool {
		foreach ( $row as $value ) {
			if ( is_array( $value ) ? [] !== $value : ( '' !== (string) $value && '0' !== (string) $value ) ) {
				return true;
			}
		}

		return false;
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
		// No jquery-ui-sortable: reordering is native pointer and drag
		// events plus the move buttons, so pulling in a jQuery UI module
		// would be a dependency nothing calls.
		return [
			'scripts' => [],
			'styles'  => [ 'dashicons' ],
		];
	}

	/**
	 * A list of rows, each the shape of the row's own fields.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'  => 'array',
			'items' => parent::schema( $field ),
		];
	}
}
