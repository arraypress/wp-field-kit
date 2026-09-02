<?php
/**
 * Flexible Content Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.1.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * Class FlexibleType
 *
 * A repeater whose rows are not all the same shape.
 *
 * Where a repeater has one set of fields repeated, this has several named
 * layouts and each row picks one. It is what page builders are made of: a hero
 * row, then two text columns, then a gallery, in whatever order the editor
 * wants.
 *
 * Each row stores its layout under `_layout` alongside that layout's values.
 * The key is prefixed because it shares a namespace with the layout's own field
 * keys, and a field genuinely called `layout` is not far-fetched.
 *
 * A row whose layout no longer exists is kept rather than dropped: removing a
 * layout from the configuration should not silently delete the content someone
 * wrote with it. It renders as a notice and its values pass through untouched.
 */
final class FlexibleType extends RepeaterType {

	/**
	 * The key each row stores its layout under.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const LAYOUT_KEY = '_layout';

	/**
	 * The type's id.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function id(): string {
		return 'flexible';
	}

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 * @since 1.1.0
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			[
				'add_label' => __( 'Add row', 'arraypress' ),
				'layouts'   => [],
			]
		);
	}

	/**
	 * The configured layouts, normalised.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, array{label: string, fields: array}>
	 * @since 1.1.0
	 */
	public function layouts( Field $field ): array {
		$layouts = $field->get( 'layouts', [] );

		if ( ! is_array( $layouts ) ) {
			return [];
		}

		$clean = [];

		foreach ( $layouts as $slug => $layout ) {
			$slug = sanitize_key( (string) $slug );

			if ( '' === $slug || ! is_array( $layout ) ) {
				continue;
			}

			$clean[ $slug ] = [
				'label'  => (string) ( $layout['label'] ?? $slug ),
				'fields' => (array) ( $layout['fields'] ?? [] ),
			];
		}

		return $clean;
	}

	/**
	 * The layout a stored row was written with.
	 *
	 * @param array $row The row.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	private function layout_of( array $row ): string {
		return sanitize_key( (string) ( $row[ self::LAYOUT_KEY ] ?? '' ) );
	}

	/**
	 * The control.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Control attributes.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$layouts = $this->layouts( $field );

		if ( [] === $layouts ) {
			return sprintf(
				'<p class="field-kit__flexible-empty notice notice-warning inline">%s</p>',
				esc_html__( 'This field has no layouts configured.', 'arraypress' )
			);
		}

		$rows  = $this->rows( $field );
		$total = count( $rows );

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__repeater', 'field-kit__flexible' );
		$wrapper->set( 'data-field-name', $field->input_name() );
		$wrapper->set( 'data-min-rows', (int) $field->get( 'min_rows', 0 ) );
		$wrapper->set( 'data-max-rows', (int) $field->get( 'max_rows', 0 ) );

		$markup = '';

		foreach ( $rows as $index => $row ) {
			$markup .= $this->render_flexible_row( $field, (int) $index, (array) $row, $total, $layouts );
		}

		$templates = '';

		foreach ( $layouts as $slug => $layout ) {
			// Rendered at an index no real row can occupy, so the template's
			// ids cannot collide with a rendered row's. The script renumbers
			// them when it clones.
			$templates .= sprintf(
				'<template class="field-kit__repeater-template" data-layout="%s">%s</template>',
				esc_attr( $slug ),
				$this->render_flexible_row( $field, -1, [ self::LAYOUT_KEY => $slug ], 0, $layouts )
			);
		}

		return sprintf(
			'<div%s><ul class="field-kit__repeater-rows" data-empty="%s">%s</ul>%s%s</div>',
			$wrapper->render(),
			$total > 0 ? 'false' : 'true',
			$markup,
			$this->render_layout_picker( $field, $layouts ),
			$templates
		);
	}

	/**
	 * One row, rendered against the layout it chose.
	 *
	 * @param Field $field   The field.
	 * @param int   $index   Row index.
	 * @param array $row     Row values.
	 * @param int   $total   Total rows.
	 * @param array $layouts The configured layouts.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	private function render_flexible_row( Field $field, int $index, array $row, int $total, array $layouts ): string {
		$slug   = $this->layout_of( $row );
		$known  = isset( $layouts[ $slug ] );
		$prefix = $field->input_name() . '[' . $index . ']';

		$position = sprintf(
			/* translators: 1: row number, 2: total rows, 3: the layout's name */
			__( 'Row %1$d of %2$d, %3$s', 'arraypress' ),
			$index + 1,
			$total,
			$known ? $layouts[ $slug ]['label'] : $slug
		);

		$body = $known
			? $this->render_children( $field, $row, $prefix, 'row' . $index, $layouts[ $slug ]['fields'] )
			: $this->render_orphan( $slug, $row, $prefix );

		// The same strip a collapsible repeater row wears: the layout's name
		// as the title, the actions and the chevron at the end, the fields
		// underneath. The layout name used to sit in a gutter beside the
		// fields, so every row's controls started at a different column and
		// the actions floated loose at the top corner.
		$closed   = (bool) $field->get( 'collapsed', false ) && $index >= 0 && $this->has_content( $row );
		$title_id = $field->input_id() . '_row' . $index . '_title';

		return sprintf(
			'<li class="field-kit__repeater-row field-kit__repeater-row--collapsible field-kit__flexible-row%1$s" data-index="%2$d" data-layout="%3$s">' .
			'<span class="field-kit__repeater-position screen-reader-text">%4$s</span>' .
			'<input type="hidden" name="%5$s[%6$s]" value="%3$s" />' .
			'<div class="field-kit__repeater-header">' .
			'<span class="field-kit__repeater-title" id="%7$s">%8$s</span>' .
			'<span class="field-kit__repeater-actions">%9$s%10$s%11$s</span>' .
			'<button type="button" class="field-kit__repeater-toggle" aria-expanded="%12$s" aria-describedby="%7$s">' .
			'<span class="screen-reader-text">%13$s</span>' .
			'<span class="toggle-indicator" aria-hidden="true"></span>' .
			'</button>' .
			'</div>' .
			'<div class="field-kit__repeater-fields">%14$s</div></li>',
			$closed ? ' is-closed' : '',
			$index,
			esc_attr( $slug ),
			esc_html( $position ),
			esc_attr( $prefix ),
			esc_attr( self::LAYOUT_KEY ),
			esc_attr( $title_id ),
			esc_html( $known ? $layouts[ $slug ]['label'] : $slug ),
			$this->row_button( 'move-up', $position, __( 'Move up', 'arraypress' ), 'arrow-up-alt2', $index < 1 ),
			$this->row_button( 'move-down', $position, __( 'Move down', 'arraypress' ), 'arrow-down-alt2', $index >= $total - 1 ),
			$this->row_button( 'remove', $position, __( 'Remove', 'arraypress' ), 'trash', false ),
			$closed ? 'false' : 'true',
			esc_html__( 'Show or hide this row', 'arraypress' ),
			$body
		);
	}

	/**
	 * A row whose layout is no longer configured.
	 *
	 * Its values are carried in hidden inputs rather than dropped. Removing a
	 * layout from the configuration should not delete what somebody wrote with
	 * it -- putting the layout back has to bring the content back too.
	 *
	 * @param string $slug   The missing layout.
	 * @param array  $row    The row's stored values.
	 * @param string $prefix The row's input name prefix.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	private function render_orphan( string $slug, array $row, string $prefix ): string {
		$hidden = '';

		foreach ( $row as $key => $value ) {
			if ( self::LAYOUT_KEY === $key || is_array( $value ) ) {
				continue;
			}

			$hidden .= sprintf(
				'<input type="hidden" name="%s[%s]" value="%s" />',
				esc_attr( $prefix ),
				esc_attr( (string) $key ),
				esc_attr( (string) $value )
			);
		}

		return sprintf(
			'<p class="notice notice-warning inline field-kit__flexible-orphan">%s</p>%s',
			esc_html(
				sprintf(
					/* translators: %s: the layout name stored on the row */
					__( 'The “%s” layout is no longer available. This row is kept as it is.', 'arraypress' ),
					$slug
				)
			),
			$hidden
		);
	}

	/**
	 * The control for adding a row of a given layout.
	 *
	 * One button per layout while there are few of them, and a select once
	 * there are enough that a row of buttons stops being scannable.
	 *
	 * @param Field $field   The field.
	 * @param array $layouts The configured layouts.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	private function render_layout_picker( Field $field, array $layouts ): string {
		if ( count( $layouts ) > 4 ) {
			$options = '';

			foreach ( $layouts as $slug => $layout ) {
				$options .= sprintf(
					'<option value="%s">%s</option>',
					esc_attr( $slug ),
					esc_html( $layout['label'] )
				);
			}

			return sprintf(
				'<p class="field-kit__flexible-add"><select class="field-kit__flexible-layout" aria-label="%s">%s</select>' .
				'<button type="button" class="button field-kit__repeater-add">%s</button></p>',
				esc_attr__( 'Layout to add', 'arraypress' ),
				$options,
				esc_html( (string) $field->get( 'add_label', __( 'Add row', 'arraypress' ) ) )
			);
		}

		$buttons = '';

		foreach ( $layouts as $slug => $layout ) {
			$buttons .= sprintf(
				'<button type="button" class="button field-kit__repeater-add" data-layout="%s">%s</button> ',
				esc_attr( $slug ),
				esc_html(
					sprintf(
						/* translators: %s: the layout's name */
						__( 'Add %s', 'arraypress' ),
						$layout['label']
					)
				)
			);
		}

		return sprintf( '<p class="field-kit__flexible-add">%s</p>', trim( $buttons ) );
	}

	/**
	 * Sanitise each row against the layout it chose.
	 *
	 * @param mixed $value The submitted rows.
	 * @param Field $field The field.
	 *
	 * @return array
	 * @since 1.1.0
	 */
	public function sanitize( mixed $value, Field $field ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$layouts = $this->layouts( $field );
		$stored  = (array) $field->value();
		$rows    = [];

		foreach ( $value as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$slug = $this->layout_of( $row );

			if ( '' === $slug ) {
				continue;
			}

			// A row whose layout has gone is passed through as text rather
			// than sanitised against fields that no longer exist -- which
			// would discard every one of its values.
			if ( ! isset( $layouts[ $slug ] ) ) {
				$rows[] = [ self::LAYOUT_KEY => $slug ] + $this->orphan_row( $row );

				continue;
			}

			$clean = $this->sanitize_children( $field, $row, $layouts[ $slug ]['fields'], (array) ( $stored[ $index ] ?? [] ) );

			if ( $this->has_content( $clean ) ) {
				$rows[] = array_merge( [ self::LAYOUT_KEY => $slug ], $clean );
			}
		}

		return $this->apply_max_rows( $rows, $field );
	}

	/**
	 * A row kept as text, because its layout no longer exists.
	 *
	 * The layout key is dropped before the rest is read: left in, the
	 * submitted spelling of it -- text, not a key -- won over the slug the
	 * row was matched by. What remains is keyed by keys and holds text.
	 *
	 * @param array $row The submitted row.
	 *
	 * @return array<string, string>
	 * @since 1.1.0
	 */
	private function orphan_row( array $row ): array {
		unset( $row[ self::LAYOUT_KEY ] );

		$kept = [];

		foreach ( array_filter( $row, 'is_scalar' ) as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' !== $key ) {
				$kept[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $kept;
	}

	/**
	 * Configuration this type reads.
	 *
	 * @return array
	 * @since 1.1.0
	 */
	public function config_keys(): array {
		return array_merge( parent::config_keys(), [ 'layouts' ] );
	}
}
