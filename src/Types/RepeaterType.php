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
 *
 * Two things decide the shape. `layout` is `stacked` — a bordered card per
 * row — or `table`, which is a real table with a header derived from the
 * fields. `direction` is which way a card's own fields run: `row` across, or
 * `column` down. A card of two short text fields wants across; a card with a
 * textarea or six fields in it is unreadable that way and wants down.
 *
 * `direction` does nothing to a table, where the columns are the direction.
 */
/*
 * Not final: FilesType is a repeater whose two columns are already decided,
 * and expressing that as a subclass is what stops it being a second copy of
 * the row, template and reindex logic.
 */
class RepeaterType extends AbstractNestedType {

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

		// Which sub-field a collapsed row is named by, so the script can keep
		// the header in step with what is typed into it.
		if ( $this->is_collapsible( $field ) ) {
			$wrapper->add_class( 'field-kit__repeater--collapsible' );
			$wrapper->set_if(
				'' !== (string) $field->get( 'row_title', '' ),
				'data-row-title',
				(string) $field->get( 'row_title', '' )
			);
		}

		$markup = '';

		foreach ( $rows as $index => $row ) {
			$markup .= $this->render_row( $field, (int) $index, $row, $total );
		}

		// Which way a row's own fields run. Only meaningful for the card
		// layout — a table's columns are the direction.
		if ( 'column' === (string) $field->get( 'direction', 'row' ) && ! $this->is_table( $field ) ) {
			$wrapper->add_class( 'field-kit__repeater--column' );
		}

		// A one-column row is a single line rather than a card of fields, and
		// is laid out as one: its controls sit on the line's middle instead
		// of floating above the input they act on.
		if ( ! $this->is_table( $field ) && ! $this->labels_rows( $field ) ) {
			$wrapper->add_class( 'field-kit__repeater--single' );
		}

		if ( $this->is_table( $field ) ) {
			$wrapper->add_class( 'field-kit__repeater--table' );

			// The template goes inside the table. A <tr> inside a <template>
			// that sits in a <div> is dropped by the HTML parser — template
			// content is parsed in the context the template appears in — so
			// the row to clone would simply not be there.
			return sprintf(
				'<div%s><table class="wp-list-table widefat striped field-kit__repeater-table">%s' .
				'<tbody class="field-kit__repeater-rows" data-empty="%s">%s</tbody>%s%s</table>%s</div>',
				$wrapper->render(),
				$this->render_head( $field ),
				$total > 0 ? 'false' : 'true',
				$markup,
				// Inside the table, as core's own list tables put it: a
				// message below an empty frame reads as a table that failed
				// to load, and leaves a bare row of headers sitting over
				// nothing.
				$this->render_empty_row( $field, $total ),
				$this->render_template( $field ),
				$this->render_status() . $this->render_add_button( $field )
			);
		}

		return sprintf(
			'<div%s><ol class="field-kit__repeater-rows" data-empty="%s">%s</ol>%s%s%s%s</div>',
			$wrapper->render(),
			$total > 0 ? 'false' : 'true',
			$markup,
			$this->render_empty_message( $total ),
			$this->render_status(),
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

		// Scoped by row: without it every row reuses the same child ids and
		// each label after the first points at the wrong control.
		$fields = $this->render_children(
			$field,
			$row,
			$field->input_name() . '[' . $index . ']',
			'row' . $index,
			null,
			$this->labels_rows( $field )
		);

		$actions = $this->row_handle( $index, $total )
			. $this->row_button( 'remove', $position, __( 'Remove', 'arraypress' ), 'trash', false );

		if ( $this->is_collapsible( $field ) ) {
			return $this->render_collapsible_row( $field, $index, $row, $position, $fields, $actions );
		}

		return sprintf(
			'<li class="field-kit__repeater-row" data-index="%d">' .
			'<span class="field-kit__repeater-position screen-reader-text">%s</span>' .
			'<div class="field-kit__repeater-fields">%s</div>' .
			'<div class="field-kit__repeater-actions">%s</div></li>',
			$index,
			esc_html( $position ),
			$fields,
			$actions
		);
	}

	/**
	 * A row that can be folded away, titled by one of its own fields.
	 *
	 * Three price tiers, each with a licence and its own files, is three
	 * screens of form to scroll past to reach the third. Collapsed, the same
	 * three are a list you can see at once -- which is also the only view
	 * that makes reordering them mean anything.
	 *
	 * The header is a button so the whole strip is the target rather than a
	 * chevron at the end of it, and the title doubles as the row's accessible
	 * name: `aria-describedby` on a "Show or hide" button is what tells a
	 * screen reader which row is being shown or hidden.
	 *
	 * @param Field                $field    The repeater.
	 * @param int                  $index    Zero-based row index.
	 * @param array<string, mixed> $row      The row's values.
	 * @param string               $position Human row position.
	 * @param string               $fields   The rendered children.
	 * @param string               $actions  The rendered row controls.
	 *
	 * @return string
	 */
	private function render_collapsible_row(
		Field $field,
		int $index,
		array $row,
		string $position,
		string $fields,
		string $actions
	): string {
		$closed   = $this->starts_closed( $field, $row );
		$title_id = $field->input_id() . '_row' . $index . '_title';

		return sprintf(
			'<li class="field-kit__repeater-row field-kit__repeater-row--collapsible%1$s" data-index="%2$d">' .
			'<span class="field-kit__repeater-position screen-reader-text">%3$s</span>' .
			'<div class="field-kit__repeater-header">' .
			'<button type="button" class="field-kit__repeater-toggle" aria-expanded="%4$s" aria-describedby="%5$s">' .
			'<span class="screen-reader-text">%6$s</span>' .
			'<span class="toggle-indicator" aria-hidden="true"></span>' .
			'</button>' .
			'<span class="field-kit__repeater-title" id="%5$s" data-fallback="%10$s">%7$s</span>' .
			'<span class="field-kit__repeater-actions">%8$s</span>' .
			'</div>' .
			'<div class="field-kit__repeater-fields">%9$s</div></li>',
			$closed ? ' is-closed' : '',
			$index,
			esc_html( $position ),
			$closed ? 'false' : 'true',
			esc_attr( $title_id ),
			esc_html__( 'Show or hide this row', 'arraypress' ),
			esc_html( $this->row_title( $field, $row, $index ) ),
			$actions,
			$fields,
			esc_attr( $this->position_title( $index ) )
		);
	}

	/**
	 * Whether rows fold away.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function is_collapsible( Field $field ): bool {
		return (bool) $field->get( 'collapsible', false ) && ! $this->is_table( $field );
	}

	/**
	 * Whether a row is drawn closed.
	 *
	 * A row with nothing in it is drawn open whatever the setting says: one
	 * just added is empty, and folding it away hides the fields somebody
	 * added it to fill in.
	 *
	 * @param Field                $field The field.
	 * @param array<string, mixed> $row   The row's values.
	 *
	 * @return bool
	 */
	private function starts_closed( Field $field, array $row ): bool {
		return (bool) $field->get( 'collapsed', true ) && $this->has_content( $row );
	}

	/**
	 * What a collapsed row says.
	 *
	 * The field named by `row_title` where it holds anything, since that is
	 * what the row is called -- a price tier's name, a rate's country. The
	 * position otherwise, because a list of rows all saying "Untitled" is a
	 * list you cannot tell apart, and an empty header is a strip of nothing
	 * to click.
	 *
	 * @param Field                $field The field.
	 * @param array<string, mixed> $row   The row's values.
	 * @param int                  $index Zero-based row index.
	 *
	 * @return string
	 */
	private function row_title( Field $field, array $row, int $index ): string {
		$key = (string) $field->get( 'row_title', '' );

		if ( '' !== $key && isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) && '' !== (string) $row[ $key ] ) {
			return (string) $row[ $key ];
		}

		return $this->position_title( $index );
	}

	/**
	 * A row's position, as a name for one with nothing to call it.
	 *
	 * Clamped, because the template the script clones is rendered at index
	 * -1 and read literally that is a row titled "Row 0".
	 *
	 * @param int $index Zero-based row index.
	 *
	 * @return string
	 */
	private function position_title( int $index ): string {
		return sprintf(
			/* translators: %d: row number */
			__( 'Row %d', 'arraypress' ),
			max( 1, $index + 1 )
		);
	}

	/**
	 * A row's controls have one line to fit in.
	 *
	 * @return array<string, mixed>
	 */
	protected function child_config_extras(): array {
		return [ 'inline' => true ];
	}

	/**
	 * Whether a row's fields carry their own visible labels.
	 *
	 * They do when a row holds several — a name, a price and a limit need
	 * telling apart. They do not when a row holds one, because that label is
	 * the same word on every row and the field already has a heading above
	 * the whole list: a three-entry feature list drew "Item" three times,
	 * one line each, above three identical inputs.
	 *
	 * The control keeps the label as its accessible name either way; the
	 * renderer moves it to an aria-label when it is not drawn.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	protected function labels_rows( Field $field ): bool {
		return count( $field->sub_fields() ) > 1;
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
			'<td class="field-kit__repeater-actions">%s%s</td></tr>',
			$index,
			$cells,
			$this->row_handle( $index, $total ),
			$this->row_button( 'remove', $position, __( 'Remove', 'arraypress' ), 'trash', false )
		);
	}

	/**
	 * The grab handle, which is also the keyboard's way to reorder.
	 *
	 * One control rather than the pair of chevrons this replaced. Those were
	 * two buttons doing one job, they took a click per position so moving a
	 * row four places took four, and the top and bottom rows each had a
	 * permanently disabled one sitting in the actions column.
	 *
	 * A button and not a decorative span: dragging cannot be done from a
	 * keyboard at all, so a handle that is only draggable makes the list
	 * unorderable without a pointer. Focus it and press the arrow keys.
	 *
	 * The position is in the accessible name because a handle that only says
	 * "Reorder row" gives no feedback that anything happened; announcing
	 * "3 of 4" is how a keyboard user knows the move landed. It is kept as a
	 * template so the script can rewrite the numbers after a move without a
	 * second copy of the wording to translate.
	 *
	 * @param int $index Zero-based row index.
	 * @param int $total How many rows there are.
	 *
	 * @return string
	 */
	protected function row_handle( int $index, int $total ): string {
		$handle = new Attributes();
		$handle->set( 'type', 'button' );
		$handle->add_class( 'field-kit__drag-handle', 'field-kit__repeater-handle' );
		$handle->set( 'aria-roledescription', __( 'Sortable', 'arraypress' ) );
		$handle->set( 'aria-keyshortcuts', 'ArrowUp ArrowDown' );

		$template = sprintf(
			/* translators: 1: row position, 2: how many rows there are */
			__( 'Reorder row, %1$s of %2$s', 'arraypress' ),
			'{position}',
			'{total}'
		);

		$handle->set( 'data-label-template', $template );
		$handle->set(
			'aria-label',
			strtr(
				$template,
				[
					'{position}' => (string) ( $index + 1 ),
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
	 * A per-row action button.
	 *
	 * A bin rather than a cross: in a collapsible header the cross sits
	 * beside the control that folds the row away, and the two read as the
	 * same thing. One of them destroys the row.
	 *
	 * @param string $action   Action name for the script.
	 * @param string $position Human row position, for the accessible label.
	 * @param string $label    Action label.
	 * @param string $icon     Dashicon suffix.
	 * @param bool   $disabled Whether the action is available.
	 *
	 * @return string
	 */
	protected function row_button( string $action, string $position, string $label, string $icon, bool $disabled ): string {
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
	 * The empty state, as a row of the table itself.
	 *
	 * core writes `<tr class="no-items"><td class="colspanchange" colspan="N">`
	 * and so does this. A message printed under the table instead leaves a
	 * row of column headings sitting over nothing at all, which reads as a
	 * table that failed to load rather than one with nothing in it yet.
	 *
	 * It stays in the markup and is hidden once there is a row, so the script
	 * has something to show again when the last row is removed.
	 *
	 * @param Field $field The field.
	 * @param int   $total How many rows there are.
	 *
	 * @return string
	 */
	private function render_empty_row( Field $field, int $total ): string {
		return sprintf(
			'<tbody class="field-kit__repeater-empty"%s><tr class="no-items">' .
			'<td class="colspanchange" colspan="%d">%s</td></tr></tbody>',
			$total > 0 ? ' hidden' : '',
			// One per sub-field, plus the actions column.
			count( $field->sub_fields() ) + 1,
			esc_html( (string) $field->get( 'empty_label', __( 'No rows yet.', 'arraypress' ) ) )
		);
	}

	/**
	 * The live region that says what adding and removing a row did.
	 *
	 * Its own element rather than the empty message, which is hidden the
	 * moment a row exists -- and a hidden live region announces nothing.
	 * Nor the first `[aria-live]` inside the repeater, which on a row that
	 * holds a range or a tags field is that field's own readout.
	 *
	 * @return string
	 */
	private function render_status(): string {
		return '<span class="field-kit__repeater-status screen-reader-text" aria-live="polite"></span>';
	}

	/**
	 * The message shown when there are no rows.
	 *
	 * Hidden, not removed, once a row exists, so the script can show it
	 * again when the last row goes. It is not a live region: the status
	 * element beside it does the announcing, because a hidden region
	 * announces nothing and this one is hidden precisely when a row is
	 * added.
	 */
	private function render_empty_message( int $total ): string {
		return sprintf(
			'<p class="field-kit__repeater-empty description"%s>%s</p>',
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
	protected function rows( Field $field ): array {
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

		// What each row already holds, by position. A type that consults its
		// stored value on the way in -- a licence that must not be overwritten
		// by its own mask -- needs the row it belongs to, not the whole list.
		$stored = (array) $field->value();
		$rows   = [];

		foreach ( $value as $index => $row ) {
			$clean = $this->sanitize_children( $field, $row, null, (array) ( $stored[ $index ] ?? [] ) );

			if ( $this->has_content( $clean ) ) {
				$rows[] = $clean;
			}
		}

		return $this->apply_max_rows( $rows, $field );
	}

	/**
	 * Cut the rows down to the configured maximum.
	 *
	 * The control refuses to add past `max_rows`, but the control is
	 * JavaScript and the rows arrive over HTTP. A limit enforced only in the
	 * browser is not a limit, so the extras are dropped here as well -- from
	 * the end, so the editor keeps what they entered first.
	 *
	 * @param array<int, array<string, mixed>> $rows  The sanitized rows.
	 * @param Field                            $field The field.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function apply_max_rows( array $rows, Field $field ): array {
		$max = (int) $field->get( 'max_rows', 0 );

		return $max > 0 ? array_slice( $rows, 0, $max ) : $rows;
	}

	/**
	 * Whether a sanitized row holds anything at all.
	 *
	 * @param array<string, mixed> $row Sanitized row.
	 *
	 * @return bool
	 */
	protected function has_content( array $row ): bool {
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

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'add_label', 'collapsed', 'collapsible', 'direction', 'empty_label', 'layout', 'max_rows', 'min_rows', 'row_title' ]
		);
	}
}
